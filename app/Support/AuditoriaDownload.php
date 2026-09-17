<?php

namespace App\Support;

use Helix\Foundation\Services\Platform\Support\ActivityRecorder;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Trilha de auditoria de download/exportação sensível (COMPRAS-2, 4ª auditoria).
 *
 * Padrão Helix: "Downloads/exports sensíveis auditados". Todo controller/ação que
 * entrega arquivo (anexo de cotação, PDF de pedido, CSV financeiro) chama
 * `registrar()` DEPOIS da autorização e IMEDIATAMENTE antes de devolver a resposta —
 * download negado não vira "baixado" na trilha.
 *
 * O dual-write (evento + audit com actor, tenant, IP/UA e correlation_id) é do
 * `ActivityRecorder` da fundação; aqui só se padroniza ator, tenant e payload mínimo
 * (o evento não carrega os atributos do registro — só o que saiu e quem tirou).
 */
class AuditoriaDownload
{
    public function __construct(private readonly ActivityRecorder $recorder) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function registrar(string $acao, Model $registro, array $metadata = []): void
    {
        $this->recorder->record($acao, $registro, TenantContext::requireId('auditoria de download'), [
            'actor_id' => auth()->id(),
            'payload' => ['id' => $registro->getKey()] + $metadata,
            'metadata' => $metadata,
        ]);
    }
}
