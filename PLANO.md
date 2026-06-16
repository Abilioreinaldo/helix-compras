# PLANO — Sistema de Gestão de Compras v1
# Rede Comendador

**Última atualização:** 2026-06-16
**Status geral:** Fases 0–8 + v1.1-A (catálogo) + v1.1-B (fusão/UNIQUE) implementadas. **v1 ainda NÃO está completa** — o PLANO foi realinhado ao ESCOPO.md; ver "Pendências reais de v1" abaixo.
**Branch principal:** main

---

## Decisões fechadas (2026-06-10)

| # | Tema | Decisão |
|---|------|---------|
| GP-1 | Modelo de alçadas | **Acumulativas e sequenciais.** Modelar como etapas ordenadas (ordem 1, 2…) vinculadas à faixa de alçada. Diretor sempre aprova antes do CEO; ninguém pula etapa. Admin parametriza quantos níveis quiser sem mudar schema. |
| GP-2 | Recebimento parcial | **Por quantidade, item a item.** Pedido fecha quando todas as quantidades forem recebidas. Controle de lote/validade fica para v1.1 junto com estoque. |
| L-1 | Compra emergencial | **Opção B — cotação posterior.** 1 cotação no ato + justificativa obrigatória + aprovação do Diretor independente do valor. Flag "emergencial" permanente no registro. Cotações complementares registradas em até 24h após o recebimento. Relatório mensal de compras emergenciais por unidade e solicitante entra na v1 (emergência recorrente = falha de planejamento). |
| L-2 | Recebimento parcial | Resolvida por GP-2. |
| L-3 | Custeio do estoque | **Custo médio ponderado.** Padrão contábil brasileiro; recalculado a cada entrada. Último custo não precisa de implementação própria — já está na cotação mais recente do item; exibir como referência quando for trivial. |
| L-4 | SLA da Compradora (24h) | **Opção A — alerta + flag "atrasada".** Após 24h sem triagem: Admin recebe notificação, requisição ganha flag visível. Sem escalonamento para substituto (cargo de backup não existe ainda). |

---

## Contexto do projeto

Laravel 13 + Livewire 4 + SQLite local.
Nenhum módulo de negócio implementado até a data deste plano.

---

## Fases

### Fase 0 — Fundação técnica ✅ IMPLEMENTADA (21 testes, QA aprovado)
**Objetivo:** estabelecer a base que todas as outras fases dependem — autenticação, layout, perfis de acesso, log de auditoria e estrutura de multi-unidade.

**Status:** 21/21 testes passando. Pint limpo. QA validou Marco M0.

**Pronto:**
- Enums: `Perfil`, `NivelAlcada`, `StatusUnidade`, `TipoUnidade`, `StatusObra`
- Models: `Unidade`, `Obra`, `FaixaAlcada` (`$table='faixas_alcada'`), `EtapaAlcada` (`$table='etapas_alcada'`), `Auditoria` (`UPDATED_AT=null`)
- `UnidadeScope` — query direta em `unidade_user` (evita recursão), falha fechado
- Trait `Auditavel` — listeners diretos (evita loop de boot com `static::observe()`)
- Trait `PertenceAUnidade`, `AuditoriaObserver`
- Migrations (7): users extensão, unidades, obras, unidade_user, faixas_alcada, etapas_alcada, auditorias
- Factories + Seeders (UsuarioSeeder, UnidadeSeeder, DatabaseSeeder)
- Auth Livewire: `Login`, `TrocarSenha` (full-page, sem Breeze)
- Middleware `ForcaTrocaSenha` registrado inline nas rotas (`auth` group)
- Layout `app.blade.php` + `guest.blade.php`
- `MenuLateral` Livewire (itens condicionados via `temPerfil()`)
- `routes/web.php`: login, logout, dashboard, senha/trocar
- `AppServiceProvider`: morphMap com `unidade` e `obra`

**Pendente backlog:**
- Comando `requisicoes:marcar-atrasadas` (SLA 24h) — aguarda model Requisicao da Fase 2

**O que é entregue:**
- Autenticação (login/logout/senha) com perfis: Admin, Compradora, Aprovador, Solicitante, Gestor de Unidade
- Middleware de visibilidade por unidade (solicitante/gestor só veem a própria unidade)
- Componente de log de auditoria reutilizável (quem, quando, de→para) — usado por todos os módulos seguintes
- Layout base e navegação lateral com Livewire

**Dependências:** nenhuma (ponto de partida)

**Risco principal:** escopo de perfis subestimado — se as regras de visibilidade por unidade forem mais complexas que o previsto, bloqueia todas as fases seguintes. Mitigação: validar as regras com o PM antes de fechar a Fase 0.

---

### Fase 1 — Cadastros Base ✅ IMPLEMENTADA (35 testes, sec revisado)
**Objetivo:** prover as entidades de referência que alimentam todos os fluxos de compra.

**Status:** 35/35 testes passando (21 Fase0 + 14 Fase1). Pint limpo. Revisão sec: P0s e P1s corrigidos.

