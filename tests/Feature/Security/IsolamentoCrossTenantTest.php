<?php

use App\Actions\EmitirPedidoCompraAction;
use App\Actions\SubmeterRequisicaoAction;
use App\Enums\NivelAlcada;
use App\Enums\Perfil;
use App\Enums\StatusAprovacao;
use App\Enums\StatusPedidoCompra;
use App\Enums\StatusRequisicao;
use App\Livewire\Almoxarife\SaldosEstoque;
use App\Livewire\Aprovacoes\PainelAprovacao;
use App\Livewire\Relatorios\ComparativoUnidades;
use App\Livewire\Relatorios\GastosCentroCusto;
use App\Livewire\Requisicoes\FormularioRequisicao;
use App\Livewire\Requisicoes\ListaRequisicoes;
use App\Models\Aprovacao;
use App\Models\CatalogoItem;
use App\Models\CentroCusto;
use App\Models\Cotacao;
use App\Models\FaixaAlcada;
use App\Models\Fornecedor;
use App\Models\ItemRequisicao;
use App\Models\PedidoCompra;
use App\Models\PedidoLojaRecebido;
use App\Models\Requisicao;
use App\Models\SaldoEstoque;
use App\Models\Unidade;
use App\Models\User;
use App\Subscribers\IngerirPedidoLoja;
use Helix\Foundation\Models\Platform\Event\DomainEvent;
use Helix\Foundation\Models\Platform\Identity\Tenant;
use Helix\Foundation\Models\Platform\Identity\TenantFeature;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Isolamento cross-tenant do Compras (auditoria multitenant 2026-09-15, nota 2/10).
 *
 * Regra máxima: nenhum tenant enxerga, altera, afeta, degrada ou assume recursos de
 * outro tenant. O tenant A é a vítima (dados semeados); B é o atacante — testado
 * com os dois papéis mais poderosos do módulo (compradora sênior E admin do tenant).
 *
 * Padrão multi-tenant: opt-out do contexto canônico global (TenantContext::forget)
 * e semeadura explícita por tenant via TenantContext::runFor (factories carimbam).
 */
