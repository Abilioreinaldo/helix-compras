<?php

use App\Actions\AbrirSessaoInventarioAction;
use App\Actions\CalcularRateioMensalAction;
use App\Actions\CancelarPedidoCompraAction;
use App\Actions\EmitirPedidoCompraAction;
use App\Enums\Perfil;
use App\Enums\StatusPedidoCompra;
use App\Enums\StatusRequisicao;
use App\Enums\TipoMovimentacao;
use App\Livewire\Almoxarife\RegistroRecebimento;
use App\Livewire\Dashboard;
use App\Livewire\Relatorios\ConsumoUnidade;
use App\Models\CatalogoItem;
use App\Models\CentroCusto;
use App\Models\Cotacao;
use App\Models\EstoqueMinimo;
use App\Models\Fornecedor;
use App\Models\ItemPedidoCompra;
use App\Models\ItemRequisicao;
use App\Models\MovimentacaoEstoque;
use App\Models\PedidoCompra;
use App\Models\Requisicao;
use App\Models\SaldoEstoque;
use App\Models\Unidade;
use App\Models\User;
use Helix\Foundation\Exceptions\MissingTenantContextException;
use Helix\Foundation\Models\Platform\Identity\Tenant;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/*
| 3ª auditoria adversarial — achado 9 (MÉDIO) do Compras: JOINs sem tenant na
| tabela SECUNDÁRIA.
|
| O recorte de tenant estava só na tabela-base do query builder cru; a tabela do
| join casava só por id. Como as FKs das tabelas de negócio ainda NÃO são compostas
| com tenant_id (decisão pendente — ver relatório), uma linha com FK cruzada (bug,
| import, restore parcial, ou escrita de um service sem guarda) faz o dado do outro
| tenant entrar na conta ou na tela. Padrão de cada teste: tenant A (contexto
| canônico) é a vítima; uma linha de B "envenenada" aponta para ids de A.
*/

beforeEach(function () {
    Mail::fake();

    $this->tenantA = TenantContext::id();
    $this->tenantB = Tenant::create(['slug' => 'bravo-joins', 'name' => 'Bravo', 'status' => 'active'])->id;
});

/** Cria no tenant B (contexto explícito) e devolve o resultado. */
function noTenantB(string $tenantB, callable $fn): mixed
{
    return TenantContext::runFor($tenantB, $fn);
}

// ─── EstoqueMinimo (3 joins) ─────────────────────────────────────────────────

it('itens a repor e itens em alerta não trazem o catálogo de outro tenant', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();
    $itemA = CatalogoItem::factory()->create(['descricao' => 'Item Alfa']);
    $itemB = noTenantB($this->tenantB, fn () => CatalogoItem::factory()->create(['descricao' => 'ITEM-SECRETO-BRAVO']));

    $minimo = EstoqueMinimo::create(['unidade_id' => $unidade->id, 'item_catalogo_id' => $itemA->id, 'quantidade_minima' => 10]);
    // FK cruzada: o mínimo de A aponta para o item de catálogo de B.
    DB::table('estoque_minimos')->where('id', $minimo->id)->update(['item_catalogo_id' => $itemB->id]);

    expect(EstoqueMinimo::itensAReporPara($admin)->pluck('item_descricao')->all())->not->toContain('ITEM-SECRETO-BRAVO')
        ->and(EstoqueMinimo::itemCatalogoIdsEmAlerta([$unidade->id]))->toBe([]);
});

it('a posição de estoque não usa o estoque mínimo de outro tenant', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();
    $item = CatalogoItem::factory()->create();

    SaldoEstoque::create([
        'unidade_id' => $unidade->id, 'deposito' => 'Central', 'descricao_item' => $item->descricao,
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao($item->descricao), 'unidade_medida' => 'un',
        'quantidade' => 1, 'custo_medio_ponderado' => 10, 'valor_total' => 10, 'item_catalogo_id' => $item->id,
    ]);

    // Mínimo DE B pendurado (FK cruzada) na unidade e no item de A.
    noTenantB($this->tenantB, function () use ($unidade, $item) {
        $minB = EstoqueMinimo::create([
            'unidade_id' => Unidade::factory()->create()->id,
            'item_catalogo_id' => CatalogoItem::factory()->create()->id,
            'quantidade_minima' => 999,
        ]);
        DB::table('estoque_minimos')->where('id', $minB->id)->update(['unidade_id' => $unidade->id, 'item_catalogo_id' => $item->id]);
    });

    $linha = EstoqueMinimo::posicaoEstoquePara($admin)->sole();

    expect($linha->quantidade_minima)->toBeNull()
        ->and($linha->em_alerta)->toBeFalse();
});

