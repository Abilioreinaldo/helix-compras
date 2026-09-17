<?php

use App\Enums\Perfil;
use App\Http\Controllers\PropostaCotacaoPublicaController;
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
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Helix\Foundation\Testing\Conformance\HelixConformance;
use Helix\Foundation\Testing\PlatformFixtures;

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
// fail-closed sem usuário). Para o teste de isolamento provar só a dimensão TENANT é
// preciso um usuário que ENXERGUE o registro do tenant sob teste — mas NÃO um
// superadmin: ele desliga o Gate::before (libera qualquer permissão) e transcende
// tenants, e o kit passaria a medir um mundo privilegiado em vez do mundo real.
// Usamos a compradora sênior, que continua sujeita a policies, permissões e escopo de
// tenant. O tenant de cada asserção vem do runFor do próprio kit. Ver KitConformidadeAtorTest.
beforeEach(function () {
    $this->actingAs(User::factory()->compradora()->create());
});

/**
 * Fábrica do kit para as models com UnidadeScope (fundação v0.7.0, FUNDACAO-8).
 *
 * Até a v0.6.x a compradora enxergava "todas as unidades" em qualquer tenant, porque
 * `hasPermission('compras.manage')` era avaliada no tenant HOME dela — inclusive dentro
 * dos tenants efêmeros que o kit cria para o teste de isolamento. Era exatamente o
 * escalonamento que a v0.7.0 fechou: permissão vale NO TENANT DA OPERAÇÃO, e num tenant
 * onde ela não tem vínculo a resposta é NÃO.
 *
 * O mundo real que sobra é o do usuário comum: enxerga o que está NA UNIDADE a que está
 * vinculado. Então a fábrica cria o registro sob o contexto do kit e liga o ator à
 * unidade dele — no mesmo tenant (o pivot `unidade_user` deriva o tenant da unidade e
 * recusa cruzar). Antes disso o ator precisa SER da empresa: a FK composta
 * `unidade_user(user_id, tenant_id) → tenant_user(user_id, tenant_id)` (migration
 * 2026_09_21_000002) só aceita vínculo de unidade de quem tem vínculo com o tenant. Quem
 * declara o vínculo é o `PlatformFixtures` da fundação — caminho oficial de fixture,
 * auditado e sem `runAsPlatform` no código do app (portanto sem exceção de kit).
 *
 * O isolamento continua sendo medido pelo BelongsToTenant: sob o tenant A, o vínculo com
 * a unidade de B não é sequer lido (o UnidadeScope filtra o pivot pelo tenant do contexto).
 */
function kitVinculandoOAtor(Closure $criar): Closure
{
    return function () use ($criar) {
        $registro = $criar();
        $unidadeId = $registro instanceof Unidade ? $registro->getKey() : $registro->unidade_id;
        $ator = auth()->user();

        PlatformFixtures::member($ator, (string) TenantContext::requireId('kit'), User::SCOPE_CORPORATE);

        $ator->unidades()->syncWithoutDetaching([
            $unidadeId => ['perfil' => Perfil::Almoxarife->value, 'nivel_alcada' => null],
        ]);

        return $registro;
    };
}

$revalidadoNoTenant = 'select do cliente; revalidado a cada uso com Rule::exists(...)->where(tenant_id) e/ou findOrFail escopado pelo BelongsToTenant';
$filtroEscopado = 'filtro de listagem do cliente; só restringe uma consulta/coleção já escopada ao tenant (BelongsToTenant ou where tenant_id explícito) e ao vínculo do usuário — id alheio resulta em lista vazia';
$aguardandoCatalogo = 'AGUARDANDO CATÁLOGO — sem equivalente em Permission::catalogByFeature()[compras]; permissão proposta no relatório da adoção do kit (P2)';

