<?php

use App\Actions\ProcessarReconciliacaoCsvAction;
use App\Models\Banco;
use App\Models\Pagamento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

/**
 * O casamento da conciliação (achado 4.6) precisa considerar banco, valor e
 * referência — não só a referência textual. Antes: casava por referencia_banco
 * apenas, ignorando o banco selecionado e o valor, e o total conciliado somava
 * o valor do extrato mesmo quando o pagamento era de outro montante.
 */
function extrato(string $conteudo): UploadedFile
{
    return UploadedFile::fake()->createWithContent('extrato.csv', $conteudo);
}

beforeEach(function () {
    $this->action = app(ProcessarReconciliacaoCsvAction::class);
    $this->user = User::factory()->create();
    $this->bancoA = Banco::factory()->create(['nome' => 'Itaú']);
    $this->bancoB = Banco::factory()->create(['nome' => 'Bradesco']);
});

it('concilia quando banco, referência e valor batem', function () {
    Pagamento::factory()->create([
        'banco_id' => $this->bancoA->id, 'referencia_banco' => 'DOC1',
        'valor_total' => 100, 'valor_pago' => 100,
    ]);

    $recon = $this->action->execute(extrato('DOC1;100,00;01/08/2026'), $this->bancoA, $this->user);

    expect($recon->itens->first()->status)->toBe('conciliado')
        ->and((float) $recon->total_conciliado)->toBe(100.0);
});

it('não concilia pagamento de OUTRO banco com a mesma referência', function () {
    Pagamento::factory()->create([
        'banco_id' => $this->bancoA->id, 'referencia_banco' => 'DOC1', 'valor_pago' => 100,
    ]);

    // extrato importado no banco B — o pagamento é do banco A
    $recon = $this->action->execute(extrato('DOC1;100,00;01/08/2026'), $this->bancoB, $this->user);

    expect($recon->itens->first()->status)->toBe('orfao')
        ->and((float) $recon->total_conciliado)->toBe(0.0);
});

it('marca divergente quando a referência bate mas o valor não', function () {
    Pagamento::factory()->create([
        'banco_id' => $this->bancoA->id, 'referencia_banco' => 'DOC1', 'valor_pago' => 100,
    ]);

    $recon = $this->action->execute(extrato('DOC1;250,00;01/08/2026'), $this->bancoA, $this->user);

    expect($recon->itens->first()->status)->toBe('divergente')
        ->and((float) $recon->total_conciliado)->toBe(0.0);
});

it('marca ambíguo quando há mais de um pagamento com a mesma referência no banco', function () {
    Pagamento::factory()->count(2)->create([
        'banco_id' => $this->bancoA->id, 'referencia_banco' => 'DOC1', 'valor_pago' => 100,
    ]);

    $recon = $this->action->execute(extrato('DOC1;100,00;01/08/2026'), $this->bancoA, $this->user);

    expect($recon->itens->first()->status)->toBe('ambiguo')
        ->and((float) $recon->total_conciliado)->toBe(0.0);
});