// ─── Rateio mensal ───────────────────────────────────────────────────────────

it('o rateio não soma consumo de saldo/unidade de outro tenant', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();

    $novoSaldo = fn (int $unidadeId, string $desc) => SaldoEstoque::create([
        'unidade_id' => $unidadeId, 'deposito' => 'D', 'descricao_item' => $desc,
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao($desc), 'unidade_medida' => 'un',
        'quantidade' => 10, 'custo_medio_ponderado' => 10, 'valor_total' => 100,
    ]);

    $saida = function (int $saldoId, float $valor) use ($admin) {
        $mov = MovimentacaoEstoque::create([
            'saldo_estoque_id' => $saldoId, 'tipo' => TipoMovimentacao::Saida, 'quantidade' => 1,
            'custo_unitario' => $valor, 'valor_total' => $valor, 'motivo' => 'consumo', 'registrado_por' => $admin->id,
        ]);
        MovimentacaoEstoque::where('id', $mov->id)->update(['created_at' => Carbon::create(2026, 5, 15, 12)]);

        return $mov;
    };

    $saida($novoSaldo($unidade->id, 'Consumo A')->id, 100.0);

    // Saída de A cujo saldo (FK cruzada) é um saldo de B, numa unidade de B.
    $saldoB = noTenantB($this->tenantB, fn () => $novoSaldo(Unidade::factory()->create()->id, 'Consumo B'));
    $envenenada = $saida($novoSaldo($unidade->id, 'Temporario')->id, 300.0);
    DB::table('movimentacoes_estoque')->where('id', $envenenada->id)->update(['saldo_estoque_id' => $saldoB->id]);

    $rateio = app(CalcularRateioMensalAction::class)->execute(5, 2026, 1000.0, $admin);
    $linha = $rateio->unidades->keyBy('unidade_id')[$unidade->id];

    // Sem o filtro, o consumo de B entrava no total (400) e A ficava com 25%.
    expect((float) $linha->percentual_consumo)->toBe(1.0)
        ->and((float) $linha->valor_rateado)->toBe(1000.0);
});

// ─── Pedidos de compra ───────────────────────────────────────────────────────

/**
 * Requisição aprovada com cotação vencedora no tenant do contexto.
 *
 * @return array{unidade: Unidade, compradora: User, fornecedor: Fornecedor, requisicao: Requisicao, itemReq: ItemRequisicao, cotacao: Cotacao}
 */
function joins_setupPedido(float $valorCotacao = 1000.0): array
{
    $unidade = Unidade::factory()->create();
    $solicitante = User::factory()->create();
    $compradora = User::factory()->compradora()->create();
    $compradora->unidades()->attach($unidade->id, ['perfil' => Perfil::CompradoraSenior->value]);
    $fornecedor = Fornecedor::factory()->homologado()->create();
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);

    $requisicao = Requisicao::create([
        'solicitante_id' => $solicitante->id, 'unidade_id' => $unidade->id, 'centro_custo_id' => $centro->id,
        'status' => StatusRequisicao::Aprovada, 'urgente' => false, 'is_emergencial' => false,
        'codigo' => 'REQ-2026-'.fake()->unique()->numerify('######'),
        'submetida_em' => now()->subHours(3), 'aprovada_em' => now()->subHour(), 'ciclo_aprovacao' => 1,
    ]);

    $itemReq = ItemRequisicao::create([
        'requisicao_id' => $requisicao->id, 'descricao' => 'Produto', 'quantidade' => 10,
        'unidade_medida' => 'un', 'valor_unitario_estimado' => $valorCotacao / 10,
    ]);

    $cotacao = Cotacao::create([
        'requisicao_id' => $requisicao->id, 'fornecedor_id' => $fornecedor->id, 'valor' => $valorCotacao,
        'vencedora' => true, 'criada_por' => $compradora->id, 'vencedora_definida_em' => now()->subMinutes(30),
    ]);

    return compact('unidade', 'compradora', 'fornecedor', 'requisicao', 'itemReq', 'cotacao');
}