**Pronto:**
- Models: `Fornecedor` (global, sem PertenceAUnidade), `CentroCusto` (com PertenceAUnidade, `colunaUnidade='unidade_id'`)
- `FaixaAlcada` e `EtapaAlcada`: trait `Auditavel` adicionado
- Migrations (2): `fornecedores` (UNIQUE cnpj+deleted_at), `centros_custo` (UNIQUE unidade+codigo+deleted_at)
- Factories + Seeders: `FornecedorFactory`, `CentroCustoFactory`, `FornecedorSeeder`, `CentroCustoSeeder`
- Livewire Admin: `ListaUnidades`, `ListaUsuarios`, `ListaFornecedores`, `ListaAlcadas`, `ListaCentrosCusto`
- Rotas admin: grupo `middleware(AdminMiddleware)`, prefixo `/admin`
- `AdminMiddleware`: usa `temPerfil(Perfil::Admin)` (corrigido sec P0-1)
- `MenuLateral`: usa `temPerfil()` para todos os checks (corrigido sec P0-2)
- Todos os métodos de escrita Livewire: `abort_unless(temPerfil(Perfil::Admin), 403)` (sec P1-1/P1-2)
- `ListaFornecedores::salvar()`: CNPJ único com `whereNull('deleted_at')` (sec P1-3)
- `ListaUsuarios::adicionarVinculo()`: `Rule::exists()->whereNull('deleted_at')` (sec P1-4)
- `ListaUsuarios::salvar()`: `isAdmin`/`isCompradora` validados como boolean; senha provisória exposta via `$senhaProvisoria` (sec P0-3, P1-5)
- `ListaFornecedores::homologar()`: guard contra re-homologação (sec P2-3)

**Pendente backlog (P2 — não bloqueante):**
- PHPDoc em `Fornecedor` explicando ausência de PertenceAUnidade
- Comentários em chamadas `withoutGlobalScopes()` nos componentes

**O que é entregue:**
- CRUD completo de Unidades, Usuários, Fornecedores, Centros de Custo/Obras, Alçadas
- Seeds de dados de exemplo (FornecedorSeeder, CentroCustoSeeder)
- Controle de senha provisória na criação de usuário

**Dependências:** Fase 0 concluída (perfis, log e visibilidade por unidade já funcionando)

**Risco principal:** modelagem das alçadas é o ponto de maior complexidade conceitual; uma modelagem errada aqui quebra o fluxo de aprovação inteiro. Mitigação: revisar o modelo de dados com o PM antes de migrar.

---

### Fase 2 — Requisição de Compra + Módulo de Obras ✅ IMPLEMENTADA (48 testes, sec revisado)
**Objetivo:** permitir que solicitantes abram requisições e que o sistema aplique as regras de verba de obra automaticamente.

**Status:** 48/48 testes passando. Pint limpo. Revisão sec: P0s e P1s corrigidos.

**Pronto:**
- Enum `StatusRequisicao` (13 casos), models `Requisicao`, `ItemRequisicao`, `RequisicaoLog`
- `SubmeterRequisicaoAction`: lockForUpdate em obra, verba 80% alerta / 100% bloqueio, snapshot `faixa_alcada_id`
- `TransicionarStatusRequisicaoAction`: mapa completo de transições (Fases 2–5)
- Livewire: `FormularioRequisicao`, `ListaRequisicoes`, `DetalheRequisicao`
- Compradora: `TriagemRequisicoes` — fila ordenada (atrasadas primeiro, depois por `submetida_em`)
- Command `requisicoes:marcar-atrasadas` (agendado horário via `routes/console.php`)
- `FaixaAlcada` agora imutável com SoftDeletes (versionamento garantido)
- Migrations: soft_deletes em faixas_alcada, requisicoes, requisicao_itens, requisicao_logs
- Factories + Seeders: `RequisicaoFactory`, `ItemRequisicaoFactory`, `RequisicaoSeeder`

**O que é entregue:**
- Formulário de Requisição com multi-item, urgência, emergencial, centro de custo/obra
- Validação de verba: alerta 80%, bloqueio 100% com lockForUpdate
- Log de status em cada transição
- Visibilidade por unidade do solicitante; Compradora vê todas

**Dependências:** Fase 1 concluída

---

### Fase 3 — Cotação ✅ IMPLEMENTADA (60 testes, sec revisado)
**Objetivo:** registrar as cotações recebidas para cada requisição e controlar o mínimo obrigatório por faixa de valor.

**Status:** 60/60 testes passando. Pint limpo. Revisão sec: P0 + 3×P1 corrigidos.

**Pronto:**
- Model `Cotacao` (table=`cotacoes`, Auditavel, SoftDeletes, unique `requisicao_id+fornecedor_id+deleted_at`)
- `RegistrarCotacaoAction`: valida homologado+ativo, disco `local`, grava `primeira_cotacao_em` na 1ª cotação
- `MarcarCotacaoVencedoraAction`: zera anterior dentro de transação, grava `vencedora_definida_em/por`
- `ConcluirCotacaoAction`: valida mínimo (emergencial=1, normal=`faixaAlcada.minimo_cotacoes`), exige exatamente 1 vencedora, grava `cotacao_concluida_em`, avança status via `TransicionarStatusRequisicaoAction`
- Livewire `GestaoCotacoes`: WithFileUploads, abort_unless CompradoraSenior em todos os writes, refresh+status check antes de cada ação
- `DownloadArquivoCotacaoController`: download autenticado do disco privado (apenas CompradoraSenior)
- `minimo_cotacoes` em `faixas_alcada` (default 3), `primeira_cotacao_em` + `cotacao_concluida_em` em `requisicoes`
- Rota `/compradora/cotacoes/arquivo/{cotacao}` registrada antes de `/{id}` (evita shadowing)
- Link "Gerenciar Cotações" no DetalheRequisicao quando status=`em_cotacao`
- Factories: `CotacaoFactory` (state `vencedora()`)

