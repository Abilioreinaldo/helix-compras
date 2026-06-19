# PLANO — Sistema de Gestão de Compras v1
# Rede Comendador

**Última atualização:** 2026-06-18
**Status geral:** Fases 0–8 + v1.1-A (catálogo) + v1.1-B (fusão/UNIQUE) + RIM/Inventário/Atendimento direto + Estoque mínimo + Relatórios #7 (R1–R5) + **v1.1-C (lote/validade+FEFO)** + **Rateio da central** + **validade da proposta na cotação (#9)** implementados (os três com sec/QA / rito conforme risco). **440 testes verdes.** **v1 quase completa** — faltam só: **transferência entre unidades (#6)** e **lembrete diário +48h (#8)**. Ver "Pendências reais de v1" abaixo.
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

### Fatia Estoque — Estoque Mínimo + Alerta de Ressuprimento ✅ IMPLEMENTADA (45 testes novos, 315 total, sec + QA aplicados)
**Objetivo:** definir quantidade mínima por (unidade × item de catálogo), alertar quando o saldo ativo cair abaixo do mínimo e sugerir reposição via formulário de requisição pré-preenchido.

**Status:** 315/315 testes passando (270 anteriores + 45 novos). Pint limpo. sec + QA aprovados com ressalvas — 4 P1 corrigidos.

**Pronto:**
- Migration `estoque_minimos` (aditiva): FK unidade_id + FK item_catalogo_id; decimal(15,3); UNIQUE(unidade_id, item_catalogo_id); index(item_catalogo_id); sem SoftDeletes
- Model `EstoqueMinimo` (Auditavel, HasFactory): relações `unidade()` e `catalogoItem()`; cast `quantidade_minima => decimal:3`
- `CatalogoItem::estoqueMinimos()` — HasMany
- `EstoqueMinimoFactory` (quantidade_minima sempre > 0)
- `DefinirEstoqueMinimoAction::execute(Unidade, CatalogoItem, float, User)`:
  - Guard: Admin OU Almoxarife-da-unidade (pivot); senão ValidationException
  - Item inativo ou soft-deletado: ValidationException
  - quantidadeMinima ≤ 0: deleta registro e retorna null
  - quantidadeMinima > 0: DB::transaction + lockForUpdate + updateOrCreate; catch QueryException UNIQUE → relê e atualiza
- `EstoqueMinimo::itensAReporPara(User)` — visibilidade por papel; query com LEFT JOIN somando saldos ativos (fundido_para_id IS NULL, item_catalogo_id NOT NULL); COALESCE(saldo, 0) < mínimo (estrito); `quantidade_sugerida` calculada em PHP
- `EstoqueMinimo::itemCatalogoIdsEmAlerta(array $unidadeIds)` — array de IDs em alerta para badge nas linhas
- `SaldosEstoque` Livewire: badge "Abaixo do mínimo" nas linhas com item de catálogo em alerta; painel "Itens a repor"; modal "Definir mínimo" (botão só em itens de catálogo); `salvarMinimo()` com guard Almoxarife e captura ValidationException
- `ListaCatalogoItens` Livewire (Admin): botão "Mínimos" por item → modal lista todas unidades ativas com input; `salvarMinimoUnidade(unidadeId)` com guard Admin
- `App\Livewire\Compradora\ItensARepor`: mount+render abort_unless podeVerTodasUnidades(); lista da rede agrupada por unidade; filtro por unidade + busca; `solicitarReposicao()` → redirect com query params
- Rota `GET /compradora/itens-a-repor` → name `compradora.itens-a-repor`; link no MenuLateral condicionado a Admin|Compradora
- `FormularioRequisicao::mount()` (somente ramo rascunho novo): lê query params `item_catalogo_id`, `unidade_id`, `quantidade_sugerida`; pré-preenche item de catálogo se válido e ativo; unidade só aceita se visível ao usuário; sem query = comportamento anterior inalterado