beforeEach(function () {
    Mail::fake();

    $this->tenantA = Tenant::create(['slug' => 'alfa', 'name' => 'Alfa', 'status' => 'active']);
    $this->tenantB = Tenant::create(['slug' => 'bravo', 'name' => 'Bravo', 'status' => 'active']);

    foreach ([$this->tenantA, $this->tenantB] as $tenant) {
        TenantFeature::firstOrCreate(['tenant_id' => $tenant->id, 'feature' => 'compras'], ['enabled' => true]);
    }

    // ── Tenant A (vítima) ────────────────────────────────────────────────────
    TenantContext::runFor($this->tenantA->id, function () {
        $this->unidadeA = Unidade::factory()->create(['tenant_id' => $this->tenantA->id, 'nome' => 'Obra Alfa Norte']);
        $this->centroA = CentroCusto::factory()->create(['unidade_id' => $this->unidadeA->id, 'ativo' => true]);
        $this->itemA = CatalogoItem::factory()->create(['descricao' => 'Cimento Alfa CP-II', 'ativo' => true]);
        $this->fornecedorA = Fornecedor::factory()->homologado()->create();

        $this->solicitanteA = User::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Solicitante Alfa']);
        $this->solicitanteA->unidades()->attach($this->unidadeA->id, ['perfil' => Perfil::Solicitante->value]);

        $this->reqA = Requisicao::factory()->create([
            'unidade_id' => $this->unidadeA->id,
            'centro_custo_id' => $this->centroA->id,
            'solicitante_id' => $this->solicitanteA->id,
            'status' => StatusRequisicao::AguardandoAprovacao,
            'ciclo_aprovacao' => 1,
            'codigo' => 'REQ-ALFA-000001',
        ]);
        ItemRequisicao::factory()->create(['requisicao_id' => $this->reqA->id, 'avulso' => true, 'item_catalogo_id' => null]);
        Aprovacao::create([
            'requisicao_id' => $this->reqA->id, 'etapa_alcada_id' => null, 'ciclo' => 1, 'ordem' => 1,
            'nivel_exigido' => NivelAlcada::Gestor->value, 'obrigatoria_emergencial' => false,
            'status' => StatusAprovacao::Pendente->value,
        ]);

        $this->pedidoA = PedidoCompra::factory()->emitido()->create([
            'unidade_id' => $this->unidadeA->id,
            'fornecedor_id' => $this->fornecedorA->id,
            'criado_por' => $this->solicitanteA->id,
        ]);

        $this->saldoA = SaldoEstoque::factory()->create(['unidade_id' => $this->unidadeA->id, 'quantidade' => 100]);
    });

    // ── Tenant B (atacante) ──────────────────────────────────────────────────
    TenantContext::runFor($this->tenantB->id, function () {
        $this->unidadeB = Unidade::factory()->create(['tenant_id' => $this->tenantB->id, 'nome' => 'Posto Bravo Centro']);
        $this->centroB = CentroCusto::factory()->create(['unidade_id' => $this->unidadeB->id, 'ativo' => true]);

        $this->compradoraB = User::factory()->compradora()->create(['tenant_id' => $this->tenantB->id, 'name' => 'Compradora Bravo']);
        $this->adminB = User::factory()->admin()->create(['tenant_id' => $this->tenantB->id, 'name' => 'Admin Bravo']);

        $this->aprovadorB = User::factory()->create(['tenant_id' => $this->tenantB->id]);
        $this->aprovadorB->unidades()->attach($this->unidadeB->id, ['perfil' => Perfil::Aprovador->value, 'nivel_alcada' => NivelAlcada::Gestor->value]);

        $this->almoxarifeB = User::factory()->create(['tenant_id' => $this->tenantB->id]);
        $this->almoxarifeB->unidades()->attach($this->unidadeB->id, ['perfil' => Perfil::Almoxarife->value]);

        $this->solicitanteB = User::factory()->create(['tenant_id' => $this->tenantB->id]);
        $this->solicitanteB->unidades()->attach($this->unidadeB->id, ['perfil' => Perfil::Solicitante->value]);

        $this->reqB = Requisicao::factory()->create([
            'unidade_id' => $this->unidadeB->id,
            'centro_custo_id' => $this->centroB->id,
            'solicitante_id' => $this->solicitanteB->id,
            'status' => StatusRequisicao::AguardandoAprovacao,
            'ciclo_aprovacao' => 1,
            'codigo' => 'REQ-BRAVO-000001',
        ]);
        ItemRequisicao::factory()->create(['requisicao_id' => $this->reqB->id, 'avulso' => true, 'item_catalogo_id' => null]);
        Aprovacao::create([
            'requisicao_id' => $this->reqB->id, 'etapa_alcada_id' => null, 'ciclo' => 1, 'ordem' => 1,
            'nivel_exigido' => NivelAlcada::Gestor->value, 'obrigatoria_emergencial' => false,
            'status' => StatusAprovacao::Pendente->value,
        ]);

        $this->saldoB = SaldoEstoque::factory()->create(['unidade_id' => $this->unidadeB->id, 'quantidade' => 50, 'item_catalogo_id' => null]);
    });

    // Multi-tenant: daqui em diante o tenant resolve pelo usuário autenticado.
    TenantContext::forget();
});

/**
 * Emite um pedido de compra de R$ $valor no tenant do $comprador (fluxo real, com a
 * numeração da action). Devolve o pedido emitido.
 */
