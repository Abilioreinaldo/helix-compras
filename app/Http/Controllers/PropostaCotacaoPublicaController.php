<?php

namespace App\Http\Controllers;

use App\Mail\RespostaCotacaoRecebida;
use App\Models\Cotacao;
use App\Models\CotacaoLink;
use App\Models\ItemRequisicao;
use App\Services\CotacaoLinkService;
use Closure;
use Helix\Foundation\Services\Platform\Support\ActivityRecorder;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tela PÚBLICA (sem login) em que o fornecedor preenche a proposta da cotação pelo
 * link assinado recebido por e-mail (decisão 11). É o único canal que grava proposta.
 *
 * Sem oráculo: assinatura inválida/expirada, token inexistente/adulterado, link
 * revogado/usado/expirado, link de outro tenant ou fornecedor, cotação apagada ou
 * fora de cotação — TODOS respondem a mesma página genérica com o mesmo status.
 * Rate limit por IP e por token (limiter `cotacao-link`, bootstrap/app.php).
 */
class PropostaCotacaoPublicaController extends Controller
{
    public function __construct(
        private CotacaoLinkService $links,
        private ActivityRecorder $atividade,
    ) {}

    public function show(Request $request, string $token): Response
    {
        return $this->comLinkValido($request, $token, function (CotacaoLink $link, Cotacao $cotacao) use ($request) {
            $this->atividade->record('compras.cotacao_link_visualizado', $link, null, [
                'actor_type' => 'fornecedor',
                ...$this->origem($request),
                'payload' => CotacaoLinkService::payloadEvento($link),
                'metadata' => ['cotacao_id' => $cotacao->id, 'fornecedor_id' => $link->fornecedor_id],
            ]);

            return response()->view('cotacao-publica.formulario', [
                'cotacao' => $cotacao,
                'itens' => $cotacao->requisicao->itens,
                'expiraEm' => $link->expires_at,
            ]);
        });
    }

    public function store(Request $request, string $token): Response
    {
        return $this->comLinkValido($request, $token, function (CotacaoLink $link, Cotacao $cotacao) use ($request) {
            $itens = $cotacao->requisicao->itens;
            $usaItens = $itens->isNotEmpty();

            // COMPRAS-8 (4ª auditoria): tetos LITERAIS de config/compras.php. Antes o unitário
            // aceitava 9.999.999.999 e o total não tinha teto (119.999.999.988 gravados).
            $limites = (array) config('compras.proposta_publica');
            $unitarioMaximo = (float) ($limites['valor_unitario_maximo'] ?? 99999999.99);
            $totalMaximo = (float) ($limites['valor_total_maximo'] ?? 999999999.99);
            $validadeMaxima = now()->addYears((int) ($limites['validade_maxima_anos'] ?? 2))->toDateString();

            $dados = $request->validate([
                'precos' => $usaItens ? ['required', 'array'] : ['prohibited'],
                'precos.*' => ['nullable', 'numeric', 'min:0', 'max:'.$unitarioMaximo],
                'valor' => $usaItens ? ['prohibited'] : ['required', 'numeric', 'min:0.01', 'max:'.$totalMaximo],
                'prazo_entrega_dias' => ['nullable', 'integer', 'min:1', 'max:365'],
                'validade_proposta' => ['nullable', 'date', 'after_or_equal:today', 'before_or_equal:'.$validadeMaxima],
                'observacoes' => ['nullable', 'string', 'max:2000'],
            ], [
                'precos.*.max' => 'Valor unitário acima do máximo aceito. Confira o preço informado.',
                'valor.max' => 'Valor acima do máximo aceito. Confira o valor informado.',
                'validade_proposta.before_or_equal' => 'A validade da proposta está distante demais. Confira a data.',
            ]);

            // Preços por item: SÓ os itens desta requisição (lida sob o tenant do link);
            // qualquer outra chave do payload é descartada.
            $linhas = [];
            $total = 0.0;
            if ($usaItens) {
                foreach ($itens as $item) {
                    $unitario = $dados['precos'][$item->id] ?? null;
                    if ($unitario === null || $unitario === '' || round((float) $unitario, 2) <= 0.0) {
                        continue;
                    }
                    $linhas[$item->id] = round((float) $unitario, 2);
                    $total += round($linhas[$item->id] * (float) $item->quantidade, 2);
                }

                if ($linhas === []) {
                    throw ValidationException::withMessages(['precos' => 'Informe o preço de ao menos um item.']);
                }
            } else {
                $total = (float) $dados['valor'];
            }
            $total = round($total, 2);

            // Teto do TOTAL (unitário × quantidade) e coerência com a requisição — ANTES de
            // consumir o link: erro de digitação volta como validação e o fornecedor corrige.
            $this->recusarTotalForaDoAceitavel($request, $link, $itens, $linhas, $total, $totalMaximo, (float) ($limites['multiplo_maximo_do_estimado'] ?? 100));

            $gravou = DB::transaction(function () use ($link, $cotacao, $linhas, $total, $dados) {
                // Uso único: sem esta linha a mesma URL regravaria a proposta.
                if (! $this->links->consumir($link)) {
                    return false;
                }

                $cotacao->itensCotacao()->delete();
                foreach ($linhas as $itemId => $unitario) {
                    $cotacao->itensCotacao()->create(['item_requisicao_id' => $itemId, 'valor_unitario' => $unitario]);
                }

                // Proposta vai para os campos de SUGESTÃO: o valor oficial continua sendo
                // confirmado pela compradora (GestaoCotacoes::confirmarSugestao).
                $cotacao->update([
                    'valor_respondido' => $total,
                    'prazo_respondido' => $dados['prazo_entrega_dias'] ?? null,
                    'validade_proposta' => $dados['validade_proposta'] ?? null,
                    'observacoes_fornecedor' => $dados['observacoes'] ?? null,
                    'resposta_recebida_em' => now(),
                ]);

                return true;
            });

            if (! $gravou) {
                return $this->invalido($request, 'link consumido em submissão concorrente');
            }

            $this->atividade->record('compras.cotacao_proposta_recebida', $cotacao, null, [
                'actor_type' => 'fornecedor',
                ...$this->origem($request),
                'new' => ['valor_respondido' => $total, 'prazo_respondido' => $dados['prazo_entrega_dias'] ?? null, 'itens' => count($linhas)],
                'metadata' => ['cotacao_link_id' => $link->id, 'fornecedor_id' => $link->fornecedor_id, 'canal' => 'link_assinado'],
            ]);

            $criador = $cotacao->criador;
            if ($criador?->email) {
                Mail::to($criador->email)->send(new RespostaCotacaoRecebida($cotacao->fresh(['fornecedor'])));
            }

            return response()->view('cotacao-publica.recebida', ['cotacao' => $cotacao]);
        });
    }