**Correções sec/QA aplicadas (APROVADO COM RESSALVAS → resolvido):**
- P1: `abrirModalMinimo` agora restringe o saldo às unidades do almoxarife (não vaza dados de outra unidade); validação `exists` de unidade/item exclui soft-deletados; `solicitarReposicao` valida que a combinação está mesmo em alerta + clampa a quantidade; delete do mínimo (=0) via instância (dispara Auditavel)
- Cobertura nova: redirect de reposição forjado é barrado (404); sugestão fracionária <1 vira 1; item inativo no query param cai no avulso

**Decisões fechadas:**
- Mínimo = 0 remove registro; definir exige > 0
- Alerta estrito: saldo < mínimo (igual não alerta)
- Visibilidade: Almoxarife = própria(s) unidade(s); Compradora + Admin = rede inteira
- Painel oculta catálogo inativo/soft-deletado e unidades soft-deletadas
- Lógica de leitura = métodos estáticos no model `EstoqueMinimo` (sem Service)
- Botão "Solicitar" NÃO cria requisição — apenas redireciona com query

**Dependências:** v1.1-A (catálogo), v1.1-B (UNIQUE saldos), Fatia RIM/Inventário

---

### Fase v1.1-B — Fusão de Saldos + UNIQUE/Race (EM ANDAMENTO)
**Objetivo:** fundir saldos duplicados de catálogo e garantir unicidade de identidade no banco. (Lote/validade + FEFO foi separado para v1.1-C.)

**Passos:** 0 (guard Sugerir + paginação catálogo) · 1 (FusaoSaldosAction + enum Fusao + tombstone/log) · 2 (comando `estoque:sanear-duplicatas-catalogo`) · 3 (UNIQUE parcial + catch de QueryException).

**⚠️ Validação MySQL:** este passo tem migration driver-aware (índice parcial vs coluna gerada STORED), ordem de deploy obrigatória do UNIQUE e catch `errorInfo` 19/1062 — tudo consolidado na seção **"Checklist de validação MySQL pré-go-live"** (itens A2, A3, C8).

---

### Fatia Relatórios faltantes (#7) — R1–R5 ✅ CONCLUÍDA (espelha Fase 8, 342 testes verdes)

Relatórios complementares aos 4 da Fase 8. Cada um: componente Livewire + view + rota + link no menu + teste, commitado individualmente. Decisões de PRD: R1 categoria do fornecedor; R2 tempo do ciclo (aprovada_em − aprovacao_iniciada_em) por faixa de alçada; R4 consumo por unidade; R5 gasto pela unidade da requisição. Todos no GitHub.

- **R1 — Gastos por Fornecedor/Categoria** ✅ commit `40c5c7d`: `SUM(ipc.valor_total)` de PC emitido agrupado por fornecedor (com coluna categoria) ou por categoria (`COALESCE → 'Sem categoria'`); toggle de agrupamento; filtros ano/mês.
- **R2 — Tempo Médio de Aprovação por faixa de alçada** ✅ commits `70b4fcf` (+ `0fc8fde` rota): `AVG` da duração do ciclo em horas, agrupada por faixa. Só ciclos completos (status Aprovada + `aprovacao_iniciada_em` e `aprovada_em` não-nulos) — `whereNotNull` exclui ciclo aberto e evita subtração com nulo. Driver-aware (ver checklist B4).
- **R3 — Posição de Estoque** ✅ commit `c9c9854`: reusa `EstoqueMinimo::posicaoEstoquePara()` com tombstone `fundido_para_id IS NULL` (saldo fundido não conta); flag de alerta por mínimo; filtro de unidade.
- **R4 — Consumo por Unidade** ✅ commit `10d7d6c`: `SUM(valor_total)` das saídas (`movimentacoes_estoque.tipo = 'saida'`) agrupado pela unidade do saldo de origem; só `saida` conta.
- **R5 — Comparativo entre Unidades** ✅ commit `a3805ee`: gasto (PC emitido) agrupado pela **unidade da requisição** (`r.unidade_id`), não a do pedido; nº requisições/pedidos, total, gasto médio/req, % do total.

