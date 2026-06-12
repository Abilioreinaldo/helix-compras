<?php

namespace App\Actions;

use App\Enums\NivelAlcada;
use App\Enums\Perfil;
use App\Enums\StatusAprovacao;
use App\Enums\StatusRequisicao;
use App\Mail\RequisicaoAguardandoAprovacao;
use App\Models\Aprovacao;
use App\Models\Requisicao;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class IniciarAprovacaoAction
{
    public function __construct(
        private readonly TransicionarStatusRequisicaoAction $transicionar
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(Requisicao $requisicao): void
    {
        $aprovadoresNotificar = [];

        DB::transaction(function () use ($requisicao, &$aprovadoresNotificar) {
            $requisicao->refresh();

            if ($requisicao->status !== StatusRequisicao::CotacaoConcluida) {
                throw ValidationException::withMessages([
                    'status' => 'A requisição precisa estar em cotação concluída para iniciar aprovação.',
                ]);
            }

            $etapas = $this->resolverEtapas($requisicao);

            foreach ($etapas as $etapa) {
                $aprovadores = $this->aprovadoresElegiveis($requisicao, $etapa['nivel_exigido']);
                if ($aprovadores->isEmpty()) {
                    $nivel = $etapa['nivel_exigido']->value;
                    throw ValidationException::withMessages([
                        'aprovadores' => "Não há aprovadores com nível '{$nivel}' cadastrados nesta unidade. Contate o administrador.",
                    ]);
                }
            }

            $cicloAtual = ($requisicao->ciclo_aprovacao ?? 1);

            foreach ($etapas as $etapa) {
                Aprovacao::create([
                    'requisicao_id' => $requisicao->id,
                    'etapa_alcada_id' => $etapa['etapa_alcada_id'],
                    'ciclo' => $cicloAtual,
                    'ordem' => $etapa['ordem'],
                    'nivel_exigido' => $etapa['nivel_exigido']->value,
                    'obrigatoria_emergencial' => $etapa['obrigatoria_emergencial'],
                    'status' => StatusAprovacao::Pendente->value,
                ]);
            }

            $requisicao->update(['aprovacao_iniciada_em' => now()]);

            $this->transicionar->execute($requisicao, StatusRequisicao::AguardandoAprovacao);

            $primeiraEtapa = $etapas[0];
            $aprovadoresNotificar = $this->aprovadoresElegiveis($requisicao, $primeiraEtapa['nivel_exigido'])->all();
        });

        foreach ($aprovadoresNotificar as $aprovador) {
            Mail::to($aprovador->email)->send(new RequisicaoAguardandoAprovacao($requisicao, $aprovador));
        }
    }

    /**
     * Resolve as etapas a materializar, injetando etapa de Diretor para emergencial se necessário.
     *
     * @return array<int, array{etapa_alcada_id: int|null, ordem: int, nivel_exigido: NivelAlcada, obrigatoria_emergencial: bool}>
     */
    private function resolverEtapas(Requisicao $requisicao): array
    {
        // FaixaAlcada é configuração global do admin; bypass necessário pois não pertence a uma unidade específica.
        $faixa = $requisicao->faixaAlcada()->withoutGlobalScopes()->with('etapas')->first();

        $etapas = $faixa?->etapas->map(fn ($e) => [
            'etapa_alcada_id' => $e->id,
            'ordem' => $e->ordem,
            'nivel_exigido' => $e->nivel_exigido,
            'obrigatoria_emergencial' => false,
        ])->toArray() ?? [];

        if ($requisicao->is_emergencial) {
            $temDiretor = collect($etapas)->contains(fn ($e) => $e['nivel_exigido'] === NivelAlcada::Diretor);
            if (! $temDiretor) {
                array_unshift($etapas, [
                    'etapa_alcada_id' => null,
                    'ordem' => 0,
                    'nivel_exigido' => NivelAlcada::Diretor,
                    'obrigatoria_emergencial' => true,
                ]);
                // Renumera as ordens das demais etapas para não colidir com 0
                foreach ($etapas as $i => &$e) {
                    if ($i > 0) {
                        $e['ordem'] = $i;
                    }
                }
                unset($e);
            }
        }

        if (empty($etapas)) {
            throw ValidationException::withMessages([
                'etapas' => 'A faixa de alçada não possui etapas de aprovação configuradas.',
            ]);
        }

        return $etapas;
    }

    private function aprovadoresElegiveis(Requisicao $requisicao, NivelAlcada $nivel): Collection
    {
        return User::whereIn('id', function ($q) use ($requisicao, $nivel) {
            $q->select('user_id')
                ->from('unidade_user')
                ->where('unidade_id', $requisicao->unidade_id)
                ->where('perfil', Perfil::Aprovador->value)
                ->where('nivel_alcada', $nivel->value);
        })->get();
    }
}
