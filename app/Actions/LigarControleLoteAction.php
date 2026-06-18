<?php

namespace App\Actions;

use App\Enums\Perfil;
use App\Models\CatalogoItem;
use App\Models\LoteEstoque;
use App\Models\SaldoEstoque;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LigarControleLoteAction
{
    /**
     * Liga ou desliga o controle de lote/validade de um item de catálogo.
     *
     * Restrito a Admin. Para preservar a integridade do estoque:
     * - Ligar exige que não haja saldo legado (quantidade > 0 sem nenhum lote vivo),
     *   pois esse saldo não teria lote de origem rastreável.
     * - Desligar exige que não haja lote vivo com quantidade > 0, pois desligar
     *   descartaria o rastreio de validade de estoque ainda existente.
     *
     * @throws ValidationException quando o usuário não é Admin ou há saldo/lote impeditivo.
     */
    public function execute(CatalogoItem $item, bool $controlar, User $por): CatalogoItem
    {
        $this->verificarAutorizacao($por);

        if ($item->controla_lote === $controlar) {
            return $item;
        }

        if ($controlar) {
            $this->verificarSemSaldoLegado($item);
        } else {
            $this->verificarSemLotesVivos($item);
        }

        return DB::transaction(function () use ($item, $controlar) {
            $item->controla_lote = $controlar;
            $item->save();

            return $item;
        });
    }

    // ─── Helpers privados ─────────────────────────────────────────────────────

    private function verificarAutorizacao(User $usuario): void
    {
        if (! $usuario->temPerfil(Perfil::Admin)) {
            throw ValidationException::withMessages([
                'autorizado' => 'Operação não permitida: apenas Admin pode alterar o controle de lote.',
            ]);
        }
    }

    /**
     * Bloqueia quando existe saldo legado: quantidade > 0 sem nenhum lote vivo.
     */
    private function verificarSemSaldoLegado(CatalogoItem $item): void
    {
        $temSaldoLegado = SaldoEstoque::query()
            ->where('item_catalogo_id', $item->id)
            ->whereNull('fundido_para_id')
            ->where('quantidade', '>', 0)
            ->whereDoesntHave('lotesVivos')
            ->exists();

        if ($temSaldoLegado) {
            throw ValidationException::withMessages([
                'controla_lote' => 'Não é possível ligar o controle de lote: há saldo legado sem lotes. Zere ou converta o saldo antes.',
            ]);
        }
    }

    /**
     * Bloqueia quando existe lote vivo com quantidade > 0 para o item.
     */
    private function verificarSemLotesVivos(CatalogoItem $item): void
    {
        $temLote = LoteEstoque::query()
            ->whereNull('fundido_para_id')
            ->where('quantidade', '>', 0)
            ->whereHas('saldoEstoque', function ($query) use ($item) {
                $query->where('item_catalogo_id', $item->id);
            })
            ->exists();

        if ($temLote) {
            throw ValidationException::withMessages([
                'controla_lote' => 'Não é possível desligar o controle de lote: há lotes com saldo. Zere os lotes antes.',
            ]);
        }
    }
}
