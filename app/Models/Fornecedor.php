<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Database\Factories\FornecedorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'razao_social',
    'nome_fantasia',
    'cnpj',
    'categoria',
    'contato_nome',
    'contato_email',
    'contato_telefone',
    'homologado',
    'homologado_em',
    'homologado_por',
    'ativo',
    'observacoes',
])]
class Fornecedor extends ComprasModel
{
    /** @use HasFactory<FornecedorFactory> */
    use Auditavel, HasFactory, SoftDeletes;

    protected $table = 'fornecedores';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'homologado' => 'boolean',
            'ativo' => 'boolean',
            'homologado_em' => 'datetime',
        ];
    }

    /**
     * UsuÃ¡rio que realizou a homologaÃ§Ã£o do fornecedor.
     */
    public function quemHomologou(): BelongsTo
    {
        return $this->belongsTo(User::class, 'homologado_por');
    }
}
