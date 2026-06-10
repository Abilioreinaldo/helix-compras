<?php

namespace App\Observers;

use App\Models\Auditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Registra eventos de criação, atualização e exclusão como linhas de auditoria.
 * Falhas na gravação são logadas sem reverter a operação principal.
 */
class AuditoriaObserver
{
    public function created(Model $model): void
    {
        $this->registrar($model, 'criado');
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        $original = $model->getOriginal();

        foreach ($changes as $campo => $valorNovo) {
            if ($campo === 'updated_at') {
                continue;
            }

            $this->registrar($model, 'atualizado', $campo, $original[$campo] ?? null, $valorNovo);
        }
    }

    public function deleted(Model $model): void
    {
        $this->registrar($model, 'excluido');
    }

    /**
     * Grava uma linha de auditoria. Falhas são logadas sem propagar exceção.
     */
    private function registrar(
        Model $model,
        string $evento,
        ?string $campo = null,
        mixed $valorAnterior = null,
        mixed $valorNovo = null,
    ): void {
        try {
            Auditoria::create([
                'auditavel_type' => $model->getMorphClass(),
                'auditavel_id' => $model->getKey(),
                'campo' => $campo,
                'valor_anterior' => $valorAnterior !== null ? (string) $valorAnterior : null,
                'valor_novo' => $valorNovo !== null ? (string) $valorNovo : null,
                'evento' => $evento,
                'user_id' => Auth::id(),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('Falha ao registrar auditoria', [
                'model' => $model->getMorphClass(),
                'id' => $model->getKey(),
                'evento' => $evento,
                'erro' => $e->getMessage(),
            ]);
        }
    }
}
