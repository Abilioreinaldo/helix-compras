<?php

use App\Actions\ConcluirCotacaoAction;
use App\Actions\RegistrarCotacaoAction;
use App\Enums\StatusRequisicao;
use App\Models\CentroCusto;
use App\Models\Cotacao;
use App\Models\FaixaAlcada;
use App\Models\Fornecedor;
use App\Models\Requisicao;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

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

it('rejeita cotação por item quando nenhum preço é válido (> 0)', function () {
    $compradora = User::factory()->compradora()->create();
    $this->actingAs($compradora);

    $req = cpi_requisicaoComItens();
    $itens = $req->itens->values();
    $fornecedor = Fornecedor::factory()->homologado()->create();

    expect(fn () => app(RegistrarCotacaoAction::class)->execute(
        $req, $fornecedor, 0.0, null, null, null, null,
        precosPorItem: [$itens[0]->id => 0, $itens[1]->id => 0.001], // zero e micro → ambos descartados
    ))->toThrow(ValidationException::class);

    expect(Cotacao::count())->toBe(0);
});

it('soma o total por linhas arredondadas (bate com o mapa) em quantidade fracionária', function () {
    $compradora = User::factory()->compradora()->create();
    $this->actingAs($compradora);

    $unidade = Unidade::factory()->create();
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);
    $req = Requisicao::create([
        'solicitante_id' => User::factory()->create()->id,
        'unidade_id' => $unidade->id,
        'centro_custo_id' => $centro->id,
        'status' => StatusRequisicao::EmCotacao,
        'codigo' => 'REQ-CPI-FRAC',
        'urgente' => false,
        'is_emergencial' => false,
        'submetida_em' => now(),
    ]);
    $i1 = $req->itens()->create(['descricao' => 'Granel A', 'quantidade' => 1.333, 'unidade_medida' => 'kg', 'valor_unitario_estimado' => 1, 'avulso' => true]);
    $i2 = $req->itens()->create(['descricao' => 'Granel B', 'quantidade' => 2.666, 'unidade_medida' => 'kg', 'valor_unitario_estimado' => 2, 'avulso' => true]);
    $fornecedor = Fornecedor::factory()->homologado()->create();

    $cotacao = app(RegistrarCotacaoAction::class)->execute(
        $req, $fornecedor, 0.0, null, null, null, null,
        precosPorItem: [$i1->id => 1.10, $i2->id => 2.20], // round(1.4663)=1.47 + round(5.8652)=5.87 = 7.34
    );

    $somaLinhas = $cotacao->itensCotacao->sum(fn ($ic) => $ic->valorLinha());
    expect((float) $cotacao->valor)->toBe(7.34)
        ->and(round($somaLinhas, 2))->toBe(7.34); // total armazenado == soma exibida no mapa
});

it('concluir cotação não conta cotações sem valor confirmado para o mínimo', function () {
    $compradora = User::factory()->compradora()->create();
    $this->actingAs($compradora);

    $req = cpi_requisicaoComItens();
    $faixa = FaixaAlcada::factory()->create(['minimo_cotacoes' => 3, 'is_emergencial' => false, 'ativo' => true]);
    $req->update(['faixa_alcada_id' => $faixa->id]);

    // 2 confirmadas + 1 aguardando (valor null) → total 3, mas confirmadas 2 < 3.
    Cotacao::factory()->create(['requisicao_id' => $req->id, 'criada_por' => $compradora->id, 'valor' => 100, 'fornecedor_id' => Fornecedor::factory()->create()->id]);
    Cotacao::factory()->create(['requisicao_id' => $req->id, 'criada_por' => $compradora->id, 'valor' => 110, 'fornecedor_id' => Fornecedor::factory()->create()->id]);
    Cotacao::factory()->create(['requisicao_id' => $req->id, 'criada_por' => $compradora->id, 'valor' => null, 'fornecedor_id' => Fornecedor::factory()->create()->id]);

    expect(fn () => app(ConcluirCotacaoAction::class)->execute($req))->toThrow(ValidationException::class);
});
