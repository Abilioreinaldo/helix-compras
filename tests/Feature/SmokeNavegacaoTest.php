<?php

use App\Enums\StatusPedidoCompra;
use App\Enums\StatusRequisicao;
use App\Models\PedidoCompra;
use App\Models\Requisicao;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Smoke: abre cada tela com o perfil adequado sobre a carga média e falha se algo der 500.
 * Pega bugs de RENDER (camada blade/Livewire) que a suíte de unidade não exercita.
 */
it('abre todas as telas principais sem erro 500', function () {
    $this->seed(DatabaseSeeder::class);

    $byEmail = fn (string $e) => User::where('email', $e)->firstOrFail();
    $admin = $byEmail('admin@comendador.com.br');
    $compradora = $byEmail('compradora@comendador.com.br');
    $almox = $byEmail('almoxarife@comendador.com.br');
    $solic = $byEmail('solicitante@comendador.com.br');
    $diretor = $byEmail('diretor@comendador.com.br');

    // Prefere uma requisição COM logs/itens — exercita o render completo da detalhe
    // (histórico de status), caminho que não aparece num rascunho vazio.
    $req = Requisicao::withoutGlobalScopes()->has('logs')->first()
        ?? Requisicao::withoutGlobalScopes()->first();
    $reqEditavel = Requisicao::withoutGlobalScopes()->whereIn('status', [StatusRequisicao::Rascunho->value, StatusRequisicao::Devolvida->value])->first();
    $reqCotacao = Requisicao::withoutGlobalScopes()->where('status', StatusRequisicao::EmCotacao->value)->first();
    $reqAprov = Requisicao::withoutGlobalScopes()->where('status', StatusRequisicao::AguardandoAprovacao->value)->first();
    $pc = PedidoCompra::withoutGlobalScopes()->where('status', StatusPedidoCompra::Emitido->value)->whereNotNull('numero')->first();

    $telas = array_filter([
        ['/dashboard', $admin],
        ['/requisicoes', $solic],
        ['/requisicoes/nova', $solic],
        ['/compradora/triagem', $compradora],
        ['/compradora/itens-a-repor', $compradora],
        ['/compradora/pedidos', $compradora],
        ['/cotacoes', $compradora],
        ['/aprovacoes', $diretor],
        ['/almoxarife/recebimentos', $almox],
        ['/almoxarife/estoque', $almox],
        ['/almoxarife/atendimento-material', $almox],
        ['/almoxarife/inventario', $almox],
        ['/solicitante/requisicoes-material', $solic],
        ['/relatorios/gastos-cc', $admin],
        ['/relatorios/pendentes-aprovador', $admin],
        ['/relatorios/custo-obra', $admin],
        ['/relatorios/emergenciais', $admin],
        ['/relatorios/gastos-fornecedor', $admin],
        ['/relatorios/tempo-aprovacao', $admin],
        ['/relatorios/posicao-estoque', $admin],
        ['/relatorios/consumo-unidade', $admin],
        ['/relatorios/comparativo-unidades', $admin],
        ['/relatorios/rateio-central', $admin],
        ['/admin/unidades', $admin],
        ['/admin/usuarios', $admin],
        ['/admin/fornecedores', $admin],
        ['/admin/alcadas', $admin],
        ['/admin/centros-custo', $admin],
        ['/admin/catalogo-itens', $admin],
        ['/admin/reconciliacao-saldos', $admin],
        // Parametrizadas (onde bugs de relação costumam aparecer)
        $req ? ['/requisicoes/'.$req->id, $admin] : null,
        $reqEditavel ? ['/requisicoes/'.$reqEditavel->id.'/editar', $solic] : null,
        $reqCotacao ? ['/compradora/cotacoes/'.$reqCotacao->id, $compradora] : null,
        $reqCotacao ? ['/requisicoes/'.$reqCotacao->id.'/mapa-cotacao', $compradora] : null,
        $reqAprov ? ['/aprovacoes/'.$reqAprov->id, $diretor] : null,
        $pc ? ['/compradora/pedidos/'.$pc->id, $compradora] : null,
        $pc ? ['/compradora/pedidos/'.$pc->id.'/pdf', $compradora] : null,
        $pc ? ['/almoxarife/recebimentos/'.$pc->id, $almox] : null,
    ]);

    $falhas = [];
    foreach ($telas as [$uri, $user]) {
        try {
            $status = $this->actingAs($user)->get($uri)->status();
        } catch (Throwable $e) {
            $falhas[] = "{$uri} => EXCEPTION: ".class_basename($e).': '.$e->getMessage();

            continue;
        }
        if ($status >= 500) {
            $falhas[] = "{$uri} => HTTP {$status}";
        }
    }

    expect($falhas)->toBe([]);
});