**Correções de segurança aplicadas:**
- P0: arquivos movidos para disco `local` (não público) + rota de download autenticada
- P1: `mimetypes` em vez de `mimes`; revalidação de status em todos os write methods; removido `lockForUpdate` ineficaz

**O que é entregue:**
- Compradora registra N cotações com anexo; emergencial libera com 1
- Sistema bloqueia avanço para CotacaoConcluida se mínimo não atingido ou sem vencedora
- Mínimo configurável por faixa de alçada (admin parametriza)

**Dependências:** Fase 2 concluída; Fase 1 concluída (fornecedores e alçadas)

---

### Fase 4 — Aprovação ✅ IMPLEMENTADA (73 testes, sec revisado, QA aprovado)
**Objetivo:** implementar o fluxo de aprovação manual com dupla aprovação, notificação por e-mail e retorno para a Compradora em caso de rejeição.

**Status:** 73/73 testes passando. Pint limpo. Revisão sec: P0s e P1s corrigidos. QA aprovado.

**Pronto:**
- Enum `StatusAprovacao` (Pendente, Aprovada, Reprovada, Pulada)
- Model `Aprovacao` (`$table='aprovacoes'`, Auditavel, SoftDeletes, casts, relações)
- Migration `aprovacoes`: ciclo, nivel_exigido, obrigatoria_emergencial, indexes compostos, unique com deleted_at
- Migration `add_aprovacao_campos_to_requisicoes`: ciclo_aprovacao, aprovacao_iniciada_em, aprovada_em, reprovada_em, reprovada_por
- `IniciarAprovacaoAction`: materializa etapas da alçada, valida aprovadores existentes, prepend Diretor para emergencial, e-mail pós-commit
- `AprovarEtapaAction`: lockForUpdate, valida permissão por pivot (nível correto), avança etapas ou conclui aprovação, e-mail pós-commit
- `ReprovarRequisicaoAction`: marca Reprovada+Puladas, incrementa ciclo, duas transições (→Reprovada→EmCotacao), notifica todas as compradoras
- `ConcluirCotacaoAction`: encadeia `IniciarAprovacaoAction` fora da transação, captura ValidationException
- Mailables: `RequisicaoAguardandoAprovacao`, `RequisicaoAprovada`, `RequisicaoReprovada` (views Markdown)
- Livewire `FilaAprovacoes` e `PainelAprovacao` com abort_unless em mount+render+actions
- IDOR fix em `PainelAprovacao::carregarRequisicao()` via query direta em `unidade_user`
- Rotas `/aprovacoes` e `/aprovacoes/{id}`, link no MenuLateral e no DetalheRequisicao

**Correções de segurança aplicadas:**
- P0-1 IDOR: `PainelAprovacao` guard via `DB::table('unidade_user')` (não só `findOrFail`)
- P0-2 Notificação: `ReprovarRequisicaoAction` usa `->get()->all()` — notifica TODAS as compradoras
- P1-1 abort_unless em render(): adicionado em `FilaAprovacoes` e `PainelAprovacao`
- P1-2 Falha em IniciarAprovacao: try-catch em `ConcluirCotacaoAction` reporta erro sem perder commit da cotação

**Correções técnicas relevantes:**
- `wherePivot` dentro de `whereHas` callback não funciona (recebe `Eloquent\Builder`, não `BelongsToMany`) — fixado com `User::whereIn('id', fn → unidade_user)` em `IniciarAprovacaoAction` e `AprovarEtapaAction`
- Pluralização incorreta `aprovacaos` → fixada com `protected $table = 'aprovacoes'`
- Mailables Markdown exigem `Content::markdown:` (não `Content::view:`) — corrigido em todos os 3

**Dependências:** Fase 3 concluída (cotação mínima precisa estar satisfeita antes de entrar em aprovação); Fase 0 (perfis e log)

---

### Fase 5 — Pedido de Compra ✅ IMPLEMENTADA (92 testes, sec revisado, QA aprovado)
**Objetivo:** gerar o Pedido de Compra formal a partir de requisições aprovadas, incluindo PDF e número sequencial.

**Status:** 92/92 testes passando (73 Fases 0–4 + 19 Fase 5). Pint limpo. npm build OK.

**Pronto:**
- Enum `StatusPedidoCompra` (Rascunho, Emitido, Cancelado) e `ModalidadeEntrega` (Entrega, Retirada, Transportadora)
- Models: `PedidoCompra` (Auditavel, PertenceAUnidade, SoftDeletes), `ItemPedidoCompra` (SoftDeletes)
- Migrations (4): `pedidos_compra`, `sequencias_pedido_compra`, `itens_pedido_compra`, `add_prazo_entrega_modalidade_entrega`
- `CriarRascunhoPedidoAction`: valida cotação vencedora do fornecedor, aceita Aprovada e EmCompra
- `EmitirPedidoCompraAction`: número PC-AAAA-NNNN com lockForUpdate, guard de desmembramento (soma PCs ≤ cotação), transita requisições para EmCompra, e-mail pós-commit
- `CancelarPedidoCompraAction`: reverte requisição para Aprovada apenas se nenhum outro PC emitido a cobrir; teto liberado para novos PCs
- `TransicionarStatusRequisicaoAction`: transições EmCompra→Aprovada e EmCompra→Recebida
- Livewire: `GestaoPedidosCompra` (sugestões por fornecedor), `FormularioPedidoCompra` (edição de itens + campos novos), `DetalhePedidoCompra` (cancelamento)
- `BaixarPdfPedidoCompraController`: DomPDF, disco privado, guard CompradoraSenior, 404 para rascunho
- PDF template com rastreabilidade por requisição e agrupamento por destino
- Mailable `PedidoCompraEmitido` → notifica todos os solicitantes das requisições vinculadas
- Factories: `PedidoCompraFactory` (estados `emitido`, `cancelado`), `ItemPedidoCompraFactory`
- Rotas: `/compradora/pedidos`, `/{id}`, `/{id}/editar`, `/{id}/pdf` (estática antes do dinâmico)
- `MenuLateral` atualizado com "Pedidos de Compra" para CompradoraSenior

