<?php

use App\Actions\RegistrarCotacaoAction;
use App\Enums\StatusRequisicao;
use App\Models\CentroCusto;
use App\Models\Fornecedor;
use App\Models\Requisicao;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function cpi_requisicaoComItens(): Requisicao
{
    $unidade = Unidade::factory()->create();
    $solicitante = User::factory()->create();
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);

    $req = Requisicao::create([
        'solicitante_id' => $solicitante->id,
        'unidade_id' => $unidade->id,
        'centro_custo_id' => $centro->id,
        'status' => StatusRequisicao::EmCotacao,
        'codigo' => 'REQ-CPI-0001',
        'urgente' => false,
        'is_emergencial' => false,
        'submetida_em' => now(),
    ]);

    $req->itens()->create(['descricao' => 'Mouse', 'quantidade' => 5, 'unidade_medida' => 'un', 'valor_unitario_estimado' => 30, 'avulso' => true]);
    $req->itens()->create(['descricao' => 'Teclado', 'quantidade' => 10, 'unidade_medida' => 'un', 'valor_unitario_estimado' => 60, 'avulso' => true]);

    return $req->load('itens');
}

it('registra cotação por item e calcula o total como soma das linhas', function () {
    $compradora = User::factory()->compradora()->create();
    $this->actingAs($compradora);

    $req = cpi_requisicaoComItens();
    $itens = $req->itens->values();
    $fornecedor = Fornecedor::factory()->homologado()->create();

    $cotacao = app(RegistrarCotacaoAction::class)->execute(
        $req, $fornecedor, 0.0, null, null, null, null,
        precosPorItem: [
            $itens[0]->id => 30.00,   // 30 × 5 = 150
            $itens[1]->id => 58.50,   // 58,50 × 10 = 585
        ]
    );

    expect((float) $cotacao->valor)->toBe(735.00)
        ->and($cotacao->itensCotacao()->count())->toBe(2)
        ->and((float) $cotacao->itensCotacao()->where('item_requisicao_id', $itens[0]->id)->first()->valor_unitario)->toBe(30.00);
});

it('o caminho legado (valor total, sem itens) continua funcionando', function () {
    $compradora = User::factory()->compradora()->create();
    $this->actingAs($compradora);

    $req = cpi_requisicaoComItens();
    $fornecedor = Fornecedor::factory()->homologado()->create();

    $cotacao = app(RegistrarCotacaoAction::class)->execute($req, $fornecedor, 1000.00);

    expect((float) $cotacao->valor)->toBe(1000.00)
        ->and($cotacao->itensCotacao()->count())->toBe(0);
});

it('ignora ids de item que não pertencem à requisição', function () {
    $compradora = User::factory()->compradora()->create();
    $this->actingAs($compradora);

    $req = cpi_requisicaoComItens();
    $itens = $req->itens->values();
    $fornecedor = Fornecedor::factory()->homologado()->create();

    $cotacao = app(RegistrarCotacaoAction::class)->execute(
        $req, $fornecedor, 0.0, null, null, null, null,
        precosPorItem: [
            $itens[0]->id => 30.00, // válido → 150
            999999 => 999.00,       // id inexistente → ignorado
        ]
    );

    expect((float) $cotacao->valor)->toBe(150.00)
        ->and($cotacao->itensCotacao()->count())->toBe(1);
});