**⚠️ Validação MySQL:** a query de duração do R2 (`TempoAprovacao`) é driver-aware (`julianday`/`TIMESTAMPDIFF`) — consolidado na seção **"Checklist de validação MySQL pré-go-live"** (item B4).

---

### Fase v1.1-C — Lote/Validade + FEFO 🚧 EM DESIGN (ESCOPO #10 = v1, cervejaria)

**Objetivo:** controle de lote e validade no estoque, opt-in por item. FEFO governa só a **quantidade física** que sai (primeiro a vencer); o **valor** sai pelo CMP do saldo agregado (NÃO é PEPS valorizado). Vencido = alerta, não bloqueia. Design validado pelo Tech Lead + sec (veredito CONDICIONAL: 10 P0 viram testes-adversários nos passos).

**Modelo de dados:**
- `lotes_estoque` (nova) pendurada no `SaldoEstoque` agregado (unidade/depósito herdados via `saldo_estoque_id` — não duplicar): `saldo_estoque_id` (FK restrict), `numero_lote`, `validade` (date NULL = sem validade), `quantidade` decimal(15,3), tombstone `fundido_para_id`/`fundido_em`. UNIQUE parcial `(saldo_estoque_id, numero_lote)` em `fundido_para_id IS NULL` (2º recebimento do mesmo lote **soma**, não duplica) — técnica driver-aware da `add_unique_catalogo`.
- `catalogo_itens.controla_lote` (bool, default false). `movimentacoes_estoque.lote_estoque_id` (nullable) — **obrigatório no `#[Fillable]`** senão é descartado silenciosamente.
- Models `LoteEstoque` (com `Auditavel`), relação `SaldoEstoque::lotes()`/`lotesVivos()`.
- **Invariante-mestra:** `controla_lote=true` ⇒ `SUM(lotes vivos.quantidade) == saldo.quantidade`.

**Ponto delicado — Saída FEFO:** o FEFO vive **dentro** da `SaidaEstoqueAction` (assinatura inalterada), guard logo após o relock: `controla_lote=false` cai no **bloco atual movido byte-a-byte** (os 8 testes do `SaidaEstoqueGuardaB1Test` seguem verdes); `true` entra no FEFO. Ordenação portável `ORDER BY (validade IS NULL), validade ASC, id ASC` (NULL por último sem `NULLS LAST`). Lock do saldo + de cada lote; consumo across lotes; 1 movimentação por lote (`custo_unitario = CMP do saldo`, `lote_estoque_id` setado); reverificação pós-lock + assert `SUM(lotes)==saldo` antes do commit; transação única (falha no N-ésimo lote reverte tudo); filtro **estrito por `saldo_estoque_id`** (nunca `item_catalogo_id` solto). Vencido marca alerta, nunca lança. Manter o FEFO dentro da action reusa o guard de perfil (Almoxarife-da-unidade / Admin / Compradora só em atendimento direto).

**Ponto delicado — opt-in `controla_lote`:** `LigarControleLoteAction`, **só Admin** (impacto transversal ao catálogo global). Saldo legado com `quantidade > 0` e zero lotes → **bloqueado**; ligar exige, na mesma transação, lote inicial cobrindo o saldo atual. `saldo == 0` → liga sem lote. Desligar com lotes de quantidade > 0 → também bloqueado.