**Correções de escopo aplicadas (vs. spec original):**
- `prazo_entrega` (date) e `modalidade_entrega` (enum) adicionados via migration — campos semânticos no lugar de texto livre
- Guard de desmembramento confirma `soma PCs emitidos ≤ valor cotação vencedora` (com tolerância de R$0,005)
- Cancelamento de PC desmembrado: requisição mantém EmCompra se outro PC ainda a cobre; saldo do PC cancelado é devolvido (guard recalcula excluindo PCs cancelados)

**Dependências:** Fase 4 concluída (só requisições aprovadas viram pedido)

---

### Fase 6 — Recebimento ✅ IMPLEMENTADA (109 testes, Pint limpo)
**Objetivo:** registrar o recebimento das mercadorias, total ou parcial, com integração automática ao estoque (F7).

**Status:** 109/109 testes passando (92 Fases 0–5 + 17 Fase 6). Pint limpo.

**Pronto:**
- Enum `StatusRecebimentoPedido` (Pendente, Parcial, Total) — derivado via `PedidoCompra::statusRecebimento()`
- Models: `Recebimento` (Auditavel, SoftDeletes), `ItemRecebimento` (SoftDeletes)
- Migrations: `recebimentos` (pedido_compra_id, almoxarife_id, recebido_em, observacoes) e `itens_recebimento` (recebimento_id, item_pedido_compra_id, quantidade_recebida)
- `RegistrarRecebimentoAction`: valida PC Emitido, valida saldo por item (sem exceder quantidade ordenada), cria Recebimento+ItemRecebimento, verifica conclusão por requisição pós-gravação, e-mail pós-commit
- Transição automática: recebimento total → `EmCompra → Recebida → Concluida` (imediato, automático)
- Query `verificarConclusaoRequisicao` e `statusRecebimento()` usam subquery pré-agregada para evitar fan-out em múltiplos recebimentos
- Livewire: `GestaoPedidosRecebimento` (lista PCs emitidos da unidade com badge de status), `RegistroRecebimento` (formulário por item + histórico)
- IDOR guard em `RegistroRecebimento`: verifica `unidade_user` pivot para o almoxarife
- Mailable `PedidoCompraRecebido` → notifica solicitante apenas quando requisição vai a Concluída
- Rotas: `/almoxarife/recebimentos` e `/almoxarife/recebimentos/{id}`
- `MenuLateral`: link "Recebimentos" do Almoxarife apontado para rota real

**Escopo não implementado em F6 (risco anotado):**
- Permissão por unidade de destino do item (cross-unit por almoxarife distinto) — F6.1

**Dependências:** Fase 5 concluída (pedido precisa existir)

---

### Fase 7 — Estoque ✅ IMPLEMENTADA (126 testes, Pint limpo, sec + QA aprovados)
**Objetivo:** controlar saldo de estoque por item × depósito com custo médio ponderado (CMP) e append-only ledger de movimentações.

**Status:** 126/126 testes passando (109 Fases 0–6 + 17 Fase 7). Pint limpo. npm build OK.

**Pronto:**
- Enum `TipoMovimentacao` (Entrada, Saida, AjustePositivo, AjusteNegativo) com `adicionaEstoque()`
- Model `SaldoEstoque` (Auditavel) — snapshot derivado: quantidade, custo_medio_ponderado, valor_total
- Model `MovimentacaoEstoque` (Auditavel) — ledger append-only, sem SoftDeletes; FK gancho `item_pedido_compra_id` → `itens_pedido_compra.{prazo_entrega, modalidade_entrega}` para F8+
- `SaldoEstoque::normalizarDescricao()`: trim + lowercase + colapsa espaços múltiplos
- Identidade do saldo: UNIQUE `(unidade_id, deposito, descricao_normalizada)`; `descricao_item` mantido para exibição
- `EntradaEstoqueAction`: chamada DENTRO da transação de `RegistrarRecebimentoAction`; recalcula CMP: `(valor_atual + qtd × custo) / (qtd_atual + qtd_nova)`; `lockForUpdate` defensivo (SQLite serializa, MySQL necessita)
- `SaidaEstoqueAction`: abre própria transação; usa CMP vigente sem alterar; guarda de unidade; saldo clampado a 0 na janela de tolerância de ponto flutuante
- `AjusteEstoqueAction`: mesmo padrão de saída; ajuste NÃO altera CMP (AjustePositivo/Negativo valorizam pelo CMP vigente)
- `RegistrarRecebimentoAction`: modificado — injeta `EntradaEstoqueAction`, chama para cada `ItemRecebimento` criado; depósito derivado de `ItemPedidoCompra.destino`
- Livewire `SaldosEstoque`: lista saldos por unidade (filtra via pivot `Almoxarife`), busca por `descricao_normalizada`, filtro por depósito, row vermelha quando quantidade ≤ 0
- Rota `GET /almoxarife/estoque` → `almoxarife.estoque.index`; MenuLateral Almoxarife atualizado
- Migrations: `saldos_estoque` e `movimentacoes_estoque`

