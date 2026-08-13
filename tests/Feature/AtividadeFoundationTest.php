<?php

use App\Actions\GerarPagamentoDoPedidoAction;
use App\Actions\SubmeterRequisicaoAction;
use App\Enums\Perfil;
use App\Enums\StatusRequisicao;
use App\Models\CentroCusto;
use App\Models\FaixaAlcada;
use App\Models\PedidoCompra;
use App\Models\Requisicao;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * D10 (ponte dual) — as ações de ciclo de vida do Compras cumprem o ESCOPO da
 * suíte: publicam evento de domínio E gravam audit_log da foundation via
 * ActivityRecorder, sem substituir a trilha fina por campo do AuditoriaObserver.
 */
function d10Requisicao(): Requisicao
{
    $unidade = Unidade::factory()->create();
    $user = User::factory()->create();
    $user->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);

    FaixaAlcada::factory()->create([
        'valor_minimo' => 0, 'valor_maximo' => null, 'is_emergencial' => false, 'ativo' => true,
    ]);

    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);
    $req = Requisicao::create([
        'solicitante_id' => $user->id,
        'unidade_id' => $unidade->id,
        'centro_custo_id' => $centro->id,
        'status' => StatusRequisicao::Rascunho,
        'urgente' => false,
        'is_emergencial' => false,
    ]);
    $req->itens()->create([
        'descricao' => 'Item D10', 'quantidade' => 1,
        'unidade_medida' => 'un', 'valor_unitario_estimado' => 100.0,
    ]);

    test()->actingAs($user);

    return $req;
}

it('submeter requisição publica evento de domínio + audit_log da foundation', function () {
    $req = d10Requisicao();

    app(SubmeterRequisicaoAction::class)->execute($req);

    expect(DB::table('events')
        ->where('name', 'compras.requisicao_submetida')
        ->where('aggregate_id', (string) $req->id)
        ->exists())->toBeTrue()
        ->and(DB::table('audit_logs')
            ->where('action', 'compras.requisicao_submetida')
            ->where('resource_id', (string) $req->id)
            ->exists())->toBeTrue();
});

it('gerar pagamento do pedido publica evento de domínio + audit_log da foundation', function () {
    $pedido = PedidoCompra::factory()->create();
    $user = User::factory()->create();

    $pagamento = app(GerarPagamentoDoPedidoAction::class)->execute($pedido, $user);

    expect(DB::table('events')
        ->where('name', 'compras.pagamento_gerado')
        ->where('aggregate_id', (string) $pagamento->id)
        ->exists())->toBeTrue()
        ->and(DB::table('audit_logs')
            ->where('action', 'compras.pagamento_gerado')
            ->where('resource_id', (string) $pagamento->id)
            ->exists())->toBeTrue();
});

it('a trilha fina do AuditoriaObserver continua viva ao lado do dual-write', function () {
    $req = d10Requisicao();

    app(SubmeterRequisicaoAction::class)->execute($req);

    // O observer segue gravando a linha por campo na tabela auditorias.
    expect(DB::table('auditorias')
        ->where('auditavel_id', $req->id)
        ->where('evento', 'atualizado')
        ->exists())->toBeTrue();
});
