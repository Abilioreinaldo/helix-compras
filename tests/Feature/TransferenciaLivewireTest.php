<?php

use App\Enums\Perfil;
use App\Livewire\Almoxarife\SaldosEstoque;
use App\Models\SaldoEstoque;
use App\Models\TransferenciaEstoque;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function trlw_saldo(Unidade $unidade, float $qtd = 10.0, float $cmp = 50.0): SaldoEstoque
{
    return SaldoEstoque::create([
        'unidade_id' => $unidade->id,
        'deposito' => 'Depósito Central',
        'descricao_item' => 'Item Transf UI',
        'descricao_normalizada' => SaldoEstoque::normalizarDescricao('Item Transf UI'),
        'unidade_medida' => 'un',
        'quantidade' => $qtd,
        'custo_medio_ponderado' => $cmp,
        'valor_total' => $qtd * $cmp,
    ]);
}

it('saldos_almoxarife_transfere_via_modal', function () {
    $uOrigem = Unidade::factory()->create();
    $uDestino = Unidade::factory()->create();
    $almox = User::factory()->create();
    $almox->unidades()->attach($uOrigem->id, ['perfil' => Perfil::Almoxarife->value]);
    $saldo = trlw_saldo($uOrigem, 10.0, 50.0);

    Livewire::actingAs($almox)
        ->test(SaldosEstoque::class)
        ->call('abrirTransferencia', $saldo->id)
        ->set('transferDestinoId', (string) $uDestino->id)
        ->set('transferQuantidade', '4')
        ->set('transferMotivo', 'Realocação')
        ->call('confirmarTransferencia')
        ->assertHasNoErrors();

    expect(TransferenciaEstoque::count())->toBe(1)
        ->and((float) $saldo->refresh()->quantidade)->toBe(6.0)
        ->and((float) SaldoEstoque::where('unidade_id', $uDestino->id)->first()->quantidade)->toBe(4.0);
});

it('saldos_transferencia_insuficiente_mostra_erro_no_campo', function () {
    $uOrigem = Unidade::factory()->create();
    $uDestino = Unidade::factory()->create();
    $almox = User::factory()->create();
    $almox->unidades()->attach($uOrigem->id, ['perfil' => Perfil::Almoxarife->value]);
    $saldo = trlw_saldo($uOrigem, 10.0, 50.0);

    Livewire::actingAs($almox)
        ->test(SaldosEstoque::class)
        ->call('abrirTransferencia', $saldo->id)
        ->set('transferDestinoId', (string) $uDestino->id)
        ->set('transferQuantidade', '999')
        ->call('confirmarTransferencia')
        ->assertHasErrors('transferQuantidade');

    expect(TransferenciaEstoque::count())->toBe(0)
        ->and((float) $saldo->refresh()->quantidade)->toBe(10.0);
});

it('saldos_nao_transfere_saldo_de_unidade_alheia', function () {
    $uOrigem = Unidade::factory()->create();
    $uDestino = Unidade::factory()->create();
    $almoxOutro = User::factory()->create();
    $almoxOutro->unidades()->attach($uDestino->id, ['perfil' => Perfil::Almoxarife->value]);
    $saldo = trlw_saldo($uOrigem, 10.0, 50.0); // saldo da origem, almox é de outra unidade

    // O almoxarife só alcança saldos das suas unidades → findOrFail lança (404 em HTTP real).
    expect(fn () => Livewire::actingAs($almoxOutro)
        ->test(SaldosEstoque::class)
        ->call('abrirTransferencia', $saldo->id))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    expect(TransferenciaEstoque::count())->toBe(0);
});