**Fora do escopo F7 (v1.1+):**
- UI para saída manual e ajuste de inventário (actions existem, sem tela ainda)
- Lote/validade, PEPS, transferência entre depósitos, ressuprimento automático
- **Catálogo de itens (v1.1):** a identidade por `descricao_normalizada` é pragmática para v1; a v1.1 introduzirá `catalogo_itens` com UUID e exigirá reconciliação de saldos existentes (migração de chave por semelhança de texto + aprovação manual)

**Correções de segurança aplicadas:**
- P0: `Auditavel` adicionado em `MovimentacaoEstoque` (ledger financeiro exige trilha)
- P1: guarda de unidade em `SaidaEstoqueAction` e `AjusteEstoqueAction` (`wherePivot Almoxarife`)
- P1: `withoutGlobalScopes()` nos re-locks internos das actions (auth context não garantido em queue/job futuro)

**Dependências:** Fase 6 concluída (`RegistrarRecebimentoAction` é o ponto de entrada de estoque)

---

### Fase 8 — Relatórios v1 ✅ IMPLEMENTADA (142 testes, Pint limpo, sec + QA aprovados)
**Objetivo:** entregar as quatro visões de dados aprovadas para o v1.

**Status:** 142/142 testes passando (126 Fases 0–7 + 16 Fase 8). Pint limpo. npm build OK. sec + QA aprovados.

**Pronto:**
- Livewire `GastosCentroCusto` — R1: agrega SUM(valor_total) por CC com filtro ano/mês; acesso via `podeVerTodasUnidades()`
- Livewire `RequisicoesAprovador` — R2: snapshot atual de aprovações pendentes do ciclo vigente via `JOIN ON a.ciclo = r.ciclo_aprovacao`
- Livewire `CustoObra` — R3: custo comprometido (PC emitido) por obra × mês com curva acumulada; `strftime('%m', pc.emitido_em)` SQLite-native; verba nullable tratada com `!== null`
- Livewire `ComprasEmergenciais` — R4: cascata PC emitido > cotação vencedora (`MAX(valor) GROUP BY`) > estimativa via `COALESCE`; filtro ano + mês atual por padrão
- 4 views Blade com empty state explícito, tabelas com totais e badges de alerta
- MenuLateral: 4 links de relatórios visíveis para Admin e CompradoraSenior
- Rotas: `/relatorios/gastos-cc`, `/relatorios/pendentes-aprovador`, `/relatorios/custo-obra`, `/relatorios/emergenciais`

**Correções de segurança/QA aplicadas:**
- Sec P1: `whereNull('un.deleted_at')` adicionado em `CustoObra` (unidades têm SoftDeletes; `DB::table()` ignora scope global)
- QA BUG-04 (ALTO): `cot_val` subquery em R4 agora usa `MAX(valor) GROUP BY requisicao_id` — evita fan-out se houver duas cotações vencedoras para a mesma requisição
- QA BUG-03 (BAIXO): verba `0.0` tratada com `!== null` em vez de cast falsy `? :` em `CustoObra`
- Bug corrigido antes dos testes: `requisicao_itens` não tem SoftDeletes — cláusula `deleted_at IS NULL` removida da subquery `est_val`

**Decisões de escopo:**
- `abort_unless(podeVerTodasUnidades(), 403)` em mount() + render() é dupla guarda suficiente; middleware de rota fica como P2/backlog
- R1 usa INNER JOIN em `centro_custo_id` (intencional: relatório de CC pressupõe CC obrigatório em requisições emitidas)
- R4 default: mês atual (não ano inteiro) para focar em compras recentes

**Dependências:** Fases 1 a 7 concluídas (dados precisam existir e estar populados)

---

### Fase v1.1-A — Catálogo de Itens + Reconciliação de Saldos ✅ IMPLEMENTADA (186 testes, Pint limpo, sec + QA aprovados)
**Objetivo:** introduzir catálogo de itens centralizado com UUID e migrar a identidade do estoque (hoje por `descricao_normalizada`) para `item_catalogo_id`, sem regressão sobre o estoque já em produção. Lote/validade fica para v1.1-B.

**Status:** 186/186 testes passando (181 v1.1-A iniciais + 5 de correção; sobre os 142 da v1). Pint limpo. sec + QA aprovados com ressalvas — P1s e bugs ALTO/MÉDIO corrigidos.

