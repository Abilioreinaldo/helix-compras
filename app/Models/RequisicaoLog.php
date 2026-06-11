<?php

namespace App\Models;

use App\Enums\StatusRequisicao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['requisicao_id', 'status_anterior', 'status_novo', 'user_id', 'observacao', 'automatico'])]
class RequisicaoLog extends Model
{
    /**
     * Log imutável: não possui updated_at.
     */
    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $table = 'requisicao_logs';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status_anterior' => StatusRequisicao::class,
            'status_novo' => StatusRequisicao::class,
            'automatico' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Requisição à qual este log pertence.
     */
    public function requisicao(): BelongsTo
    {
        return $this->belongsTo(Requisicao::class);
    }

    /**
     * Usuário que gerou esta transição (null para ações automáticas).
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
