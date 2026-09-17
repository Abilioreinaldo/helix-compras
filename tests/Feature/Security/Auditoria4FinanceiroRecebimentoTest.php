<?php

/**
 * 4ª auditoria adversarial — COMPRAS-V2 (contas a pagar) e COMPRAS-V3 (recebimento).
 *
 *  - V2: a sonda V4-P1 provou que o segundo pagamento parcial SOBRESCREVIA o primeiro
 *    (600 + 400 → valor_pago = 400, status parcial) e que o teto de 110% valia por
 *    lançamento (600 + 400 + 1.100 = 2.100 lançados para uma dívida de 1.000).
 *  - V3: achado de leitura — o recebimento somava o já recebido sem lock pessimista.
 *    Em `:memory:` não há duas transações; aqui se prova que a Action EMITE a leitura
 *    com lock (pedido e itens) ANTES de somar `itens_recebimento`, e que a rechecagem
 *    do saldo a receber barra o segundo recebimento.
 */

use App\Actions\CancelarPedidoCompraAction;
use App\Actions\EmitirPedidoCompraAction;
use App\Actions\RegistrarPagamentoAction;
use App\Actions\RegistrarRecebimentoAction;
use App\Enums\MetodoPagamento;
use App\Enums\Perfil;
use App\Enums\StatusPagamento;
use App\Enums\StatusRequisicao;
use App\Livewire\Financeiro\ListaPagamentos;
use App\Models\Cotacao;
use App\Models\Fornecedor;
use App\Models\ItemPedidoCompra;
use App\Models\ItemRequisicao;
use App\Models\Pagamento;
use App\Models\PedidoCompra;
use App\Models\Requisicao;
use App\Models\Scopes\UnidadeScope;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(fn () => Mail::fake());

function a4_pagar(Pagamento $pagamento, float $valor, User $usuario): Pagamento
{
    return app(RegistrarPagamentoAction::class)->execute(
        $pagamento->fresh(), $valor, now()->toDateString(), MetodoPagamento::Boleto, null, null, null, $usuario,
    );
}

/**
 * SQLite não compila `FOR UPDATE` (a gramática devolve ''). Para PROVAR que a Action pede
 * o lock, troca-se a gramática por uma que marca a leitura com um comentário SQL — o
 * SQLite ignora o comentário e o `DB::listen` enxerga a marca.
 *
 * @return array<int, string> consultas executadas, em ordem (preenchido por referência)
 */
function a4_espionarLocks(array &$consultas): void
{
    $conexao = DB::connection();

    if ($conexao->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('Espião de lock só é necessário (e só funciona) em SQLite; em MySQL o FOR UPDATE é real.');
    }

    $conexao->setQueryGrammar(new class($conexao) extends SQLiteGrammar
    {
        protected function compileLock(Builder $query, $value)
        {
            return $value === true ? ' /* FOR UPDATE */' : '';
        }
    });

    DB::listen(function ($query) use (&$consultas) {
        $consultas[] = $query->sql;
    });
}

/** @return array{pedido: PedidoCompra, item: ItemPedidoCompra, almoxarife: User, compradora: User} */
function a4_pedidoEmitido(): array
{
    $unidade = Unidade::factory()->create();
    $compradora = User::factory()->compradora()->create();
    $almoxarife = User::factory()->create();
    $almoxarife->unidades()->attach($unidade->id, ['perfil' => Perfil::Almoxarife->value]);
    $fornecedor = Fornecedor::factory()->homologado()->create();
    $requisicao = Requisicao::factory()->create(['unidade_id' => $unidade->id, 'status' => StatusRequisicao::Aprovada]);
    $itemReq = ItemRequisicao::factory()->create(['requisicao_id' => $requisicao->id, 'quantidade' => 10]);
    $cotacao = Cotacao::factory()->create(['requisicao_id' => $requisicao->id, 'fornecedor_id' => $fornecedor->id, 'valor' => 1000, 'vencedora' => true]);
    $pedido = PedidoCompra::factory()->create(['fornecedor_id' => $fornecedor->id, 'unidade_id' => $unidade->id]);
    $item = ItemPedidoCompra::factory()->create([
        'pedido_compra_id' => $pedido->id, 'requisicao_id' => $requisicao->id, 'item_requisicao_id' => $itemReq->id,
        'cotacao_id' => $cotacao->id, 'quantidade' => 10, 'valor_unitario' => 100, 'valor_total' => 1000, 'destino' => 'Depósito',
    ]);

    test()->actingAs($compradora);
    $pedido = app(EmitirPedidoCompraAction::class)->execute($pedido, $compradora);

    return compact('pedido', 'item', 'almoxarife', 'compradora');
}

// ─── COMPRAS-V2 ──────────────────────────────────────────────────────────────

it('COMPRAS-V2: pagamentos parciais ACUMULAM — 600 + 400 quita a dívida de 1.000', function () {
    $financeiro = User::factory()->financeiro()->create();
    $pagamento = Pagamento::factory()->create(['valor_total' => 1000, 'status' => StatusPagamento::Pendente]);

    $apos600 = a4_pagar($pagamento, 600.0, $financeiro);
    expect((float) $apos600->valor_pago)->toBe(600.0)->and($apos600->status)->toBe(StatusPagamento::Parcial);

    $apos400 = a4_pagar($pagamento, 400.0, $financeiro);
    expect((float) $apos400->valor_pago)->toBe(1000.0)->and($apos400->status)->toBe(StatusPagamento::Pago);
});