function ic_emitirPedido(User $comprador, Unidade $unidade, CentroCusto $centro, float $valor): PedidoCompra
{
    test()->actingAs($comprador);

    $fornecedor = Fornecedor::factory()->homologado()->create();

    $requisicao = Requisicao::factory()->create([
        'unidade_id' => $unidade->id,
        'centro_custo_id' => $centro->id,
        'solicitante_id' => $comprador->id,
        'status' => StatusRequisicao::Aprovada,
        'ciclo_aprovacao' => 1,
        'codigo' => 'REQ-PC-'.fake()->unique()->numerify('######'),
    ]);
    $item = ItemRequisicao::factory()->create([
        'requisicao_id' => $requisicao->id, 'avulso' => true, 'item_catalogo_id' => null,
        'quantidade' => 1, 'valor_unitario_estimado' => $valor,
    ]);
    $cotacao = Cotacao::create([
        'requisicao_id' => $requisicao->id, 'fornecedor_id' => $fornecedor->id, 'valor' => $valor,
        'vencedora' => true, 'criada_por' => $comprador->id, 'vencedora_definida_em' => now(),
    ]);

    $pedido = PedidoCompra::create([
        'status' => StatusPedidoCompra::Rascunho,
        'fornecedor_id' => $fornecedor->id,
        'unidade_id' => $unidade->id,
        'criado_por' => $comprador->id,
    ]);
    $pedido->itens()->create([
        'requisicao_id' => $requisicao->id, 'item_requisicao_id' => $item->id, 'cotacao_id' => $cotacao->id,
        'descricao' => 'Item', 'quantidade' => 1, 'unidade_medida' => 'un',
        'valor_unitario' => $valor, 'valor_total' => $valor, 'destino' => 'Almoxarifado',
    ]);

    return app(EmitirPedidoCompraAction::class)->execute($pedido, $comprador);
}

// ─── Leitura direta por id (IDOR cross-tenant) ───────────────────────────────

it('compradora e admin de B recebem 404 no PDF do pedido de A', function () {
    $this->actingAs($this->compradoraB)->get(route('compradora.pedidos.pdf', $this->pedidoA->id))->assertNotFound();
    $this->actingAs($this->adminB)->get(route('compradora.pedidos.pdf', $this->pedidoA->id))->assertNotFound();
});

it('compradora e admin de B recebem 404 no detalhe e na edição da requisição de A', function () {
    foreach ([$this->compradoraB, $this->adminB] as $atacante) {
        $this->actingAs($atacante)->get(route('requisicoes.detalhe', $this->reqA->id))->assertNotFound();
        $this->actingAs($atacante)->get(route('requisicoes.editar', $this->reqA->id))->assertNotFound();
    }
});

it('painel de aprovação com id de A: aprovador de B recebe 404', function () {
    $this->actingAs($this->aprovadorB)->get(route('aprovacoes.painel', $this->reqA->id))->assertNotFound();
});

it('painel de aprovação: o id é #[Locked] — o cliente não reaponta para a requisição de A', function () {
    $painel = Livewire::actingAs($this->aprovadorB)->test(PainelAprovacao::class, ['id' => $this->reqB->id]);

    expect(fn () => $painel->set('id', $this->reqA->id))->toThrow(CannotUpdateLockedPropertyException::class);

    // e a etapa de A continua pendente — nada foi decidido do outro lado
    expect(DB::table('aprovacoes')->where('requisicao_id', $this->reqA->id)->value('status'))
        ->toBe(StatusAprovacao::Pendente->value);
});

it('as policies negam requisição de A para admin, compradora e aprovador de B', function () {
    $this->actingAs($this->adminB);
    expect($this->adminB->can('view', $this->reqA))->toBeFalse()
        ->and($this->adminB->can('update', $this->reqA))->toBeFalse();

    $this->actingAs($this->compradoraB);
    expect($this->compradoraB->can('view', $this->reqA))->toBeFalse();

    // Mesmo com um vínculo forjado na unidade de A, o aprovador de B não acessa nem decide.
    TenantContext::runFor($this->tenantA->id, fn () => $this->aprovadorB->unidades()->attach(
        $this->unidadeA->id, ['perfil' => Perfil::Aprovador->value, 'nivel_alcada' => NivelAlcada::Gestor->value],
    ));

    $this->actingAs($this->aprovadorB);
    expect($this->aprovadorB->can('aprovacao.acessar', $this->reqA))->toBeFalse()
        ->and($this->aprovadorB->can('aprovacao.decidir', $this->reqA))->toBeFalse();
});

// ─── Listagens ───────────────────────────────────────────────────────────────

