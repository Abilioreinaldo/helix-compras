<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Link assinado de resposta de cotação (decisão 11). Emitido/revogado/consumido
 * SÓ pelo CotacaoLinkService — nenhum atributo é mass-assignable: `token_hash`,
 * `referencia`, prazos e ids são estado de servidor.
 *
 * @property string $tenant_id
 * @property int $cotacao_id
 * @property int $fornecedor_id
 * @property string $token_hash
 * @property string $referencia
 * @property Carbon $expires_at
 * @property Carbon|null $submetido_em
 * @property Carbon|null $revogado_em
 */
class CotacaoLink extends ComprasModel
{
    protected $table = 'cotacao_links';

    /** @var list<string> */
    protected $guarded = ['*'];

    /** O hash nunca sai em array/JSON (auditoria, eventos, respostas). */
    protected $hidden = ['token_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'submetido_em' => 'datetime',
            'revogado_em' => 'datetime',
        ];
    }

    /** Ainda aceita visualização/submissão: não submetido, não revogado, dentro do prazo. */
    public function utilizavel(): bool
    {
        return $this->submetido_em === null
            && $this->revogado_em === null
            && $this->expires_at->isFuture();
    }

    public function cotacao(): BelongsTo
    {
        return $this->belongsTo(Cotacao::class);
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }
}