**Pronto:**
- Model `CatalogoItem` (`catalogo_itens`, Auditavel, SoftDeletes, UUID gerado no `creating()`, global — sem PertenceAUnidade, como Fornecedor)
- Migrations (4, todas aditivas/não-destrutivas): `create_catalogo_itens_table`; `item_catalogo_id` + `avulso` em `requisicao_itens` e `itens_pedido_compra`; `item_catalogo_id` em `saldos_estoque` (UNIQUE legado preservado)
- Factory `CatalogoItemFactory` + `CatalogoItemSeeder` (itens de exemplo), registrado no `DatabaseSeeder`
- Livewire `Admin\CatalogoItens\ListaCatalogoItens` — CRUD só-Admin (espelha `ListaFornecedores`)
- Livewire `Admin\CatalogoItens\ReconciliacaoSaldos` — só-Admin, vincula saldos existentes ao catálogo
- `SugerirVinculoCatalogoAction` — sugestão por similaridade (pré-filtro LIKE + `similar_text` + Jaccard de tokens), 3 faixas de confiança (alta ≥0.85, media ≥0.60, baixa abaixo); sem extensão SQLite
- `ConfirmarVinculoSaldoAction` — `vincular`/`desvincular`: só `item_catalogo_id`, NUNCA toca quantidade/CMP/valor_total; idempotente, reversível, bloqueia colisão de identidade
- `EntradaEstoqueAction` — identidade dual: item com catálogo agrupa por `item_catalogo_id`; avulso preserva comportamento v1 por `descricao_normalizada` (+ `whereNull('item_catalogo_id')`)
- `FormularioRequisicao` — campos `item_catalogo_id`/`avulso`; item avulso (descrição livre) continua aceito; validação rejeita item inativo/soft-deleted server-side
- `CriarRascunhoPedidoAction` — propaga `item_catalogo_id`/`avulso` da requisição → pedido
- Rotas `/admin/catalogo-itens` e `/admin/reconciliacao-saldos` (grupo AdminMiddleware); links no MenuLateral (Admin)

**Correções de segurança/QA aplicadas:**
- Sec P1: `Auditavel` em `ItemRequisicao`/`ItemPedidoCompra`; escape de metacaracteres LIKE no `SugerirVinculoCatalogoAction`; `abort_unless` em `abrirEditar()`/`fecharVinculoManual()`
- QA BUG-01 (ALTO): soft-delete de `CatalogoItem` com saldos vinculados agora bloqueado (evita órfão irrecuperável)
- QA BUG-02 (MÉDIO): validação de requisição rejeita item de catálogo inativo (`->where('ativo', true)`)
- Sec P2-03: mensagem de erro PT-BR na reconciliação (extrai `errors()` em vez de `getMessage()` genérico)

**Decisões de escopo / backlog (v1.1-B):**
- Catálogo global cadastrado por Admin; item avulso permitido (flag `avulso=true`); reconciliação só-Admin
- Fusão de saldos (dois saldos avulsos → mesmo catálogo) é bloqueada, não fundida — fica para v1.1-B
- UNIQUE de `(unidade_id, deposito, item_catalogo_id)` no banco fica na lógica PHP (mesmo padrão de `fornecedores`/`centros_custo`); índice DB + race condition → v1.1-B
- Paginação do catálogo no `FormularioRequisicao` (Sec P2-02) e guard interno do `SugerirVinculoCatalogoAction` (QA BUG-04) → backlog
- **Lote/validade + FEFO → v1.1-B** (sub-fase separada, depende deste catálogo estável)

**Dependências:** Fases 1 a 7 concluídas (catálogo se integra a requisição, pedido e estoque)

---

