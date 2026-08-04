<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Database\Factories\LoteEstoqueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'saldo_estoque_id',
    'numero_lote',
    'validade',
    'quantidade',
    'fundido_para_id',
    'fundido_em',
])]
class LoteEstoque extends ComprasModel
{
    /** @use HasFactory<LoteEstoqueFactory> */
    use Auditavel, HasFactory;

    protected $table = 'lotes_estoque';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'validade' => 'date',
            'fundido_em' => 'datetime',
        ];
    }

    /** Saldo agregado ao qual este lote pertence. */
    public function saldoEstoque(): BelongsTo
    {
        return $this->belongsTo(SaldoEstoque::class);
    }

    /** Lote destino ao qual este lote foi fundido (se for tombstone). */
    public function fundidoPara(): BelongsTo
    {
        return $this->belongsTo(LoteEstoque::class, 'fundido_para_id');
    }

    /** Lotes tombstone que foram fundidos neste lote. */
    public function fundidosDe(): HasMany
    {
        return $this->hasMany(LoteEstoque::class, 'fundido_para_id');
    }

    /**
     * Validade mÃ­nima/mÃ¡xima dos lotes VIVOS com validade, agrupada por saldo.
     * Lotes sem validade (NULL) sÃ£o ignorados; saldos sem lote datado nÃ£o aparecem
     * no mapa (a UI mostra "â€”"). MIN/MAX sobre date Ã© portÃ¡vel SQLite/MySQL.
     *
     * @param  iterable<int>  $saldoIds
     * @return Collection<int, object> saldo_estoque_id => {min, max} (datas 'Y-m-d')
     */
    public static function validadesVivasPorSaldo(iterable $saldoIds): Collection
    {
        $ids = collect($saldoIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return DB::table('lotes_estoque')
            ->whereIn('saldo_estoque_id', $ids->all())
            ->whereNull('fundido_para_id')
            ->whereNotNull('validade')
            ->groupBy('saldo_estoque_id')
            ->select('saldo_estoque_id', DB::raw('MIN(validade) as min'), DB::raw('MAX(validade) as max'))
            ->get()
            // Normaliza para 'Y-m-d' em PHP: o cast date grava datetime na coluna; trim sem
            // funÃ§Ã£o de data SQL (portÃ¡vel). MIN/MAX lexicogrÃ¡fico = cronolÃ³gico (mesmo formato).
            ->map(fn (object $row) => (object) [
                'saldo_estoque_id' => $row->saldo_estoque_id,
                'min' => Carbon::parse($row->min)->toDateString(),
                'max' => Carbon::parse($row->max)->toDateString(),
            ])
            ->keyBy('saldo_estoque_id');
    }

    /**
     * Saldos (dentre os informados) com ao menos um lote vivo VENCIDO (validade < hoje).
     * Reusa validadesVivasPorSaldo (min jÃ¡ normalizada); comparaÃ§Ã£o de data em PHP, sem
     * funÃ§Ã£o de data SQL. Vencido Ã© sÃ³ alerta â€” nÃ£o bloqueia a saÃ­da.
     *
     * @param  iterable<int>  $saldoIds
     * @return Collection<int, int> ids dos saldos com lote vencido
     */
    public static function saldosComLoteVencido(iterable $saldoIds): Collection
    {
        $hoje = Carbon::today()->toDateString();

        return static::validadesVivasPorSaldo($saldoIds)
            ->filter(fn (object $v) => $v->min < $hoje)
            ->keys();
    }
}