it('a lista de requisições de B não contém linhas de A', function () {
    Livewire::actingAs($this->compradoraB)
        ->test(ListaRequisicoes::class)
        ->assertSee('REQ-BRAVO-000001')
        ->assertDontSee('REQ-ALFA-000001');

    Livewire::actingAs($this->adminB)
        ->test(ListaRequisicoes::class)
        ->assertDontSee('REQ-ALFA-000001');
});

// ─── Escrita apontando para recursos de A ────────────────────────────────────

it('transferir estoque de B para unidade de A falha na validação e não move saldo', function () {
    Livewire::actingAs($this->almoxarifeB)
        ->test(SaldosEstoque::class)
        ->call('abrirTransferencia', $this->saldoB->id)
        ->set('transferDestinoId', (string) $this->unidadeA->id)
        ->set('transferQuantidade', '5')
        ->call('confirmarTransferencia')
        ->assertHasErrors(['transferDestinoId']);

    expect((float) SaldoEstoque::withoutTenantScope()->find($this->saldoB->id)->quantidade)->toBe(50.0)
        ->and(SaldoEstoque::withoutTenantScope()->where('unidade_id', $this->unidadeA->id)->count())->toBe(1);
});

it('formulário de requisição de B rejeita unidade, centro de custo e item de catálogo de A', function () {
    Livewire::actingAs($this->solicitanteB)
        ->test(FormularioRequisicao::class)
        ->set('unidadeId', $this->unidadeA->id)
        ->set('centroCustoId', $this->centroA->id)
        ->set('itens', [[
            'descricao' => 'Cimento Alfa CP-II', 'quantidade' => '1', 'unidade_medida' => 'un',
            'valor_unitario_estimado' => '10', 'item_catalogo_id' => $this->itemA->id, 'avulso' => false,
        ]])
        ->call('salvar')
        ->assertHasErrors(['unidadeId', 'centroCustoId', 'itens.0.item_catalogo_id']);

    // Nada foi gravado apontando para a unidade de A (só a reqA semeada) — leitura crua, sem scopes.
    expect(DB::table('requisicoes')->where('unidade_id', $this->unidadeA->id)->count())->toBe(1);
});

it('o vínculo unidade_user carimba o tenant da unidade e recusa vínculo cruzado', function () {
    $linha = DB::table('unidade_user')
        ->where('user_id', $this->almoxarifeB->id)
        ->where('unidade_id', $this->unidadeB->id)
        ->first();

    expect($linha->tenant_id)->toBe($this->tenantB->id);

    // Declarar o tenant errado no attach é recusado (fonte de verdade é a unidade).
    expect(fn () => $this->almoxarifeB->unidades()->attach($this->unidadeA->id, [
        'tenant_id' => $this->tenantB->id, 'perfil' => Perfil::Almoxarife->value,
    ]))->toThrow(InvalidArgumentException::class);
});

// ─── Numeração ───────────────────────────────────────────────────────────────

it('a numeração de pedidos de compra é independente por tenant', function () {
    $compradoraA = TenantContext::runFor($this->tenantA->id, fn () => User::factory()->compradora()->create(['tenant_id' => $this->tenantA->id]));

    $pedidoA = ic_emitirPedido($compradoraA, $this->unidadeA, $this->centroA, 100.0);
    $pedidoB = ic_emitirPedido($this->compradoraB, $this->unidadeB, $this->centroB, 200.0);

    $esperado = sprintf('PC-%04d-0001', now()->year);

    expect($pedidoA->numero)->toBe($esperado)
        ->and($pedidoB->numero)->toBe($esperado)
        ->and($pedidoA->tenant_id)->toBe($this->tenantA->id)
        ->and($pedidoB->tenant_id)->toBe($this->tenantB->id);

    // Um segundo pedido em B continua a sequência de B, não a de A.
    expect(ic_emitirPedido($this->compradoraB, $this->unidadeB, $this->centroB, 300.0)->numero)
        ->toBe(sprintf('PC-%04d-0002', now()->year));
});