**Passos (suíte verde entre cada — hoje 342):**
- **Passo 0** — Schema + models + factory (inerte): 3 migrations aditivas (`lotes_estoque`, `controla_lote`, `lote_estoque_id`), `LoteEstoque`, `LoteEstoqueFactory`, editar `CatalogoItem`/`SaldoEstoque`/`MovimentacaoEstoque`. `controla_lote` default false → estoque atual idêntico. **Adversário:** UNIQUE parcial rejeita duplicata viva, aceita após tombstone.
- **Passo 1** — `LigarControleLoteAction` + guard de opt-in (só Admin). **Adversários:** saldo>0 sem lote → ValidationException (flag continua false, nenhum lote criado); saldo==0 liga sem lote; já controla_lote → idempotente.
- **Passo 2** — `EntradaEstoqueAction` (params opcionais `numeroLote`/`validade`) credita/soma lote só quando controla_lote; `RegistrarRecebimentoAction` repassa. **Adversário:** item controla_lote sem numero_lote → ValidationException (não cria saldo órfão).
- **Passo 3** — `SelecaoFefoService` (leitura pura: ordenação, multi-lote, flag vencido). Nada consome ainda.
- **Passo 4 ⚠️ ISOLADO** — ramo FEFO na `SaidaEstoqueAction` (commit isolado; diff mostra ramo legado movido sem edição). Regressão obrigatória do `SaidaEstoqueGuardaB1Test` (8 verdes). **Adversários:** multi-lote debita o que vence primeiro; saldo insuficiente reverte tudo; lote vencido consumido com alerta.
- **Passo 5** — Ajuste/inventário com lote (ver Decisão 2). **Adversário:** ajuste mantém `SUM(lotes)==saldo`.
- **Passo 6** — UI Livewire (recebimento coleta lote/validade; toggle controla_lote no catálogo exigindo lote inicial; alerta de vencido na saída/triagem; coluna validade na posição de estoque).

**Decisões do dono (2026-06-18):**
1. **RIM multi-lote:** ✅ *implementado (correção pós-sec/QA do Passo 4).* `SaidaEstoqueAction::execute` recebe `?int $requisicaoMaterialId` e seta o vínculo em **TODAS** as movimentações geradas (uma por lote no FEFO), não só a âncora. `AtenderRequisicaoMaterialAction` passa `$rim->id` (removido o post-update da última movimentação). Teste `rim_multilote_vincula_requisicao_material_id_em_todas_as_movimentacoes`.
2. **Inventário com lote:** ✅ *confirmado pelo dono (Passo 5).* **BLOQUEAR** no v1, contagem/ajuste por lote → **v1.1-D**. Implementado: `AjusteEstoqueAction` recusa saldo `controla_lote` com ValidationException; `AbrirSessaoInventarioAction` **exclui** saldos `controla_lote` do snapshot (via `whereNotExists` na tabela `catalogo_itens`, portável); `AplicarInventarioAction` tem guard defensivo (recusa se um item virou `controla_lote` após o snapshot). Itens **sem** controla_lote: inventário como hoje. Helper `SaldoEstoque::controlaLote()` (withTrashed). Contagem por lote exigiria redesenhar `itens_inventario` com dimensão de lote (snapshot/divergência/ajuste por lote + UI) — fica para v1.1-D. Bloquear mantém `SUM(lotes)==saldo` trivialmente seguro.
3. **Fusão + lotes:** `FusaoSaldosAction` migra os lotes do tombstone para o destino (senão lote vivo em saldo morto); **BLOQUEAR** fusão quando há colisão de `numero_lote` entre origem e destino (não consolidar automático — validades podem divergir); **recusar fusão mista** (saldo com lote × saldo sem lote do mesmo item). Verificar `SUM(lotes destino)==destino.quantidade` antes do commit.

**Invariantes garantidas:** soma=saldo (controla_lote); saldo nunca negativo (clamp + tolerância 0.001); CMP inalterado pela mecânica de lote; controla_lote=false ⇒ comportamento byte-a-byte atual; opt-in impossível em saldo fantasma; vencido não bloqueia; ledger append-only (N movimentações por saída multi-lote).

**Portabilidade:** ver itens **D9–D11** no Checklist de validação MySQL pré-go-live.

---

## Checklist de validação MySQL pré-go-live

> **Por que esta seção existe:** a suíte de testes roda **só em SQLite**; produção é **MySQL**. Os itens abaixo têm comportamento ou sintaxe que diferem entre os dois dialetos e **nenhum é exercitado por teste automatizado**. Validar TODOS contra um MySQL real antes do deploy. Marcar `[x]` conforme validado.

### Pré-requisito de ambiente