it('COMPRAS-V2: o teto de 110% vale sobre o TOTAL pago, não por lançamento', function () {
    $financeiro = User::factory()->financeiro()->create();
    $pagamento = Pagamento::factory()->create(['valor_total' => 1000, 'status' => StatusPagamento::Pendente]);

    a4_pagar($pagamento, 600.0, $financeiro);

    // 600 já pagos: o máximo que ainda cabe é 500 (1.100 − 600). 1.100 "cabia" no teto por lançamento.
    expect(fn () => a4_pagar($pagamento, 1100.0, $financeiro))->toThrow(ValidationException::class, 'R$ 500,00');
    expect(fn () => a4_pagar($pagamento, 500.01, $financeiro))->toThrow(ValidationException::class);

    expect((float) $pagamento->fresh()->valor_pago)->toBe(600.0)
        ->and($pagamento->fresh()->status)->toBe(StatusPagamento::Parcial);

    $final = a4_pagar($pagamento, 500.0, $financeiro);
    expect((float) $final->valor_pago)->toBe(1100.0)->and($final->status)->toBe(StatusPagamento::Pago);
});

it('COMPRAS-V2: depois de quitada pela soma dos parciais, a dívida não aceita novo lançamento', function () {
    $financeiro = User::factory()->financeiro()->create();
    $pagamento = Pagamento::factory()->create(['valor_total' => 1000, 'status' => StatusPagamento::Pendente]);

    a4_pagar($pagamento, 600.0, $financeiro);
    a4_pagar($pagamento, 400.0, $financeiro);

    expect(fn () => a4_pagar($pagamento, 100.0, $financeiro))->toThrow(ValidationException::class);
    expect((float) $pagamento->fresh()->valor_pago)->toBe(1000.0);
});

it('COMPRAS-V2: cada lançamento fica na trilha de auditoria com o valor do lançamento e o acumulado', function () {
    $financeiro = User::factory()->financeiro()->create();
    $pagamento = Pagamento::factory()->create(['valor_total' => 1000, 'status' => StatusPagamento::Pendente]);

    a4_pagar($pagamento, 600.0, $financeiro);
    a4_pagar($pagamento, 400.0, $financeiro);

    $trilha = DB::table('audit_logs')->where('action', 'compras.pagamento_registrado')->orderBy('created_at')->orderBy('id')->get();
    expect($trilha)->toHaveCount(2);

    $lancamentos = $trilha->map(fn ($linha) => json_encode($linha))->implode(' ');
    expect($lancamentos)->toContain('valor_lancamento')->toContain('valor_pago_acumulado');
});

it('COMPRAS-V2: o modal de registro abre com o SALDO da dívida, não com o valor total', function () {
    $financeiro = User::factory()->financeiro()->create();
    $pagamento = Pagamento::factory()->create(['valor_total' => 1000, 'status' => StatusPagamento::Pendente]);
    a4_pagar($pagamento, 600.0, $financeiro);

    Livewire::actingAs($financeiro)->test(ListaPagamentos::class)
        ->call('abrirRegistrar', $pagamento->id)
        ->assertSet('valorPago', '400.00');
});

// ─── COMPRAS-V3 ──────────────────────────────────────────────────────────────

it('COMPRAS-V3: o recebimento lê pedido e itens COM LOCK antes de somar o já recebido', function () {
    $c = a4_pedidoEmitido();

    $consultas = [];
    a4_espionarLocks($consultas);

    app(RegistrarRecebimentoAction::class)->execute($c['pedido'], $c['almoxarife'], [$c['item']->id => 4.0]);

    $posicao = fn (string $trecho, bool $comLock) => collect($consultas)->search(
        fn (string $sql) => str_contains($sql, $trecho) && str_starts_with($sql, 'select') && (! $comLock || str_contains($sql, '/* FOR UPDATE */'))
    );

    $lockPedido = $posicao('from "pedidos_compra"', true);
    $lockItens = $posicao('from "itens_pedido_compra"', true);
    $somaRecebido = $posicao('from "itens_recebimento"', false);

    expect($lockPedido)->not->toBeFalse('o pedido tem de ser relido com lockForUpdate')
        ->and($lockItens)->not->toBeFalse('os itens do pedido têm de ser lidos com lockForUpdate')
        ->and($somaRecebido)->not->toBeFalse()
        ->and($lockPedido)->toBeLessThan($somaRecebido)
        ->and($lockItens)->toBeLessThan($somaRecebido);
});

it('COMPRAS-V3: a rechecagem do saldo a receber barra o segundo recebimento do mesmo saldo', function () {
    $c = a4_pedidoEmitido();

    // Dois almoxarifes com a MESMA tela aberta (instâncias antigas do pedido): o primeiro recebe tudo.
    $telaDoSegundo = PedidoCompra::withoutGlobalScope(UnidadeScope::class)->find($c['pedido']->id);
    app(RegistrarRecebimentoAction::class)->execute($c['pedido'], $c['almoxarife'], [$c['item']->id => 10.0]);

    expect(fn () => app(RegistrarRecebimentoAction::class)->execute($telaDoSegundo, $c['almoxarife'], [$c['item']->id => 10.0]))
        ->toThrow(ValidationException::class, 'excede o saldo');

    expect((float) DB::table('itens_recebimento')->where('item_pedido_compra_id', $c['item']->id)->sum('quantidade_recebida'))->toBe(10.0);
});

it('COMPRAS-V3 (irmão): o cancelamento do pedido também relê o pedido com lock', function () {
    $c = a4_pedidoEmitido();

    $consultas = [];
    a4_espionarLocks($consultas);

    app(CancelarPedidoCompraAction::class)->execute($c['pedido'], $c['compradora'], 'fornecedor desistiu');

    expect(collect($consultas)->contains(fn (string $sql) => str_contains($sql, 'from "pedidos_compra"') && str_contains($sql, '/* FOR UPDATE */')))->toBeTrue();
});