/** Rascunho de PC (tenant do contexto) com um item da requisição do setup. */
function joins_rascunho(array $s, float $valor): PedidoCompra
{
    $pedido = PedidoCompra::create([
        'status' => StatusPedidoCompra::Rascunho, 'fornecedor_id' => $s['fornecedor']->id,
        'unidade_id' => $s['unidade']->id, 'criado_por' => $s['compradora']->id,
    ]);

    $pedido->itens()->create([
        'requisicao_id' => $s['requisicao']->id, 'item_requisicao_id' => $s['itemReq']->id, 'cotacao_id' => $s['cotacao']->id,
        'descricao' => 'Produto', 'quantidade' => 10, 'unidade_medida' => 'un',
        'valor_unitario' => $valor / 10, 'valor_total' => $valor, 'destino' => 'Central',
    ]);

    return $pedido;
}

/** Um PC EMITIDO no tenant B (para receber FKs cruzadas). */
function joins_pedidoEmitidoEmB(string $tenantB): PedidoCompra
{
    return noTenantB($tenantB, fn () => PedidoCompra::factory()->emitido()->create());
}

it('o painel não soma item de pedido de outro tenant no valor emitido', function () {
    $s = joins_setupPedido();
    $pedido = joins_rascunho($s, 100.0);
    DB::table('pedidos_compra')->where('id', $pedido->id)->update(['status' => StatusPedidoCompra::Emitido->value]);

    // Item DE B pendurado (FK cruzada) no pedido emitido de A.
    noTenantB($this->tenantB, function () use ($pedido) {
        $itemB = ItemPedidoCompra::factory()->create(['valor_total' => 99999]);
        DB::table('itens_pedido_compra')->where('id', $itemB->id)->update(['pedido_compra_id' => $pedido->id]);
    });

    $admin = User::factory()->admin()->create();

    expect((float) Livewire::actingAs($admin)->test(Dashboard::class)->viewData('valorEmitido'))->toBe(100.0);
});

it('o teto de desmembramento na emissão não conta pedido emitido de outro tenant', function () {
    $s = joins_setupPedido(1000.0);
    $pedido = joins_rascunho($s, 900.0);

    // Item DE A (mesma requisição) pendurado num pedido EMITIDO de B.
    $outro = joins_rascunho($s, 500.0);
    $pedidoB = joins_pedidoEmitidoEmB($this->tenantB);
    DB::table('itens_pedido_compra')->where('pedido_compra_id', $outro->id)->update(['pedido_compra_id' => $pedidoB->id]);

    $validar = new ReflectionMethod(EmitirPedidoCompraAction::class, 'validarLimiteDesmembramento');

    // 900 (este PC) + 500 (pedido de B) > 1000 estourava a validação de A.
    $validar->invoke(app(EmitirPedidoCompraAction::class), $pedido, $s['requisicao']->id, $pedido->itens()->get());

    expect(true)->toBeTrue();
});

it('cancelar pedido não confunde pedido emitido de outro tenant com "outro PC" da requisição', function () {
    $s = joins_setupPedido();
    $pedido = joins_rascunho($s, 1000.0);
    app(EmitirPedidoCompraAction::class)->execute($pedido, $s['compradora']);
    expect($s['requisicao']->fresh()->status)->toBe(StatusRequisicao::EmCompra);

    // Outro item DE A da mesma requisição, pendurado num pedido EMITIDO de B.
    $outro = joins_rascunho($s, 1.0);
    $pedidoB = joins_pedidoEmitidoEmB($this->tenantB);
    DB::table('itens_pedido_compra')->where('pedido_compra_id', $outro->id)
        ->update(['pedido_compra_id' => $pedidoB->id, 'item_requisicao_id' => ItemRequisicao::factory()->create()->id]);

    app(CancelarPedidoCompraAction::class)->execute($pedido, $s['compradora'], 'Cancelado por teste.');

    expect($s['requisicao']->fresh()->status)->toBe(StatusRequisicao::Aprovada);
});