- [ ] **Criar o banco de produção com `utf8mb4` / `utf8mb4_unicode_ci`.**
  - **O que validar:** charset e collation do banco e das colunas de texto buscadas.
  - **Como:** `CREATE DATABASE comendador CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`. Confere com `SHOW TABLE STATUS` e `SHOW FULL COLUMNS` nas colunas de busca (`descricao`, `descricao_normalizada`, `nome`, `codigo`, `cnpj`, `razao_social`). É a base do item C6.

### A. Migrations driver-aware

- [ ] **A1 — Enum `tipo` de `movimentacoes_estoque`** (`add_fusao_to_movimentacoes_estoque_tipo`).
  - **O que validar:** o `up()` altera o ENUM para incluir `'fusao'` via `ALTER TABLE ... MODIFY COLUMN tipo ENUM(...)` (ramo MySQL, diferente do swap de coluna TEXT do SQLite); o `down()` reverte sem `'fusao'` (assume zero linhas `'fusao'`).
  - **Como:** rodar `migrate` no MySQL; `SHOW COLUMNS FROM movimentacoes_estoque LIKE 'tipo'` deve listar os 5 valores; inserir uma movimentação `tipo='fusao'` e confirmar aceitação; testar `migrate:rollback` num banco sem linhas `'fusao'`.
- [ ] **A2 — UNIQUE de saldos de catálogo** (`add_unique_catalogo_to_saldos_estoque`): índice **parcial** (SQLite) vs **coluna gerada STORED `catalogo_chave_unica` + UNIQUE** (MySQL).
  - **O que validar:** `migrate` cria a coluna gerada e o índice; duas linhas **ativas** com mesma `(unidade_id, deposito, item_catalogo_id)` são barradas; avulsos (`item_catalogo_id` NULL) e tombstones (`fundido_para_id` != NULL) coexistem sem colidir.
  - **Como:** `SHOW CREATE TABLE saldos_estoque` (confere coluna gerada + UNIQUE); tentar inserir duplicata ativa → deve falhar com 1062; inserir 2 avulsos idênticos e 2 tombstones idênticos → devem passar.
- [ ] **A3 — Ordem de deploy obrigatória do UNIQUE (mandatória, não inverter):**
  1. `php artisan migrate` até o **Passo 2** (NÃO aplicar ainda a migration do UNIQUE).
  2. `estoque:sanear-duplicatas-catalogo --dry-run` para auditar → depois `--executado-por=<id Admin>` para fundir as duplicatas legadas.
  3. **Só então** `php artisan migrate` o **Passo 3** (cria o UNIQUE/coluna gerada).
  - **Por quê:** se a constraint do Passo 3 subir ANTES do saneamento num banco com duplicatas, a criação do índice **falha e o deploy trava**.
- [ ] **A4 — Enum `tipo` ampliado para rateio** (`add_rateio_tipos_to_movimentacoes_estoque`): adiciona `'rateio_central'` e `'desconto_rateio'` via `ALTER TABLE ... MODIFY COLUMN tipo ENUM(...)` (só MySQL; no SQLite a coluna já é TEXT puro desde A1 → no-op).
  - **Como:** `migrate` no MySQL; `SHOW COLUMNS FROM movimentacoes_estoque LIKE 'tipo'` deve listar os 7 valores; inserir movimentação `tipo='rateio_central'` com `saldo_estoque_id` NULL e confirmar aceitação.
- [ ] **A5 — `saldo_estoque_id` NULLABLE** (`make_saldo_estoque_id_nullable_on_movimentacoes_estoque`): `->change()` gera `MODIFY` no MySQL e rebuild no SQLite.
  - **O que validar:** após `migrate`, `SHOW CREATE TABLE movimentacoes_estoque` mostra `saldo_estoque_id BIGINT UNSIGNED NULL` com a **FK `saldos_estoque` preservada** e o índice intacto; inserir mov de rateio com NULL passa; entrada/saída reais continuam exigindo saldo (a app sempre passa). `down()` é irreversível com dados de rateio (documentado na migration).

