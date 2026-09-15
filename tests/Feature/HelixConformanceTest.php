<?php

use App\Livewire\Account2FA;
use App\Livewire\Admin\CatalogoItens\ListaCatalogoItens;
use App\Livewire\Admin\CentrosCusto\ListaCentrosCusto;
use App\Livewire\Admin\Unidades\ListaUnidades;
use App\Livewire\Admin\Usuarios\ListaUsuarios;
use App\Livewire\Almoxarife\MapaEstoque;
use App\Livewire\Almoxarife\SaldosEstoque;
use App\Livewire\Aprovacoes\FilaAprovacoes;
use App\Livewire\Compradora\GestaoCotacoes;
use App\Livewire\Compradora\ItensARepor;
use App\Livewire\Compradora\PedidosLoja;
use App\Livewire\Financeiro\ListaPagamentos;
use App\Livewire\Financeiro\Reconciliacao;
use App\Livewire\Relatorios\CustoObra;
use App\Livewire\Relatorios\PosicaoEstoque;
use App\Livewire\Requisicoes\FormularioRequisicao;
use App\Livewire\Solicitante\RequisicoesMaterial;
use App\Models\Cotacao;
use App\Models\Fornecedor;
use App\Models\PedidoCompra;
use App\Models\Requisicao;
use App\Models\Unidade;
use App\Models\UnidadeUser;
use App\Models\User;
use Helix\Foundation\Testing\Conformance\HelixConformance;

/*
|--------------------------------------------------------------------------
| Kit de conformidade Helix (fundação) — feature `compras`.
|--------------------------------------------------------------------------
|
| O Compras é legado de layout FLAT (App\Models, App\Livewire, database/migrations),
| não o Platform/{Produto} do gerador — o kit é apontado para esse layout.
|
| Toda exceção abaixo tem motivo; as marcadas "AGUARDANDO CATÁLOGO" são temporárias:
| saem quando a fundação publicar as permissões propostas e o app migrar para elas.
|
*/

// Unidade/Requisicao/PedidoCompra também carregam o UnidadeScope (recorte POR UNIDADE,
// fail-closed sem usuário). Para o teste de isolamento provar só a dimensão TENANT, um
// superadmin (vê todas as unidades) fica autenticado; o tenant vem do runFor do kit.
beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_superadmin' => true]));
});

$revalidadoNoTenant = 'select do cliente; revalidado a cada uso com Rule::exists(...)->where(tenant_id) e/ou findOrFail escopado pelo BelongsToTenant';
$filtroEscopado = 'filtro de listagem do cliente; só restringe uma consulta/coleção já escopada ao tenant (BelongsToTenant ou where tenant_id explícito) e ao vínculo do usuário — id alheio resulta em lista vazia';
$aguardandoCatalogo = 'AGUARDANDO CATÁLOGO — sem equivalente em Permission::catalogByFeature()[compras]; permissão proposta no relatório da adoção do kit (P2)';

