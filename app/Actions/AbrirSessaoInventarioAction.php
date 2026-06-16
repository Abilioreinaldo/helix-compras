<?php

namespace App\Actions;

use App\Enums\Perfil;
use App\Enums\StatusInventario;
use App\Models\ItemInventario;
use App\Models\SaldoEstoque;
use App\Models\SessaoInventario;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AbrirSessaoInventarioAction
{
    /**
     * Abre uma nova sessão de inventário para a unidade, fazendo snapshot dos saldos.
     *
     * @throws ValidationException
     */
    public function execute(Unidade $unidade, ?string $deposito, User $abertoPor): SessaoInventario
    {
        // Valida perfil: Admin (global) ou Almoxarife da unidade
        $autorizado = $abertoPor->temPerfil(Perfil::Admin)
            || $abertoPor->unidades()
                ->withoutGlobalScopes()
                ->where('unidades.id', $unidade->id)
                ->wherePivot('perfil', Perfil::Almoxarife->value)
                ->exists();

        if (! $autorizado) {
            abort(403, 'Perfil insuficiente para abrir sessão de inventário.');
        }

        return DB::transaction(function () use ($unidade, $deposito, $abertoPor) {
            // Serializa aberturas concorrentes para a mesma unidade (evita TOCTOU): sem índice
            // único parcial portável SQLite/MySQL, o lock na linha da unidade garante exclusão
            // mútua entre dois almoxarifes abrindo sessão ao mesmo tempo.
            Unidade::withoutGlobalScopes()->where('id', $unidade->id)->lockForUpdate()->first();

            // Guarda: não pode haver sessão em_andamento para mesma unidade+depósito
            $sessaoAtiva = SessaoInventario::where('unidade_id', $unidade->id)
                ->where('status', StatusInventario::EmAndamento)
                ->when(
                    $deposito !== null,
                    fn ($q) => $q->where('deposito', $deposito),
                    fn ($q) => $q->whereNull('deposito'),
                )
                ->exists();

            if ($sessaoAtiva) {
                throw ValidationException::withMessages([
                    'sessao' => 'Já existe uma sessão de inventário em andamento para esta unidade e depósito.',
                ]);
            }

            $sessao = SessaoInventario::create([
                'unidade_id' => $unidade->id,
                'deposito' => $deposito,
                'aberta_por' => $abertoPor->id,
                'status' => StatusInventario::EmAndamento,
            ]);

            // Snapshot: saldos ativos da unidade (excluindo tombstones de fusão)
            $query = SaldoEstoque::where('unidade_id', $unidade->id)
                ->whereNull('fundido_para_id');

            if ($deposito !== null) {
                $query->where('deposito', $deposito);
            }

            $saldos = $query->get();

            foreach ($saldos as $saldo) {
                ItemInventario::create([
                    'sessao_inventario_id' => $sessao->id,
                    'saldo_estoque_id' => $saldo->id,
                    'quantidade_sistema' => (float) $saldo->quantidade,
                    'quantidade_contada' => null,
                ]);
            }

            return $sessao->load('itens');
        });
    }
}