    /**
     * COMPRAS-8: teto absoluto do total e coerência com o valor ESTIMADO da requisição
     * (só dos itens cotados que têm estimativa). A mensagem é a MESMA nos dois casos e não
     * cita o estimado; e as recusas por coerência são CONTADAS — sem isso o fornecedor
     * descobriria o orçamento do comprador por tentativa e erro (cada recusa diz "acima de
     * N × estimado" e não consome o link). Estourado o número de tentativas, o link deixa
     * de aceitar proposta por 24h, com a mesma mensagem.
     *
     * COMPRAS-4r: a contagem é por ORIGEM (IP) com teto global por link. Quem floodou
     * tentativas incoerentes trava a si mesmo; o fornecedor legítimo, de outra origem,
     * continua conseguindo mandar a proposta coerente no mesmo dia.
     *
     * @param  Collection<int, ItemRequisicao>  $itens
     * @param  array<int, float>  $linhas
     *
     * @throws ValidationException
     */
    private function recusarTotalForaDoAceitavel(Request $request, CotacaoLink $link, Collection $itens, array $linhas, float $total, float $totalMaximo, float $multiplo): void
    {
        $recusa = fn () => ValidationException::withMessages([
            'precos' => 'O valor total da proposta está fora do intervalo aceito para esta cotação. Confira se informou o valor UNITÁRIO de cada item (e não o total da linha).',
        ]);

        if ($total > $totalMaximo) {
            throw $recusa();
        }

        $baldes = [
            'compras:proposta-publica:incoerente:'.$link->id.':origem:'.hash('sha256', (string) $request->ip()) => (int) config('compras.proposta_publica.tentativas_incoerentes_por_origem_dia', 3),
            'compras:proposta-publica:incoerente:'.$link->id => (int) config('compras.proposta_publica.tentativas_incoerentes_por_link_dia', 10),
        ];
        foreach ($baldes as $chave => $maximo) {
            if ((int) tenantCache()->get($chave, 0) >= $maximo) {
                throw $recusa();
            }
        }

        $estimado = 0.0;
        $cotadoComEstimativa = 0.0;
        foreach ($itens as $item) {
            $estimativa = (float) ($item->valor_unitario_estimado ?? 0);
            if ($estimativa <= 0 || ! isset($linhas[$item->id])) {
                continue;
            }
            $estimado += $estimativa * (float) $item->quantidade;
            $cotadoComEstimativa += $linhas[$item->id] * (float) $item->quantidade;
        }

        if ($multiplo > 0 && $estimado > 0 && $cotadoComEstimativa > $estimado * $multiplo) {
            foreach (array_keys($baldes) as $chave) {
                tenantCache()->add($chave, 0, now()->addDay());
                tenantCache()->increment($chave);
            }

            Log::warning('Proposta pública recusada por incoerência com a requisição.', [
                'cotacao_link_id' => $link->id,
                'ip' => $request->ip(),
            ]);

            throw $recusa();
        }
    }

    /**
     * @param  Closure(CotacaoLink, Cotacao): Response  $acao
     */
    private function comLinkValido(Request $request, string $token, Closure $acao): Response
    {
        // A assinatura é conferida AQUI (e não pelo middleware `signed`, que responde
        // 403 próprio): assinatura ruim e token ruim precisam ser indistinguíveis.
        if (! $request->hasValidSignature()) {
            return $this->invalido($request, 'assinatura inválida ou expirada');
        }

        $link = $this->links->resolver($token);
        if ($link === null) {
            return $this->invalido($request, 'token desconhecido');
        }

        return TenantContext::runFor((string) $link->tenant_id, function () use ($request, $link, $acao) {
            $cotacao = $this->links->cotacaoAberta($link);

            if ($cotacao === null) {
                return $this->invalido($request, 'link indisponível (usado, revogado, expirado ou cotação fechada)');
            }

            return $acao($link, $cotacao);
        });
    }

    /**
     * IP/UA do fornecedor EXPLÍCITOS na trilha: o ActivityRecorder só os captura sozinho
     * fora do console, e esta é a única evidência de quem usou um link sem login.
     *
     * @return array{ip_address: ?string, user_agent: ?string}
     */
    private function origem(Request $request): array
    {
        return ['ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 512) ?: null];
    }

    private function invalido(Request $request, string $motivo): Response
    {
        // O motivo fica só no log do servidor; o fornecedor vê sempre a mesma página.
        Log::warning('Link de cotação recusado.', ['motivo' => $motivo, 'ip' => $request->ip(), 'user_agent' => $request->userAgent()]);

        return response()->view('cotacao-publica.indisponivel', [], 404);
    }
}