### B. Relatórios driver-aware

> Para cada um: abrir logado como Compradora/Admin, confirmar que **não dá erro de SQL**, e que os números **batem com o SQLite** para os mesmos dados.

- [ ] **B4 — R2 `TempoAprovacao`**: `TIMESTAMPDIFF(SECOND, ...)/3600` (MySQL) vs `(julianday(...) - julianday(...)) * 24` (SQLite). `julianday()` não existe no MySQL.
  - **O que validar:** abre sem erro; média/mín/máx em horas conferem; `GROUP BY` faixa e ordenação por `valor_minimo` retornam as mesmas linhas.
- [ ] **B5 — `CustoObra`**: `DATE_FORMAT(pc.emitido_em, '%m')` (MySQL) vs `strftime('%m', ...)` (SQLite). Corrigido no commit `5d09b05` (antes era SQLite-only sem ramo).
  - **O que validar:** abre sem erro; a curva mensal aloca o valor no mês correto (01–12); acumulado e % de verba conferem.

### C. Comportamentais (não-quebras — validar semântica)

- [ ] **C6 — `like` + collation.** Coberto pelo pré-requisito de ambiente.
  - **O que validar:** busca nas telas (usuários, fornecedores, centros de custo, catálogo, saldos) continua **case-insensitive** no MySQL (no SQLite é por padrão; no MySQL depende da collation).
  - **Como:** com `utf8mb4_unicode_ci`, buscar termo em maiúsculas/minúsculas e com/sem acento e confirmar que retorna o esperado.
- [ ] **C7 — `insertOrIgnore` da sequência de PC** (`EmitirPedidoCompraAction`): `INSERT IGNORE` (MySQL) engole mais classes de erro que `INSERT OR IGNORE` (SQLite).
  - **O que validar:** a numeração `PC-AAAA-NNNN` sob concorrência silencia **apenas** a colisão de unicidade da sequência, não outros erros (FK/NOT NULL).
  - **Como:** emitir 2 PCs concorrentes no mesmo ano e confirmar sequência sem buraco nem duplicata.
- [ ] **C8 — Catch `errorInfo[1]` 19 (SQLite) / 1062 (MySQL)** (`DefinirEstoqueMinimoAction` + catch do UNIQUE de saldos do v1.1-B).
  - **O que validar:** violação UNIQUE em MySQL cai no ramo 1062 e degrada para UPDATE/relê corretamente. **Atenção:** em MySQL a transação **aborta** na violação (diferente do rollback statement-level do SQLite) — confirmar que o retry funciona.
  - **Como:** forçar corrida de `updateOrCreate` do mesmo `(unidade, item)` e confirmar resultado consistente sem exceção propagada.

### D. v1.1-C — Lote/Validade (quando implementado)

- [ ] **D9 — UNIQUE parcial de `lotes_estoque`** `(saldo_estoque_id, numero_lote)` em `fundido_para_id IS NULL`: índice parcial (SQLite) vs coluna gerada STORED + UNIQUE (MySQL), mesma técnica de A2.
  - **Como:** `SHOW CREATE TABLE lotes_estoque`; inserir duplicata viva → falha 1062; após tombstone, inserir mesmo `numero_lote` → passa.
- [ ] **D10 — Ordenação FEFO `(validade IS NULL), validade ASC, id ASC`** (NULL por último sem `NULLS LAST`).
  - **Como:** com lotes de validade NULL + datas, confirmar no MySQL que NULL sai por último e a saída debita o de menor validade primeiro.
- [ ] **D11 — Comparação de vencido `validade < hoje`** (date puro, sem `julianday`/`DATEDIFF`).
  - **Como:** lote com `validade` no passado é marcado vencido (alerta) no MySQL, sem erro de função e sem bloquear a saída.
