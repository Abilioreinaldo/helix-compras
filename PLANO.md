# PLANO — Sistema de Gestão de Compras v1
# Rede Comendador

**Última atualização:** 2026-06-15
**Status geral:** Fase 6 implementada — aguardando início Fase 7
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
**Objetivo:** registrar o recebimento das mercadorias, total ou parcial, sem integração com estoque.

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

### Fase 7 — Relatórios v1
**Objetivo:** entregar as três visões de dados aprovadas para o v1.

**O que é entregue:**
- Relatório: gasto por centro de custo (filtro mês/ano)
- Relatório: requisições pendentes por aprovador
- Relatório: custo acumulado por obra com curva mensal
- **Relatório: compras emergenciais por unidade e solicitante (mensal)** — emergência recorrente é sinal de falha de planejamento

**Dependências:** Fases 1 a 6 concluídas (dados precisam existir e estar populados)

**Risco principal:** performance das consultas com SQLite em dev pode não revelar problemas que aparecem em produção com volume real. Mitigação: indexar as colunas de filtro desde a modelagem e testar com seeds de volume.

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
                            → Fase 7 (Relatórios)
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
| M6 | Fase 6 | Pedido recebe dois recebimentos parciais e fecha como "Recebido Totalmente" sem tocar estoque |
| M7 | Fase 7 | Quatro relatórios renderizados com dados reais de seeds; custo acumulado por obra exibe curva mensal correta; relatório emergencial lista compras com flag "emergencial" agrupadas por unidade/solicitante |

---

## Fora da v1 (não planejar, não implementar)

- Estoque completo (módulo completo; lote/validade vai para v1.1)
- Rateio da central
- Relatórios por fornecedor, tempo médio, consumo, comparativo entre unidades
- Lembrete de 48h em notificações
- Atendimento direto da requisição pela Compradora (saída sem compra — bloqueado enquanto estoque não existir)
