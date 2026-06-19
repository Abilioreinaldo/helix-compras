<?php

use App\Actions\CalcularRateioMensalAction;
use App\Enums\Perfil;
use App\Enums\TipoMovimentacao;
use App\Livewire\Relatorios\RelatorioRateioMensalCentral;
use App\Models\MovimentacaoEstoque;
use App\Models\SaldoEstoque;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function rcl_consumo(Unidade $unidade, User $reg, float $valor): void
{
    $saldo = SaldoEstoque::create([
        'unidade_id' => $unidade->id,
        'deposito' => 'Depósito Central',
        'descricao_item' => "Consumo U{$unidade->id}",
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao("Consumo U{$unidade->id}"),
        'unidade_medida' => 'un',
        'quantidade' => 10.0,
        'custo_medio_ponderado' => $valor,
        'valor_total' => 10.0 * $valor,
    ]);

    $mov = MovimentacaoEstoque::create([
        'saldo_estoque_id' => $saldo->id,
        'tipo' => TipoMovimentacao::Saida,
        'quantidade' => 1,
        'custo_unitario' => $valor,
        'valor_total' => $valor,
        'motivo' => 'consumo teste',
        'registrado_por' => $reg->id,
    ]);

    MovimentacaoEstoque::where('id', $mov->id)->update(['created_at' => Carbon::create(2026, 5, 15, 12)]);
}

it('relatorio_admin_ve_o_rateio', function () {
    $admin = User::factory()->admin()->create();
    $reg = User::factory()->create();
    $u = Unidade::factory()->create();
    rcl_consumo($u, $reg, 100.0);
    app(CalcularRateioMensalAction::class)->execute(5, 2026, 1000.0, $admin);

    Livewire::actingAs($admin)
        ->test(RelatorioRateioMensalCentral::class)
        ->assertSee('05/2026')
        ->assertSee('1.000,00');
});

it('relatorio_aprovador_ve_apenas_a_propria_unidade', function () {
    $admin = User::factory()->admin()->create();
    $reg = User::factory()->create();
    $uA = Unidade::factory()->create(['nome' => 'Unidade Alfa']);
    $uB = Unidade::factory()->create(['nome' => 'Unidade Beta']);
    rcl_consumo($uA, $reg, 100.0);
    rcl_consumo($uB, $reg, 100.0);

    $rateio = app(CalcularRateioMensalAction::class)->execute(5, 2026, 1000.0, $admin);

    $gestor = User::factory()->create();
    $gestor->unidades()->attach($uA->id, ['perfil' => Perfil::Aprovador->value]);

    Livewire::actingAs($gestor)
        ->test(RelatorioRateioMensalCentral::class)
        ->call('toggleExpandir', $rateio->id)
        ->assertSee('Unidade Alfa')
        ->assertDontSee('Unidade Beta');
});

it('relatorio_usuario_sem_admin_nem_aprovador_recebe_403', function () {
    $semAcesso = User::factory()->create();

    Livewire::actingAs($semAcesso)
        ->test(RelatorioRateioMensalCentral::class)
        ->assertForbidden();
});

it('relatorio_admin_reverte_e_gera_desconto', function () {
    $admin = User::factory()->admin()->create();
    $reg = User::factory()->create();
    $u = Unidade::factory()->create();
    rcl_consumo($u, $reg, 100.0);
    $rateio = app(CalcularRateioMensalAction::class)->execute(5, 2026, 1000.0, $admin);
    $linha = $rateio->unidades->first();

    Livewire::actingAs($admin)
        ->test(RelatorioRateioMensalCentral::class)
        ->call('abrirReversao', $linha->id)
        ->set('motivoReversao', 'Valor da central estava errado.')
        ->call('confirmarReversao')
        ->assertHasNoErrors();

    expect(MovimentacaoEstoque::where('tipo', TipoMovimentacao::DescontoRateio->value)->count())->toBe(1);
});

it('relatorio_aprovador_de_duas_unidades_ve_ambas', function () {
    $admin = User::factory()->admin()->create();
    $reg = User::factory()->create();
    $uA = Unidade::factory()->create(['nome' => 'Unidade Alfa']);
    $uB = Unidade::factory()->create(['nome' => 'Unidade Beta']);
    rcl_consumo($uA, $reg, 100.0);
    rcl_consumo($uB, $reg, 100.0);
    $rateio = app(CalcularRateioMensalAction::class)->execute(5, 2026, 1000.0, $admin);

    $gestor = User::factory()->create();
    $gestor->unidades()->attach($uA->id, ['perfil' => Perfil::Aprovador->value]);
    $gestor->unidades()->attach($uB->id, ['perfil' => Perfil::Aprovador->value]);

    Livewire::actingAs($gestor)
        ->test(RelatorioRateioMensalCentral::class)
        ->call('toggleExpandir', $rateio->id)
        ->assertSee('Unidade Alfa')
        ->assertSee('Unidade Beta');
});

it('relatorio_aprovador_nao_pode_reverter', function () {
    $admin = User::factory()->admin()->create();
    $reg = User::factory()->create();
    $u = Unidade::factory()->create();
    rcl_consumo($u, $reg, 100.0);
    $rateio = app(CalcularRateioMensalAction::class)->execute(5, 2026, 1000.0, $admin);
    $linha = $rateio->unidades->first();

    $gestor = User::factory()->create();
    $gestor->unidades()->attach($u->id, ['perfil' => Perfil::Aprovador->value]);

    // Gestor (Aprovador) tem acesso ao relatório, mas não pode disparar a reversão.
    Livewire::actingAs($gestor)
        ->test(RelatorioRateioMensalCentral::class)
        ->call('abrirReversao', $linha->id)
        ->assertForbidden();
});