### Fatia Estoque — Saída de Material (RIM) + Atendimento Direto + Inventário ✅ IMPLEMENTADA (270 testes, Pint limpo, sec + QA aplicados)
**Objetivo:** entregar o fluxo operacional de saída de material via Requisição Interna de Material (RIM), inventário físico com ajustes automáticos e atendimento direto pela Compradora Sênior sem geração de Pedido de Compra. (Cobre as pendências #1 e #3 do backlog de v1. NÃO confundir com v1.1-C, que é lote/validade.)

**Status:** 270/270 testes passando (211 anteriores + 59 novos). Pint limpo. sec + QA revisaram (REPROVADO inicial pelo BUG-01 → corrigido e reaprovado).

**Pronto:**
- Enums: `StatusRequisicaoMaterial` (Aberta/Atendida/Recusada + `label()` + `ehTerminal()`), `StatusInventario` (EmAndamento/Concluido/Cancelado + `label()`)
- Migrations (4, aditivas): `requisicoes_material`, `sessoes_inventario`, `itens_inventario`, `add_requisicao_material_id_to_movimentacoes_estoque`
- Models: `RequisicaoMaterial` (Auditavel), `SessaoInventario` (Auditavel), `ItemInventario` (Auditavel + accessor `divergencia`), `MovimentacaoEstoque` ← relação `requisicaoMaterial()` + fillable `requisicao_material_id`
- Factories: `RequisicaoMaterialFactory`, `SessaoInventarioFactory`, `ItemInventarioFactory`, `SaldoEstoqueFactory`
- `AtenderRequisicaoMaterialAction`: valida status Aberta + almoxarife-da-unidade, chama `SaidaEstoqueAction`, vincula movimentação à RIM via `requisicao_material_id`
- `RecusarRequisicaoMaterialAction`: valida status Aberta + motivo + almoxarife-da-unidade, sem movimentação de estoque
- `AbrirSessaoInventarioAction`: valida perfil Admin|Almoxarife da unidade, guarda contra sessão duplicada (unidade+depósito), snapshot de saldos excluindo tombstones (`fundido_para_id IS NULL`)
- `AplicarInventarioAction`: exige todos os itens contados + justificativa não-vazia, gera ajustes positivos/negativos por divergência, rollback total em falha
- `CancelarSessaoInventarioAction`: status Cancelado, sem movimentações
- B1 — `SaidaEstoqueAction` com contexto `$atendimentoDireto`: Almoxarife-da-unidade (sempre) OU Admin (sempre) OU CompradoraSenior SOMENTE em atendimento direto (sem passe livre fora do fluxo)
- `TransicionarStatusRequisicaoAction`: novas transições `AguardandoTriagem → Concluida` e `EmTriagem → Concluida`
- Livewire `Solicitante\RequisicoesMaterial`: abre RIM selecionando saldo da unidade, lista com status+motivo
- Livewire `Almoxarife\AtendimentoRequisicoesMaterial`: lista RIMs abertas da(s) unidade(s) do almoxarife, atender/recusar com captura de ValidationException
- Livewire `Almoxarife\Inventario`: abertura de sessão (depósito opcional), conferência com cálculo live de divergência, modal de justificativa para aplicação, cancelamento
- `TriagemRequisicoes` — `atenderDoEstoque(id)`: bloqueia itens avulsos, resolve saldo por `item_catalogo_id`, transação única baixando cada item + conclusão da requisição
- Rotas: `solicitante.rim.index`, `almoxarife.rim.index`, `almoxarife.inventario.index`
- MenuLateral: "Requisições de Material" (Solicitante), "Atendimento de Material" e "Inventário" (Almoxarife)

**Correções sec/QA aplicadas (REPROVADO inicial → resolvido):**
- BUG-01 (ALTO): `AjusteEstoqueAction` não autorizava Admin → inventário com divergência falhava para Admin. Corrigido (Admin OU Almoxarife-da-unidade) + teste de regressão com divergência real (o teste antigo usava divergência zero e mascarava o bug)
- P1: histórico de inventário agora filtra por unidade (Almoxarife não vê sessões de outras unidades); abertura de sessão com `lockForUpdate` na unidade (anti-TOCTOU); `quantidade_contada` negativa rejeitada server-side; guarda B1 apertada para contexto
- Cobertura nova: rollback total do atendimento direto multi-item; rollback total do inventário (ajuste excede saldo real); solicitante não cria RIM de outra unidade

**Decisões fechadas:**
- RIM sem aprovação (3 status: aberta → atendida | recusada)
- Inventário suporta depósito específico (default) ou unidade inteira (deposito nullable)
- Notificação ao solicitante in-app (status visível na própria tela), sem e-mail nesta fatia
- Atendimento direto bloqueia itens avulsos (apenas itens de catálogo com saldo)

**Fora desta fatia (backlog):**
- `estoque_minimo` + alerta de ressuprimento (coluna não existe ainda)
- Lote/validade + FEFO (v1.1-C); rateio da central; transferência entre unidades
- P2 (go-live): dropdown de seleção de unidade no inventário do Admin (hoje usa `Unidade::first()`); remover `almoxarife_id`/`movimentacao_estoque_id` do fillable (setados só por action)

**Dependências:** v1.1-B concluída (fusão de saldos + UNIQUE); Fase 7 (SaidaEstoqueAction/AjusteEstoqueAction)

---

### Fase v1.1-B — Fusão de Saldos + UNIQUE/Race (EM ANDAMENTO)
**Objetivo:** fundir saldos duplicados de catálogo e garantir unicidade de identidade no banco. (Lote/validade + FEFO foi separado para v1.1-C.)

**Passos:** 0 (guard Sugerir + paginação catálogo) · 1 (FusaoSaldosAction + enum Fusao + tombstone/log) · 2 (comando `estoque:sanear-duplicatas-catalogo`) · 3 (UNIQUE parcial + catch de QueryException).

**⚠️ PONTO CEGO PARA O SEC+QA — validar antes do go-live:**
- A migration `add_unique_catalogo_to_saldos_estoque` é **driver-aware**: SQLite usa índice UNIQUE **parcial** (`WHERE item_catalogo_id IS NOT NULL AND fundido_para_id IS NULL`); MySQL/MariaDB (produção) usa **coluna gerada STORED** (`catalogo_chave_unica`, NULL fora do escopo) + UNIQUE sobre ela.
- **A suíte de testes roda SÓ em SQLite.** O caminho MySQL — que é o de produção — **NÃO é exercitado por nenhum teste automatizado.** Antes do deploy é obrigatório validar num MySQL real: (a) `migrate` cria a coluna gerada + índice; (b) insert de duas linhas ativas com mesma `(unidade_id, deposito, item_catalogo_id)` é barrado; (c) avulsos (catálogo NULL) e tombstones (fundido_para_id != NULL) coexistem sem colidir.
- **ORDEM DE DEPLOY OBRIGATÓRIA (MySQL real):**
  1. `php artisan migrate` até o **Passo 2** (inclusive) — NÃO aplicar ainda a migration do UNIQUE.
  2. Rodar `estoque:sanear-duplicatas-catalogo --dry-run` para auditar, depois `--executado-por=<id Admin>` para fundir as duplicatas legadas.
  3. Só então `php artisan migrate` o **Passo 3** (cria o UNIQUE/coluna gerada).
  Se a constraint do Passo 3 subir ANTES do saneamento num banco com duplicatas, a criação do índice **falha** e o deploy trava. Essa ordem é mandatória — não inverter.
- O catch de `QueryException` discrimina por `errorInfo[1]` (19 SQLite / 1062 MySQL) + nome da constraint; a degradação para UPDATE foi testada só em SQLite (statement-level rollback). Em MySQL a transação aborta na violação — confirmar o comportamento do retry em MySQL real.

---

## Sequência de execução

```
Fase 0 (Fundação)
    → Fase 1 (Cadastros Base)
        → Fase 2 (Requisição + Obras)
            → Fase 3 (Cotação)
                → Fase 4 (Aprovação)
                    → Fase 5 (Pedido de Compra)
                        → Fase 6 (Recebimento)
                            → Fase 7 (Estoque)
                                → Fase 8 (Relatórios)
```

Todas as fases são estritamente sequenciais — não há paralelismo seguro porque cada fase consome entidades criadas na fase anterior.

---

## Riscos globais do projeto

| # | Risco | Impacto | Mitigação |
|---|-------|---------|-----------|
| 1 | Modelagem de alçadas errada na Fase 1 | Quebra o fluxo de aprovação inteiro e exige refatoração de banco em fases avançadas | Revisar e aprovar o modelo de dados com o PM antes de executar a migration da Fase 1 |
| 2 | Regras de negócio não mapeadas emergem no meio da implementação (ex.: dupla aprovação, escalada de verba) | Gera retrabalho e pode atrasar 1 a 2 fases | Antes de cada fase, o PM valida um checklist de cenários-borda com o solicitante da regra |
| 3 | Prazo total extrapolando 2 semanas | Escopo v1 completo é grande para uma squad pequena | Se estimativas individuais somadas passarem de 10 dias úteis, cortar Relatórios (Fase 7) para v1.1 e alinhar com PM |

---

## Marcos verificáveis

| Marco | Fase | Entregável concreto |
|-------|------|---------------------|
| M0 | Fase 0 | Login funcional com 5 perfis; middleware de unidade bloqueando acesso cruzado em teste automatizado |
| M1 | Fase 1 | CRUD completo de Unidades, Usuários, Fornecedores, Centros de Custo/Obras, Alçadas — com seeds de dados de exemplo |
| M2 | Fase 2 | Solicitante consegue abrir requisição; sistema alerta e bloqueia ao atingir 80%/100% da verba da obra |
| M3 | Fase 3 | Compradora registra 3 cotações com anexo; sistema bloqueia avanço se mínimo não atingido |
| M4 | Fase 4 | Requisição percorre ciclo completo de aprovação dupla e rejeição com e-mail disparado em cada transição |
| M5 | Fase 5 | PDF do Pedido de Compra gerado com número PC-AAAA-NNNN; múltiplas requisições agrupadas num único pedido |
| M6 | Fase 6 | Pedido recebe dois recebimentos parciais e fecha como "Recebido Totalmente"; entrada automática de estoque disparada |
| M7 | Fase 7 | CMP recalculado corretamente após dois lotes de custo distinto; saída reduz saldo pelo CMP vigente sem alterá-lo; saldo negativo bloqueado |
| M8 | Fase 8 | Quatro relatórios renderizados com dados reais de seeds; custo acumulado por obra exibe curva mensal correta; relatório emergencial lista compras com flag "emergencial" agrupadas por unidade/solicitante |

---

## Pendências reais de v1 (backlog priorizado)

O PLANO original tratou "Fases 0–8 = v1 completa", mas o ESCOPO.md define um v1 mais amplo.
Em "Pontos em aberto", o dono respondeu **lote/validade = v1 (#10)** e **rateio da central = v1 (#12)** —
itens que estavam erroneamente em "Fora da v1". Além disso, várias funções do módulo de Estoque
ficaram só com Action (lógica), sem tela/fluxo. Lista em ordem de criticidade operacional:

| # | Pendência de v1 | Status | Observação |
|---|-----------------|--------|------------|
| 1 | **Estoque — Saída de material** (requisição interna) + **Inventário** + **Atendimento direto Compradora** | ✅ IMPLEMENTADA (52 testes novos) | RIM (Aberta→Atendida/Recusada), sessão de inventário com snapshot+ajustes, atendimento direto pela Compradora via `SaidaEstoqueAction` relaxada (B1) |
| 2 | **Estoque mínimo + alerta de ressuprimento** | 🟠 não-iniciado | Coluna `estoque_minimo` por unidade **NÃO existe** (precisa migration); + alerta + sugestão de requisição |
| 4 | **Lote/validade + FEFO** (cervejaria) | 🟠 não-iniciado | ESCOPO #10 = v1. Planejado como v1.1-C |
| 5 | **Rateio da central** entre unidades | 🟠 não-iniciado | ESCOPO #12 = v1. Indefinido — exige PRD do PM antes de codar |
| 6 | **Transferência entre unidades** | 🟡 não-iniciado | Sem aprovação (#8); entidade própria + reconciliação entre unidades |
| 7 | **Relatórios faltantes** (5 de 8) | 🟡 parcial | Faltam: gasto por fornecedor/categoria; tempo médio de aprovação; posição de estoque; consumo por CC/unidade; comparativo entre unidades |
| 8 | **Lembrete diário de pendências +48h** | 🟢 não-iniciado | Notificação por e-mail |
| 9 | **Campos da cotação** (prazo de entrega, validade da proposta) | 🟢 parcial | ESCOPO exige por cotação; hoje não capturados na cotação |

**Atendimento direto do estoque pela Compradora** (saída sem compra) sai junto do item 1 (depende da Saída).

---

## Fora da v1 (não planejar, não implementar)

(Alinhado ao ESCOPO.md, seção "Fora de escopo (v1)".)

- Integração com ERP / contabilidade
- Pagamento e financeiro (contas a pagar)
- App mobile nativo
- Portal do fornecedor
- Contratos recorrentes
- Compra de combustível de pista dos postos (processo próprio — ESCOPO #11)
- Localização do item no estoque (prateleira/bin) — cosmético, pós-v1

> Catálogo de itens centralizado saiu desta lista: **já implementado** em v1.1-A.
