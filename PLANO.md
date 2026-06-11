# PLANO — Sistema de Gestão de Compras v1
# Rede Comendador

**Última atualização:** 2026-06-11
**Status geral:** Fase 1 implementada — aguardando início Fase 2
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

### Fase 2 — Requisição de Compra + Módulo de Obras
**Objetivo:** permitir que solicitantes abram requisições e que o sistema aplique as regras de verba de obra automaticamente.

**O que é entregue:**
- Formulário de Requisição com todos os campos (descrição, quantidade, urgência, centro de custo / obra, unidade)
- Validação de verba por obra: alerta em 80% e bloqueio com escalada automática de aprovação ao atingir 100%
- Log de status na criação da requisição
- Visibilidade filtrada por unidade do solicitante

**Dependências:** Fase 1 concluída (Obras e Verbas precisam existir; Alçadas precisam existir para a escalada automática)

**Risco principal:** regra de escalada automática ao estourar verba de obra envolve lógica cruzada entre Verbas e Alçadas — pode gerar casos-borda não mapeados. Mitigação: mapear cenários de teste com o PM antes de implementar a escalada.

---

### Fase 3 — Cotação
**Objetivo:** registrar as cotações recebidas para cada requisição e controlar o mínimo obrigatório por faixa de valor.

**O que é entregue:**
- Registro de cotações vinculadas à requisição (fornecedor, valor, anexo obrigatório)
- Regra de mínimo de cotações por faixa de valor (configurada nas Alçadas)
- Validação de que o fornecedor está homologado (fornecedor novo exige fluxo separado no cadastro, não inline aqui)
- **Fluxo de compra emergencial:** requisição pode ser marcada como emergencial; exige 1 cotação no ato + justificativa obrigatória; flag "emergencial" permanente no registro; prazo de 24h para registro de cotações complementares
- Log de status ao registrar cotação

**Dependências:** Fase 2 concluída (requisição precisa existir); Fase 1 concluída (fornecedores e alçadas)

**Risco principal:** armazenamento de anexos (upload de arquivos) pode ter comportamento diferente em produção vs. SQLite local. Mitigação: definir storage local para dev e cloud para produção antes de implementar o upload.

---

### Fase 4 — Aprovação
**Objetivo:** implementar o fluxo de aprovação manual com dupla aprovação, notificação por e-mail e retorno para a Compradora em caso de rejeição.

**O que é entregue:**
- Roteamento da requisição pelas etapas ordenadas da alçada (etapa 1 → etapa 2 → … sem pular)
- Emergencial: aprovação obrigatória do Diretor independente do valor; etapas superiores seguem normalmente
- Regra: aprovador não pode aprovar a própria requisição
- Aprovação e rejeição com justificativa obrigatória
- Rejeição retorna a requisição para a Compradora com notificação
- Notificações de mudança de status por e-mail (aprovado, rejeitado, aguardando aprovação)
- Log de cada transição de status

**Dependências:** Fase 3 concluída (cotação mínima precisa estar satisfeita antes de entrar em aprovação); Fase 0 (perfis e log)

**Risco principal:** fluxo de dupla aprovação tem estados intermediários (aguardando Diretor, aguardando CEO, um rejeitou) que podem não estar completamente especificados. Mitigação: desenhar o diagrama de estados completo com o PM antes de implementar.

---

### Fase 5 — Pedido de Compra
**Objetivo:** gerar o Pedido de Compra formal a partir de requisições aprovadas, incluindo PDF e número sequencial.

**O que é entregue:**
- Criação do Pedido de Compra com número sequencial PC-AAAA-NNNN
- Vínculo com o fornecedor vencedor da cotação
- Campos: prazo de entrega, modalidade de entrega
- Agrupamento de requisições num único pedido pela Compradora
- Geração de PDF do Pedido de Compra
- Log de status na criação do pedido
- Notificação de status por e-mail ao solicitante

**Dependências:** Fase 4 concluída (só requisições aprovadas viram pedido)

**Risco principal:** geração de PDF pode demandar biblioteca ou serviço externo não previsto. Mitigação: definir a solução de PDF (ex.: DomPDF já disponível no ecossistema Laravel) antes de iniciar a fase.

---

### Fase 6 — Recebimento
**Objetivo:** registrar o recebimento das mercadorias, total ou parcial, sem integração com estoque.

**O que é entregue:**
- Registro de recebimento vinculado ao Pedido de Compra
- Status: Recebido Totalmente / Recebido Parcialmente / Pendente
- Múltiplos recebimentos parciais até completar o pedido
- **Critério de parcial:** por quantidade, item a item; pedido fecha quando todas as quantidades forem recebidas (decisão GP-2)
- Log de cada recebimento
- Notificação de status por e-mail

**Dependências:** Fase 5 concluída (pedido precisa existir)

**Risco principal:** pedidos com itens para unidades diferentes podem ter recebimentos registrados por almoxarifes distintos — validar se o controle de "recebido por item" precisa de permissão por unidade de destino.

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