it('o código da requisição é sequência anual por tenant (não deriva do id global)', function () {
    $submeter = function (User $solicitante, Unidade $unidade, CentroCusto $centro): string {
        $this->actingAs($solicitante);
        FaixaAlcada::factory()->create(['valor_minimo' => 0, 'valor_maximo' => null, 'is_emergencial' => false, 'ativo' => true]);

        $req = Requisicao::factory()->create([
            'unidade_id' => $unidade->id, 'centro_custo_id' => $centro->id, 'solicitante_id' => $solicitante->id,
            'status' => StatusRequisicao::Rascunho, 'codigo' => null,
        ]);
        ItemRequisicao::factory()->create(['requisicao_id' => $req->id, 'avulso' => true, 'item_catalogo_id' => null, 'quantidade' => 1, 'valor_unitario_estimado' => 10]);

        app(SubmeterRequisicaoAction::class)->execute($req);

        return $req->fresh()->codigo;
    };

    $codigoA = $submeter($this->solicitanteA, $this->unidadeA, $this->centroA);
    $codigoB = $submeter($this->solicitanteB, $this->unidadeB, $this->centroB);

    $esperado = sprintf('REQ-%04d-000001', now()->year);

    expect($codigoA)->toBe($esperado)->and($codigoB)->toBe($esperado);
});

// ─── Inbound (transporte inter-app) ──────────────────────────────────────────

it('IngerirPedidoLoja não grava para tenant sem a feature compras nem para evento sem tenant', function () {
    $tenantSemCompras = Tenant::create(['slug' => 'charlie', 'name' => 'Charlie', 'status' => 'active']);
    $payload = ['version' => 1, 'request_code' => 'PED-X-0001', 'store_code' => 'LOJ-1', 'lines' => [], 'line_count' => 0, 'total_estimated_cents' => 0];

    $ingerir = app(IngerirPedidoLoja::class);

    // v0.2.0: tenant_id saiu do $fillable do DomainEvent — evento montado à mão
    // (não persistido) recebe a coluna por forceFill.
    $evento = fn (?string $tenantId) => (new DomainEvent([
        'name' => IngerirPedidoLoja::EVENTO, 'payload' => $payload,
    ]))->forceFill(['tenant_id' => $tenantId]);

    TenantContext::runFor($tenantSemCompras->id, fn () => $ingerir->handle($evento($tenantSemCompras->id)));
    $ingerir->handle($evento(null));

    expect(PedidoLojaRecebido::withoutTenantScope()->count())->toBe(0);

    // Controle positivo: tenant com a feature grava, carimbado com o tenant do evento.
    TenantContext::runFor($this->tenantA->id, fn () => $ingerir->handle($evento($this->tenantA->id)));

    expect(PedidoLojaRecebido::withoutTenantScope()->count())->toBe(1)
        ->and(PedidoLojaRecebido::withoutTenantScope()->first()->tenant_id)->toBe($this->tenantA->id);
});

// ─── Relatórios (query builder, sem BelongsToTenant) ─────────────────────────

it('relatórios de B não somam gastos de A', function () {
    $compradoraA = TenantContext::runFor($this->tenantA->id, fn () => User::factory()->compradora()->create(['tenant_id' => $this->tenantA->id]));

    ic_emitirPedido($compradoraA, $this->unidadeA, $this->centroA, 1000.0);
    ic_emitirPedido($this->compradoraB, $this->unidadeB, $this->centroB, 40.0);

    $gastos = Livewire::actingAs($this->compradoraB)->test(GastosCentroCusto::class);
    expect((float) $gastos->viewData('totalGeral'))->toBe(40.0);

    $comparativo = Livewire::actingAs($this->compradoraB)->test(ComparativoUnidades::class);
    expect((float) $comparativo->viewData('totalGeral'))->toBe(40.0)
        ->and($comparativo->viewData('resultados')->pluck('unidade_nome')->all())->toBe(['Posto Bravo Centro']);

    // ... e o admin de B tampouco.
    expect((float) Livewire::actingAs($this->adminB)->test(GastosCentroCusto::class)->viewData('totalGeral'))->toBe(40.0);
});
