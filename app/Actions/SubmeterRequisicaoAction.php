<?php

namespace App\Actions;

use App\Enums\StatusRequisicao;
use App\Models\FaixaAlcada;
use App\Models\Obra;
use App\Models\Requisicao;
use App\Models\RequisicaoLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmeterRequisicaoAction
{
    /**
     * @return array{alerta_verba: bool, percentual_verba: float|null}
     *
     * @throws ValidationException
     */
    public function execute(Requisicao $requisicao): array
    {
        $alerta = ['alerta_verba' => false, 'percentual_verba' => null];

        return DB::transaction(function () use ($requisicao, $alerta) {
            $valorTotal = DB::table('requisicao_itens')
                ->where('requisicao_id', $requisicao->id)
                ->sum(DB::raw('COALESCE(quantidade * valor_unitario_estimado, 0)'));

            if ($requisicao->obra_id) {
                $obra = Obra::where('id', $requisicao->obra_id)->lockForUpdate()->first();

                $idsComprometidos = Requisicao::where('obra_id', $requisicao->obra_id)
                    ->whereNotIn('status', [
                        StatusRequisicao::Rascunho->value,
                        StatusRequisicao::Cancelada->value,
                        StatusRequisicao::Devolvida->value,
                    ])
                    ->where('id', '!=', $requisicao->id)
                    ->pluck('id');

                $verbaConsumida = DB::table('requisicao_itens')
                    ->whereIn('requisicao_id', $idsComprometidos)
                    ->sum(DB::raw('COALESCE(quantidade * valor_unitario_estimado, 0)'));

                $totalComNovo = $verbaConsumida + $valorTotal;
                $verba = (float) $obra->verba;
                $percentual = $verba > 0 ? ($totalComNovo / $verba) * 100 : 0;

                if ($percentual >= 100) {
                    throw ValidationException::withMessages([
                        'formulario' => "Verba da obra esgotada ({$percentual}%). Submissão bloqueada.",
                    ]);
                }

                $alerta['consumo_verba_no_submit'] = $totalComNovo;
                $alerta['escalada_verba'] = $percentual >= 80;
                $alerta['alerta_verba'] = $percentual >= 80;
                $alerta['percentual_verba'] = $percentual;
            }

            $faixa = $requisicao->is_emergencial
                ? FaixaAlcada::where('is_emergencial', true)->where('ativo', true)->whereNull('deleted_at')->first()
                : FaixaAlcada::whereNull('deleted_at')
                    ->where('ativo', true)
                    ->where('is_emergencial', false)
                    ->where('valor_minimo', '<=', $valorTotal)
                    ->where(fn ($q) => $q->whereNull('valor_maximo')->orWhere('valor_maximo', '>=', $valorTotal))
                    ->first();

            if (! $faixa) {
                throw ValidationException::withMessages([
                    'formulario' => 'Nenhuma alçada configurada para este valor. Contate o administrador.',
                ]);
            }

            $codigo = 'REQ-'.now()->year.'-'.str_pad((string) $requisicao->id, 6, '0', STR_PAD_LEFT);

            // Via expressa: todos os itens catalogados com preço homologado válido
            // do mesmo fornecedor → dispensa cotação ad-hoc (a aprovação por alçada
            // permanece). A elegibilidade é reavaliada no momento de atender.
            $expressa = $requisicao->avaliarViaExpressa() !== null;

            $requisicao->update([
                'codigo' => $codigo,
                'faixa_alcada_id' => $faixa->id,
                'expressa' => $expressa,
                'consumo_verba_no_submit' => $alerta['consumo_verba_no_submit'] ?? null,
                'escalada_verba' => $alerta['escalada_verba'] ?? false,
                'status' => StatusRequisicao::AguardandoTriagem,
                'submetida_em' => now(),
            ]);

            RequisicaoLog::create([
                'requisicao_id' => $requisicao->id,
                'status_anterior' => StatusRequisicao::Rascunho->value,
                'status_novo' => StatusRequisicao::AguardandoTriagem->value,
                'user_id' => auth()->id(),
                'observacao' => null,
                'automatico' => false,
            ]);

            return $alerta;
        });
    }
}