HelixConformance::forProduct('Compras', feature: 'compras')
    ->models('app/Models', 'App\\Models')
    ->livewire('app/Livewire', 'App\\Livewire')
    ->migrations('database/migrations')

    // (a) models com tenant_id fora do BelongsToTenant
    ->allowModelWithoutTenantScope(User::class, 'users.tenant_id é o tenant HOME da identidade da fundação (membership multi-tenant em tenant_user); a administração filtra o tenant explicitamente (ListaUsuarios::usuariosDoTenant)')
    ->allowModelWithoutTenantScope(UnidadeUser::class, 'pivot do vínculo usuário×unidade: tenant_id derivado da unidade no creating (UnidadeUser::booted, recusa cruzar tenants); lido só via relação com wherePivot(tenant_id) ou DB::table com where tenant_id explícito')

    // (c) actions sem permissão: operam só sobre a conta do próprio usuário autenticado
    ->allowUnauthorizedAction(Account2FA::class.'::habilitar', '2FA da própria conta (auth()->user()), sem recurso de terceiro nem permissão aplicável')
    ->allowUnauthorizedAction(Account2FA::class.'::confirmar', '2FA da própria conta (auth()->user()), sem recurso de terceiro nem permissão aplicável')
    ->allowUnauthorizedAction(Account2FA::class.'::regenerar', '2FA da própria conta (auth()->user()), sem recurso de terceiro nem permissão aplicável')
    ->allowUnauthorizedAction(Account2FA::class.'::desabilitar', '2FA da própria conta (auth()->user()); a política de 2FA obrigatório é checada no próprio método')

    // (d) ids públicos que o cliente precisa trocar (select/filtro) — revalidados
    ->allowUnlockedId(ListaCatalogoItens::class.'::novoFornecedorId', $revalidadoNoTenant.' (adicionarHomologacao)')
    ->allowUnlockedId(ListaCentrosCusto::class.'::unidadeId', $revalidadoNoTenant.' (salvar)')
    ->allowUnlockedId(ListaCentrosCusto::class.'::gestorId', $revalidadoNoTenant.' (salvar)')
    ->allowUnlockedId(ListaUnidades::class.'::gestorId', $revalidadoNoTenant.' (salvar)')
    ->allowUnlockedId(ListaUsuarios::class.'::vincularUnidadeId', $revalidadoNoTenant.' (adicionarVinculo)')
    ->allowUnlockedId(SaldosEstoque::class.'::transferDestinoId', $revalidadoNoTenant.' (confirmarTransferencia)')
    ->allowUnlockedId(GestaoCotacoes::class.'::fornecedorId', $revalidadoNoTenant.' (registrarCotacao)')
    ->allowUnlockedId(PedidosLoja::class.'::unidadeId', $revalidadoNoTenant.' (promover)')
    ->allowUnlockedId(PedidosLoja::class.'::centroCustoId', $revalidadoNoTenant.' (promover; centro de custo amarrado à unidade)')
    ->allowUnlockedId(FormularioRequisicao::class.'::unidadeId', $revalidadoNoTenant.' (regrasValidacao em salvar/submeter)')
    ->allowUnlockedId(FormularioRequisicao::class.'::centroCustoId', $revalidadoNoTenant.' (regrasValidacao em salvar/submeter)')
    ->allowUnlockedId(FormularioRequisicao::class.'::obraId', $revalidadoNoTenant.' (regrasValidacao em salvar/submeter)')
    ->allowUnlockedId(RequisicoesMaterial::class.'::saldoEstoqueId', $revalidadoNoTenant.' + restrito às unidades do solicitante (salvar)')
    ->allowUnlockedId(ListaPagamentos::class.'::bancoId', 'select de banco do catálogo COMPE global (tabela bancos sem tenant); exists:bancos + Banco::find no registrar')
    ->allowUnlockedId(Reconciliacao::class.'::bancoId', 'select de banco do catálogo COMPE global (tabela bancos sem tenant); exists:bancos + findOrFail no processar')
    ->allowUnlockedId(ListaPagamentos::class.'::filtroBancoId', $filtroEscopado)
    ->allowUnlockedId(ListaPagamentos::class.'::filtroFornecedorId', $filtroEscopado)
    ->allowUnlockedId(MapaEstoque::class.'::filtroUnidadeId', $filtroEscopado)
    ->allowUnlockedId(FilaAprovacoes::class.'::filtroUnidadeId', $filtroEscopado)
    ->allowUnlockedId(FilaAprovacoes::class.'::filtroFaixaId', $filtroEscopado)
    ->allowUnlockedId(ItensARepor::class.'::filtroUnidadeId', $filtroEscopado)
    ->allowUnlockedId(CustoObra::class.'::obraId', $filtroEscopado)
    ->allowUnlockedId(PosicaoEstoque::class.'::unidadeId', $filtroEscopado)

    // (e) exists: em tabela global
    ->allowPlainExists('bancos', 'catálogo COMPE de bancos é global (tabela bancos sem tenant_id, Banco fora do ComprasModel)')

    // (j) tabelas sem tenant_id (database/migrations inteiro é varrido no layout flat)
    ->allowTableWithoutTenant('bancos', 'catálogo COMPE de bancos, registro público e global compartilhado por todos os tenants')
    ->allowTableWithoutTenant('cache', 'infraestrutura do framework (store de cache), sem dado de negócio')
    ->allowTableWithoutTenant('cache_locks', 'infraestrutura do framework (locks de cache/onOneServer), sem dado de negócio')
    ->allowTableWithoutTenant('jobs', 'infraestrutura de fila do framework; o tenant viaja no payload do job')
    ->allowTableWithoutTenant('job_batches', 'infraestrutura de fila do framework, sem dado de negócio')
    ->allowTableWithoutTenant('failed_jobs', 'infraestrutura de fila do framework; drenada por queue:prune-failed')

    // (h) permissões fora do catálogo — AGUARDANDO CATÁLOGO (temporárias)
    ->allowUncatalogedPermission('admin.gerenciar', $aguardandoCatalogo.': cadastros/parâmetros (unidades, centros de custo, fornecedores, catálogo, alçadas, reconciliação de saldos, reversão de rateio) — hoje só admin do tenant')
    ->allowUncatalogedPermission('estoque.gerenciar', $aguardandoCatalogo.': operação de almoxarifado por vínculo (perfil Almoxarife em unidade_user); estoque.manage do catálogo é do papel compras e não é equivalente')
    ->allowUncatalogedPermission('aprovacao.acessar-fila', $aguardandoCatalogo.': fila de aprovação por vínculo (perfil Aprovador em unidade_user)')
    ->allowUncatalogedPermission('aprovacao.acessar', $aguardandoCatalogo.': painel de aprovação por vínculo (Aprovador na unidade da requisição, mesmo tenant)')
    ->allowUncatalogedPermission('aprovacao.decidir', $aguardandoCatalogo.': decisão por vínculo + nível de alçada da etapa atual')

    // (h) permissão do catálogo sem papel além do admin
    ->allowUnassignedPermission('users.manage', 'DECISÃO DE PRODUTO PENDENTE: gestão de usuários do Compras segue exclusiva do admin do tenant (rotas /admin com middleware admin); delegar a um papel é escolha da fundação/produto')

    // (i) isolamento cross-tenant dos transacionais principais
    ->isolate(Unidade::class)
    ->isolate(Fornecedor::class)
    ->isolate(Requisicao::class)
    ->isolate(Cotacao::class)
    ->isolate(PedidoCompra::class)
    ->register();
