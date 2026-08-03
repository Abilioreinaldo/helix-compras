<?php

namespace App\Actions;

use App\Models\Banco;
use App\Models\Pagamento;
use App\Models\ReconciliacaoBancaria;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Processa um extrato bancário (CSV) e concilia com os pagamentos casando por
 * BANCO + referência + valor (com tolerância de centavos). Status por linha:
 * conciliado | divergente (ref bate, valor não) | ambiguo (mais de um) |
 * orfao (nenhum). Só 'conciliado' entra no total conciliado.
 * Formato por linha: numero_documento ; valor ; data_transacao ; descricao(opcional)
 */
class ProcessarReconciliacaoCsvAction
{
    private const MAX_LINHAS = 5000;

    /**
     * @throws ValidationException
     */
    public function execute(UploadedFile $arquivo, Banco $banco, User $usuario): ReconciliacaoBancaria
    {
        $conteudo = (string) file_get_contents($arquivo->getRealPath());
        if (trim($conteudo) === '') {
            throw ValidationException::withMessages(['arquivo' => 'O arquivo está vazio.']);
        }

        $hash = hash('sha256', $conteudo);
        if (ReconciliacaoBancaria::where('arquivo_hash', $hash)->exists()) {
            throw ValidationException::withMessages(['arquivo' => 'Este extrato já foi processado anteriormente.']);
        }

        $linhas = $this->parsear($conteudo);
        if ($linhas === []) {
            throw ValidationException::withMessages(['arquivo' => 'Nenhuma linha válida encontrada no extrato.']);
        }

        return DB::transaction(function () use ($linhas, $banco, $usuario, $hash) {
            $reconciliacao = ReconciliacaoBancaria::create([
                'banco_id' => $banco->id,
                'data_arquivo' => Carbon::today()->toDateString(),
                'arquivo_hash' => $hash,
                'criado_por' => $usuario->id,
                'total_linhas' => 0,
                'total_processado' => 0,
                'total_conciliado' => 0,
            ]);

            $totalProcessado = 0.0;
            $totalConciliado = 0.0;

            foreach ($linhas as $linha) {
                $totalProcessado += $linha['valor'];

                // Casa pelo BANCO selecionado + referência (não só a referência):
                // uma linha do banco X nunca concilia com pagamento do banco Y.
                $candidatos = Pagamento::whereNull('deleted_at')
                    ->where('banco_id', $banco->id)
                    ->where('referencia_banco', $linha['numero_documento'])
                    ->orderBy('id')                 // determinístico (nada de first() ao acaso)
                    ->get();

                $pagamento = null;

                if ($candidatos->count() > 1) {
                    // referência ambígua no banco — decisão humana, não escolhe um
                    $status = 'ambiguo';
                } elseif ($candidatos->count() === 1) {
                    $candidato = $candidatos->first();
                    $valorPago = (float) $candidato->valor_pago > 0
                        ? (float) $candidato->valor_pago
                        : (float) $candidato->valor_total;

                    if (abs($valorPago - $linha['valor']) <= 0.01) {
                        $pagamento = $candidato;
                        $status = 'conciliado';
                    } else {
                        // referência bate, valor não — não fecha como conciliado
                        $status = 'divergente';
                    }
                } else {
                    $status = 'orfao';
                }

                $reconciliacao->itens()->create([
                    'numero_documento' => $linha['numero_documento'],
                    'valor' => $linha['valor'],
                    'data_transacao' => $linha['data'],
                    'descricao' => $linha['descricao'],
                    'pagamento_id' => $pagamento?->id,
                    'status' => $status,
                ]);

                // Só o que realmente conciliou entra no total (antes somava o valor
                // do extrato mesmo quando o pagamento era de outro montante).
                if ($status === 'conciliado') {
                    $totalConciliado += $linha['valor'];
                }
            }

            $reconciliacao->update([
                'total_linhas' => count($linhas),
                'total_processado' => round($totalProcessado, 2),
                'total_conciliado' => round($totalConciliado, 2),
            ]);

            Log::info('Reconciliação bancária processada.', [
                'reconciliacao_id' => $reconciliacao->id,
                'banco_id' => $banco->id,
                'linhas' => count($linhas),
                'conciliados' => $reconciliacao->itens()->where('status', 'conciliado')->count(),
                'por' => $usuario->id,
            ]);

            return $reconciliacao->fresh('itens');
        });
    }

    /**
     * @return array<int, array{numero_documento: string, valor: float, data: ?string, descricao: ?string}>
     */
    private function parsear(string $conteudo): array
    {
        $linhas = preg_split('/\r\n|\r|\n/', trim($conteudo)) ?: [];
        $resultado = [];

        foreach ($linhas as $i => $linha) {
            if ($i >= self::MAX_LINHAS) {
                break;
            }
            $linha = trim($linha);
            if ($linha === '') {
                continue;
            }

            $delimitador = substr_count($linha, ';') >= substr_count($linha, ',') ? ';' : ',';
            $colunas = array_map(fn ($c) => trim((string) $c), str_getcsv($linha, $delimitador));

            if (count($colunas) < 2) {
                continue;
            }

            $valor = $this->numero($colunas[1]);
            if ($valor === null || $valor <= 0) {
                continue; // cabeçalho ou linha inválida
            }

            $resultado[] = [
                'numero_documento' => $colunas[0],
                'valor' => $valor,
                'data' => $this->data($colunas[2] ?? null),
                'descricao' => ($colunas[3] ?? '') !== '' ? $colunas[3] : null,
            ];
        }

        return $resultado;
    }

    private function numero(string $bruto): ?float
    {
        $bruto = trim(str_replace(['R$', ' '], '', $bruto));
        if ($bruto === '') {
            return null;
        }
        if (str_contains($bruto, ',')) {
            $bruto = str_replace('.', '', $bruto);
            $bruto = str_replace(',', '.', $bruto);
        }

        return is_numeric($bruto) ? (float) $bruto : null;
    }

    private function data(?string $bruto): ?string
    {
        $bruto = trim((string) $bruto);
        if ($bruto === '') {
            return null;
        }
        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y'] as $formato) {
            try {
                return Carbon::createFromFormat($formato, $bruto)->toDateString();
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }
}