it('o recebimento não soma item recebido de recebimento de outro tenant', function () {
    $s = joins_setupPedido();
    $pedido = joins_rascunho($s, 1000.0);
    app(EmitirPedidoCompraAction::class)->execute($pedido, $s['compradora']);
    $item = $pedido->itens()->sole();

    $almoxarife = User::factory()->create();
    $almoxarife->unidades()->attach($s['unidade']->id, ['perfil' => Perfil::Almoxarife->value]);

    // Recebimento DE B pendurado no pedido de A + item de recebimento DE A nesse recebimento.
    $recebimentoB = noTenantB($this->tenantB, function () use ($pedido) {
        $pedidoB = PedidoCompra::factory()->emitido()->create();
        $id = DB::table('recebimentos')->insertGetId([
            'tenant_id' => TenantContext::id(), 'pedido_compra_id' => $pedidoB->id, 'almoxarife_id' => User::factory()->create()->id,
            'recebido_em' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('recebimentos')->where('id', $id)->update(['pedido_compra_id' => $pedido->id]);

        return $id;
    });

    DB::table('itens_recebimento')->insert([
        'tenant_id' => $this->tenantA, 'recebimento_id' => $recebimentoB, 'item_pedido_compra_id' => $item->id,
        'quantidade_recebida' => 7, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $recebido = Livewire::actingAs($almoxarife)
        ->test(RegistroRecebimento::class, ['id' => $pedido->id])
        ->viewData('jaRecebidoPorItem');

    expect((float) ($recebido[$item->id] ?? 0))->toBe(0.0);
});

// ─── Inventário ──────────────────────────────────────────────────────────────

it('abrir inventário não exclui saldo por catálogo controla_lote de outro tenant', function () {
    $admin = User::factory()->admin()->create();
    $unidade = Unidade::factory()->create();
    $item = CatalogoItem::factory()->create(['controla_lote' => false]);

    $saldo = SaldoEstoque::create([
        'unidade_id' => $unidade->id, 'deposito' => 'Central', 'descricao_item' => $item->descricao,
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao($item->descricao), 'unidade_medida' => 'un',
        'quantidade' => 5, 'custo_medio_ponderado' => 10, 'valor_total' => 50, 'item_catalogo_id' => $item->id,
    ]);

    // O saldo de A aponta (FK cruzada) para um item de B que controla lote.
    $itemB = noTenantB($this->tenantB, fn () => CatalogoItem::factory()->create(['controla_lote' => true]));
    DB::table('saldos_estoque')->where('id', $saldo->id)->update(['item_catalogo_id' => $itemB->id]);

    $sessao = app(AbrirSessaoInventarioAction::class)->execute($unidade, 'Central', $admin);

    expect($sessao->itens->pluck('saldo_estoque_id')->all())->toContain($saldo->id);
});

// ─── Relatórios: getActiveTenantId() → TenantContext::requireId() ────────────

it('relatório sem tenant resolvido falha fechado em vez de filtrar tenant_id IS NULL', function () {
    TenantContext::forget();

    // Superadmin sem tenant home e sem tenant na sessão: getActiveTenantId() é null,
    // e `where('m.tenant_id', null)` virava `IS NULL` — casando com linhas órfãs.
    // make()+save(): sem o afterCreating da factory, que criaria membership sem tenant.
    $semTenant = User::factory()->make(['tenant_id' => null]);
    $semTenant->forceFill(['is_superadmin' => true])->save();

    $causas = [];

    try {
        Livewire::actingAs($semTenant)->test(ConsumoUnidade::class);
    } catch (Throwable $e) {
        // O Livewire embrulha a exceção do render (ViewException): vale a CAUSA.
        for ($atual = $e; $atual !== null; $atual = $atual->getPrevious()) {
            $causas[] = $atual::class;
        }
    }

    expect($causas)->toContain(MissingTenantContextException::class);
});