- [ ] **D12 — Case-sensitivity de `numero_lote` no agrupamento de lote** (Passo 2, `EntradaEstoqueAction::creditarLote`): a busca do lote vivo usa `WHERE numero_lote = ?` e o UNIQUE parcial `(saldo_estoque_id, numero_lote)`. SQLite (binary) trata `'L-001'` ≠ `'l-001'` → **2 lotes**; MySQL com collation padrão `utf8mb4_unicode_ci` trata `'L-001'` = `'l-001'` → **1 lote (soma)**. A invariante `SUM(lotes vivos)==saldo` **não quebra** nos dois casos (ambos somam ao saldo); o que diverge é a granularidade dos lotes. **Decisão do dono pendente:** normalizar `numero_lote` (ex.: `upper(trim())`) na entrada para tornar determinístico entre bancos, ou aceitar a divergência.
  - **Como:** receber `'L-001'` e depois `'l-001'` no mesmo saldo; conferir `LoteEstoque::count()` (2 em SQLite, 1 em MySQL) e confirmar o comportamento desejado no MySQL real.

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
| 2 | **Estoque mínimo + alerta de ressuprimento** | ✅ IMPLEMENTADA (45 testes novos) | Tabela `estoque_minimos` (unidade × item catálogo); `DefinirEstoqueMinimoAction`; `EstoqueMinimo::itensAReporPara()` + `itemCatalogoIdsEmAlerta()`; badge e painel em `SaldosEstoque`; modal mínimos por unidade em `ListaCatalogoItens`; painel `Compradora\ItensARepor` com botão de sugestão de requisição; pré-preenchimento do `FormularioRequisicao` via query params |
| 4 | **Lote/validade + FEFO** (cervejaria) | ✅ IMPLEMENTADA + sec/QA | ESCOPO #10 = v1. Passos 0–6 (schema → opt-in → entrada com lote → FEFO service → ramo FEFO na saída → bloqueio ajuste/inventário → UI). Decisão 2 = bloquear inventário controla_lote. P0 RIM multi-lote corrigido. Pontos cegos MySQL D9–D12 + A4/A5 no checklist |
| 5 | **Rateio da central** entre unidades | ✅ IMPLEMENTADA + sec/QA | ESCOPO #12 = v1. Documental (não toca estoque); consumo proporcional, maior-resto, idempotente, reversão DescontoRateio, command + relatório Livewire. Pontos cegos MySQL A4/A5 |
| 6 | **Transferência entre unidades** | 🟡 não-iniciado | Sem aprovação (#8); entidade própria + reconciliação entre unidades |
| 7 | **Relatórios faltantes** (R1–R5) | ✅ CONCLUÍDA | R1 fornecedor/categoria, R2 tempo de aprovação, R3 posição de estoque, R4 consumo por unidade, R5 comparativo entre unidades — commits `40c5c7d`→`a3805ee`, no GitHub. Ver Fatia #7 acima |
| 8 | **Lembrete diário de pendências +48h** | 🟢 não-iniciado | Notificação por e-mail |
| 9 | **Campos da cotação** (prazo de entrega, validade da proposta) | ✅ IMPLEMENTADA | `prazo_entrega_dias` (já existia) + `validade_proposta` (date) capturados em `RegistrarCotacaoAction`/`GestaoCotacoes`; coluna na lista com indicador de "vencida" |

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



## D. Checklist MySQL Pré-Go-Live (v1.1-C)

- [x] **D9** — UNIQUE parcial lote: ✅ Índice `lotes_estoque_saldo_lote_unique` validado em MySQL 8.0.46
- [x] **D10** — Ordenação FEFO portável: ✅ `ORDER BY (validade IS NULL), validade ASC, id ASC` funciona em MySQL
- [x] **D11** — Vencido (validade < CURDATE()): ✅ Query funciona em MySQL
- [x] **D12** — Case-sensitivity `numero_lote`: ✅ Mantém `utf8mb4_unicode_ci` (case-insensitive, portável SQLite↔MySQL)

**Status v1.1-C:** ✅ **PRONTA PARA GO-LIVE**
- Passos 0-6 implementados e testados (406 testes verdes)
- Sec/QA completo
- Validação MySQL completa
