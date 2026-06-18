<?php

use App\Enums\TipoMovimentacao;
use App\Livewire\Relatorios\ConsumoUnidade;
use App\Models\MovimentacaoEstoque;
use App\Models\SaldoEstoque;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(fn () => Mail::fake());

// ─── Helper ──────────────────────────────────────────────────────────────────

function r4_movimentacao(SaldoEstoque $saldo, TipoMovimentacao $tipo, float $valor, User $registrador): MovimentacaoEstoque
{
    return MovimentacaoEstoque::create([
        'saldo_estoque_id' => $saldo->id,
        'tipo' => $tipo,
        'quantidade' => 1.0,
        'custo_unitario' => $valor,
        'valor_total' => $valor,
        'motivo' => 'Teste R4',
        'registrado_por' => $registrador->id,
    ]);
}

// ─── Autorização ───────────────────────────────────────────────────────────────

it('rota_consumo_unidade_retorna_403_para_usuario_sem_permissao', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('relatorios.consumo-unidade'))
        ->assertForbidden();
});

it('rota_consumo_unidade_retorna_200_para_compradora', function () {
    $this->actingAs(User::factory()->compradora()->create())
        ->get(route('relatorios.consumo-unidade'))
        ->assertSuccessful();
});

// ─── Estado vazio ────────────────────────────────────────────────────────────

it('consumo_unidade_sem_dados_renderiza_mensagem_vazia', function () {
    $this->actingAs(User::factory()->compradora()->create())
        ->get(route('relatorios.consumo-unidade'))
        ->assertSuccessful()
        ->assertSee('Nenhum consumo registrado');
});

// ─── Agregação ─────────────────────────────────────────────────────────────────

it('consumo_unidade_soma_saidas_por_unidade', function () {
    $compradora = User::factory()->compradora()->create();
    $unidade = Unidade::factory()->create();
    $saldo = SaldoEstoque::factory()->create(['unidade_id' => $unidade->id]);

    r4_movimentacao($saldo, TipoMovimentacao::Saida, 250.0, $compradora);

    $this->actingAs($compradora)
        ->get(route('relatorios.consumo-unidade'))
        ->assertSuccessful()
        ->assertSee($unidade->nome)
        ->assertSee('250,00');
});

// ─── Adversário: só saída conta como consumo ────────────────────────────────────

it('consumo_unidade_ignora_movimentacoes_que_nao_sao_saida', function () {
    $compradora = User::factory()->compradora()->create();
    $unidade = Unidade::factory()->create();
    $saldo = SaldoEstoque::factory()->create(['unidade_id' => $unidade->id]);

    // Consumo real (deve contar).
    r4_movimentacao($saldo, TipoMovimentacao::Saida, 250.0, $compradora);

    // Entrada e ajuste positivo NÃO são consumo (não devem entrar no total).
    r4_movimentacao($saldo, TipoMovimentacao::Entrada, 999.0, $compradora);
    r4_movimentacao($saldo, TipoMovimentacao::AjustePositivo, 777.0, $compradora);

    $resultados = Livewire\Livewire::actingAs($compradora)
        ->test(ConsumoUnidade::class)
        ->viewData('resultados');

    expect($resultados)->toHaveCount(1);
    expect((int) $resultados->first()->total_saidas)->toBe(1);
    expect((float) $resultados->first()->total_consumido)->toBe(250.0);
});