$kit = HelixConformance::forProduct('Compras', feature: 'compras')
    ->models('app/Models', 'App\\Models')
    ->livewire('app/Livewire', 'App\\Livewire')
    ->migrations('database/migrations')

    // (a) models com tenant_id fora do BelongsToTenant
    // ATENÇÃO: `users.tenant_id` é o tenant HOME da identidade, NÃO "o tenant do
    // usuário" — quem participa de uma empresa é a MEMBERSHIP (pivot tenant_user).
    // Filtrar a administração pelo home era o defeito (achado ALTO da auditoria
    // adversarial), não a correção: ver UsuariosMembershipTest.
    ->allowModelWithoutTenantScope(User::class, 'users é a identidade COMPARTILHADA da suíte e não tem um tenant dono: users.tenant_id é só o tenant HOME. O recorte por empresa é a membership ativa no pivot tenant_user — é assim que a administração filtra (ListaUsuarios::usuariosDoTenant, whereExists em tenant_user) e é o mesmo critério da autorização (User::belongsToTenant)')
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

    // (d2) propriedades públicas que o cliente edita por desenho (não são id de registro)
    ->allowUnlockedId(ListaCatalogoItens::class.'::codigo', 'campo de FORMULÁRIO digitado pelo usuário (código interno do item), não referência a registro; unicidade é por tenant (catalogo_itens_tenant_codigo_uq) e o registro editado vem do $editandoId, que é #[Locked]')
    ->allowUnlockedId(ListaCentrosCusto::class.'::codigo', 'campo de FORMULÁRIO digitado pelo usuário (código do centro de custo), não referência a registro; o registro editado vem do $editandoId, que é #[Locked]')
    ->allowUnlockedId(FormularioRequisicao::class.'::itens', 'linhas do formulário editadas pelo cliente; `item_catalogo_id` é revalidado a cada submit com Rule::exists(catalogo_itens)->where(tenant_id) e autorizado com can(operar, $catalogoItem) em selecionarItemCatalogo; as linhas são recriadas sob $requisicao->itens()')

    // (e) exists: em tabela global
    ->allowPlainExists('bancos', 'catálogo COMPE de bancos é global (tabela bancos sem tenant_id, Banco fora do ComprasModel)')

    // (c2) action cujo único findOrFail é de tabela GLOBAL (sem tenant a comparar)
    ->allowUnauthorizedAction(Reconciliacao::class.'::processar', 'autoriza com manage/Pagamento antes de tudo; o único findOrFail é Banco (catálogo COMPE global, tabela sem tenant_id) — não há registro de tenant a passar para a policy. A ReconciliacaoBancaria nasce carimbada pelo contexto (BelongsToTenant)')

    // (decisão 15) relatório de saneamento multitenant, somente leitura
    ->allowPlatformContext('app/Console/Commands/IntegridadeTenantCommand.php::handle', 'relatório de saneamento SÓ LEITURA do DBA (decisão 15): compara filhas e mães de TODOS os tenants para achar órfãos/cruzados — é por definição cross-tenant. Só SELECT (listener que recusa INSERT/UPDATE/DELETE/DDL em IntegridadeTenantCommandTest); não é agendado, roda à mão em cópia da produção')
    ->allowRawDbTable('app/Console/Commands/IntegridadeTenantCommand.php::contarOrfaos', 'relatório de saneamento SÓ LEITURA (decisão 15, docs/SANEAMENTO-TENANT.md): contar filhas cuja mãe NÃO EXISTE atravessa todos os tenants por definição — não há tenant a filtrar numa linha órfã. Só SELECT count (provado em IntegridadeTenantCommandTest com listener que recusa escrita), roda sob runAsPlatform declarado')

    // (k) DB::table() cru — migrations de schema/backfill (rodam no deploy, fora de request)
    ->allowRawDbTable('database/migrations/2026_08_04_000001_add_tenant_id_to_unidades.php::up', 'migration de backfill: é ela que CARIMBA tenant_id em unidades a partir do 1º tenant; roda uma vez no deploy, fora de request, e por definição atravessa tenants')
    ->allowRawDbTable('database/migrations/2026_08_04_000002_add_tenant_id_to_compras_business_tables.php::up', 'migration de backfill: carimba tenant_id nas 33 tabelas de negócio a partir de unidades/pais; roda uma vez no deploy, fora de request')
    ->allowRawDbTable('database/migrations/2026_09_15_000002_sequencias_por_tenant.php::up', 'migration de schema: cria as tabelas de sequência e semeia uma linha POR TENANT (itera tenants por definição)')
    ->allowRawDbTable('database/migrations/2026_09_15_000002_sequencias_por_tenant.php::down', 'ROLLBACK de schema: o down() recria a tabela de sequência ANTIGA, que era por ano e global na instalação, colapsando o MAX(ultimo_numero) de todos os tenants — atravessar tenants é o próprio objetivo do rollback; roda só em migrate:rollback, fora de request')
    ->allowRawDbTable('database/migrations/2026_09_15_000004_cotacoes_email_por_tenant_e_token_opaco.php::up', 'migration de dados: gera o email_token opaco das cotações existentes de TODOS os tenants, uma vez, no deploy')
    ->allowRawDbTable('database/migrations/2026_09_17_000003_add_tenant_foreign_keys.php::up', 'migration de schema: sanea tenant_id órfão e cria a FK para tenants em todas as tabelas de negócio; roda uma vez no deploy, fora de request')
    ->allowRawDbTable('database/migrations/2026_06_16_150803_add_fusao_to_movimentacoes_estoque_tipo.php::up', 'migration de schema: no SQLite a ampliação do enum `tipo` é feita recriando a coluna (ADD/UPDATE/DROP/RENAME). Os dois UPDATEs flagrados COPIAM a coluna para si mesma (tipo → tipo_novo, e o inverso no down) — são parte indivisível do rebuild de coluna, cujos ALTER o próprio kit já ignora como DDL. Filtrar por tenant_id aqui deixaria as linhas dos demais tenants com a coluna nova vazia')
    ->allowRawDbTable('database/migrations/2026_06_16_150803_add_fusao_to_movimentacoes_estoque_tipo.php::down', 'migration de schema: no SQLite a ampliação do enum `tipo` é feita recriando a coluna (ADD/UPDATE/DROP/RENAME). Os dois UPDATEs flagrados COPIAM a coluna para si mesma (tipo → tipo_novo, e o inverso no down) — são parte indivisível do rebuild de coluna, cujos ALTER o próprio kit já ignora como DDL. Filtrar por tenant_id aqui deixaria as linhas dos demais tenants com a coluna nova vazia')

    // (l) update/insert em massa com tenant_id no payload
    ->allowMassTenantWrite('database/migrations/2026_08_04_000001_add_tenant_id_to_unidades.php::up', 'backfill único: o UPDATE com tenant_id é o próprio objetivo da migration')
    ->allowMassTenantWrite('database/migrations/2026_08_04_000002_add_tenant_id_to_compras_business_tables.php::up', 'backfill único: os UPDATEs com tenant_id são o próprio objetivo da migration')
    ->allowMassTenantWrite('database/migrations/2026_09_15_000002_sequencias_por_tenant.php::up', 'semeadura da linha de sequência (tenant_id, ano): a coluna É a chave da linha, não um atributo migrável')
    ->allowMassTenantWrite('database/migrations/2026_09_17_000003_add_tenant_foreign_keys.php::up', 'saneamento pré-FK: zera tenant_id ÓRFÃO (aponta para tenant inexistente) para a chave estrangeira poder ser criada')
    ->allowMassTenantWrite('app/Support/SequenciaAnualPorTenant.php::proximo', 'tabelas de sequência (sequencias_pedido_compra/sequencias_requisicao) não têm model nem BelongsToTenant: a LINHA é o par (tenant_id, ano), então o insertOrIgnore precisa da coluna. Nenhum registro muda de tenant — o insert só cria a linha do próprio tenant e o update mexe só em ultimo_numero')

    // (n) withoutTenantScope() sem filtro de tenant
    ->allowUnfilteredBypass('app/Actions/ProcessarRespostaCotacaoAction.php::resolverLink', 'a caixa IMAP de cotações é única da instalação e roda no console, SEM tenant no contexto: a referência PÚBLICA do link (cotacao_links.referencia, ULID único na base) é o lookup que DESCOBRE o tenant. Logo em seguida tudo — leitura, dedupe, auditoria e aviso — roda dentro de TenantContext::runFor((string) $link->tenant_id). Não grava nada na cotação (decisão 11)')
    ->allowUnfilteredBypass('app/Services/CotacaoLinkService.php::resolver', 'rota PÚBLICA do link assinado (sem login, sem tenant no contexto): o token (lookup pelo SHA-256, único na base) é o que DESCOBRE o tenant; o controller roda todo o resto sob TenantContext::runFor((string) $link->tenant_id) e a cotação é relida escopada (decisão 11)')

    // (o) consulta a User sem filtro de tenant
    ->allowUnscopedUserQuery('app/Console/Commands/ExecutarRateioMensal.php::handle', 'console: o --executado-por identifica o Admin operador ANTES de existir tenant no contexto; é dele que o tenant é derivado (runFor do tenant do Admin), e o comando recusa quem não tem perfil Admin')
    ->allowUnscopedUserQuery('app/Console/Commands/SanearDuplicatasCatalogo.php::handle', 'console: idem — o --executado-por resolve o Admin operador antes do tenant, e a fusão fica restrita ao tenant DELE')

    // (q) comando agendado sem runFor/eachTenant
    ->allowTenantlessCommand('cotacoes:capturar-respostas', 'o comando não escolhe tenant: ele lê a caixa IMAP única da instalação. O tenant de CADA mensagem é descoberto pela referência pública do link de cotação e todo o processamento (só aviso, nada gravado) roda dentro de TenantContext::runFor (ProcessarRespostaCotacaoAction::execute)')

    // (c3) endpoints públicos por desenho
    ->allowUnauthorizedEndpoint(PropostaCotacaoPublicaController::class.'::show', 'link assinado de cotação (decisão 11): o fornecedor não tem login. A autorização É o link — assinatura HMAC do APP_KEY com expiração + token de 256 bits conferido pelo SHA-256 na base + uso único/revogação/expiração + cotação relida sob o tenant do link e amarrada ao fornecedor do link; qualquer falha devolve a mesma página genérica. Rate limit por IP e token (Security/CotacaoLinkAssinadoTest)')
    ->allowUnauthorizedEndpoint(PropostaCotacaoPublicaController::class.'::store', 'idem show: grava só nos campos de sugestão da cotação do link, depois de consumir o link com UPDATE condicional atômico (uso único), dentro de TenantContext::runFor do tenant do link (Security/CotacaoLinkAssinadoTest)')

    // (j2) tabela de infraestrutura sem tenant_id (também fora do alcance do off-boarding)
    ->allowTableWithoutTenant('personal_access_tokens', 'tabela do Sanctum: token de API da IDENTIDADE (tokenable = users), que é compartilhada pela suíte e não tem tenant dono. O expurgo do tenant não a alcança de propósito — quem revoga é o UserService (removeMembership/changeStatus/deleteUser revogam os tokens do usuário)')

    // (p) unicidades sem tenant_id na coluna — TODAS já são por-tenant por transitividade
    //     ou globais por desenho. A coluna-líder de cada uma é FK para uma tabela
    //     escopada (e agora com FK para tenants): duas empresas não podem compartilhar
    //     o mesmo pai, logo a colisão entre tenants é impossível por construção.
    ->allowGlobalUnique('obras.unidade_id', 'unidade_id é FK para unidades, que tem tenant_id + FK para tenants: uma obra de outra empresa nunca aponta para a mesma unidade — o unique já é por-tenant por transitividade')
    ->allowGlobalUnique('etapas_alcada.faixa_alcada_id+ordem', 'faixa_alcada_id é FK para faixas_alcada (escopada): a etapa herda o tenant da faixa')
    ->allowGlobalUnique('centros_custo.unidade_id+codigo+deleted_at', 'unidade_id é FK para unidades (escopada): o código do centro de custo já é único POR UNIDADE, logo por tenant')
    ->allowGlobalUnique('cotacoes.email_token', 'DESCONTINUADO (decisão 11): coluna legada, não é mais gerada nem lida — o índice sai junto com a coluna numa migration de limpeza. Era global por desenho (resolvia o tenant na caixa IMAP única)')
    ->allowGlobalUnique('cotacao_links.token_hash', 'SHA-256 de token aleatório de 256 bits: identificador GLOBAL por desenho — é ele que DESCOBRE o tenant na rota pública do link assinado, antes de existir tenant no contexto')
    ->allowGlobalUnique('cotacao_links.referencia', 'ULID público de correlação do e-mail de cotação: GLOBAL por desenho — é ele que DESCOBRE o tenant da resposta que chega pela caixa IMAP única da instalação')
    ->allowGlobalUnique('aprovacoes.requisicao_id+ciclo+ordem+deleted_at', 'requisicao_id é FK para requisicoes (escopada)')
    ->allowGlobalUnique('itens_pedido_compra.pedido_compra_id+item_requisicao_id', 'pedido_compra_id é FK para pedidos_compra (escopada)')
    ->allowGlobalUnique('saldos_estoque.unidade_id+deposito+descricao_normalizada', 'unidade_id é FK para unidades (escopada): a identidade do saldo já é única por unidade, logo por tenant')
    ->allowGlobalUnique('catalogo_itens.uuid', 'UUID é identificador GLOBAL por desenho (chave estável do item entre apps da suíte); a chave de negócio do catálogo já é por tenant (catalogo_itens_tenant_codigo_uq)')
    ->allowGlobalUnique('itens_inventario.sessao_inventario_id+saldo_estoque_id', 'sessao_inventario_id é FK para sessoes_inventario (escopada)')
    ->allowGlobalUnique('lotes_estoque.saldo_estoque_id+numero_lote', 'saldo_estoque_id é FK para saldos_estoque (escopada). Índice PARCIAL (fundido_para_id IS NULL) no SQLite / coluna gerada no MySQL')
    ->allowGlobalUnique('rateio_unidades.rateio_central_id+unidade_id', 'rateio_central_id é FK para rateios_centrais (escopada, unique por tenant+mês+ano)')
    ->allowGlobalUnique('itens_cotacao.cotacao_id+item_requisicao_id', 'cotacao_id é FK para cotacoes (escopada)')
    ->allowGlobalUnique('precos_homologados.uuid', 'UUID é identificador GLOBAL por desenho; a chave de negócio (item+fornecedor) é escopada pelo item de catálogo')

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

    // (h) permissão do catálogo sem papel além do admin — `users.manage` e
    //     `channels.manage` saíram daqui na fundação v0.7.0: são ADMIN_ONLY do catálogo
    //     (Permission::ADMIN_ONLY), decisão da fundação, e o kit já as dispensa. Repetir
    //     a exceção aqui virava uma linha MORTA (reprovada pelo allowlist_hygiene).

    // (r) universo vazio declarado (v0.3.0: vazio = INCONCLUSIVO, não aprovado)
    ->acceptEmpty('jobs_carry_tenant', 'o Compras não tem jobs próprios — o diretório app/Jobs não existe. O único processamento fora de request é o comando cotacoes:capturar-respostas, que roda síncrono e estabelece o tenant com runFor (ver allowTenantlessCommand acima). O primeiro job do app remove esta linha')

    // (i) isolamento cross-tenant dos transacionais principais. Os três que carregam o
    //     UnidadeScope recebem a fábrica que liga o ator à unidade do registro (acima).
    ->isolate(Unidade::class, kitVinculandoOAtor(fn () => Unidade::factory()->create()))
    ->isolate(Fornecedor::class)
    ->isolate(Requisicao::class, kitVinculandoOAtor(fn () => Requisicao::factory()->create()))
    ->isolate(Cotacao::class)
    ->isolate(PedidoCompra::class, kitVinculandoOAtor(fn () => PedidoCompra::factory()->create()));

$kit->register();
