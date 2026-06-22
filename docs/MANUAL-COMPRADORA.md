# Comendador Compras — Manual da Compradora

**Versão v1 · 22/06/2026 · Público: Compradoras e Gerentes de Unidade**

---

## Índice

1. [Visão geral](#1-visão-geral)
2. [Primeiro acesso](#2-primeiro-acesso)
3. [Passo a passo do fluxo](#3-passo-a-passo-do-fluxo)
   - 3.1 [Requisição](#31-requisição)
   - 3.2 [Triagem](#32-triagem)
   - 3.3 [Cotação](#33-cotação)
   - 3.4 [Aprovação](#34-aprovação)
   - 3.5 [Pedido de Compra](#35-pedido-de-compra)
   - 3.6 [Recebimento](#36-recebimento)
4. [Enviar Cotação por E-mail (sugestão automática)](#4-enviar-cotação-por-e-mail-sugestão-automática)
5. [Dicas & atalhos para acelerar o trabalho](#5-dicas--atalhos-para-acelerar-o-trabalho)
6. [FAQ — 15 perguntas comuns](#6-faq--15-perguntas-comuns)
7. [Quem contatar se algo der errado](#7-quem-contatar-se-algo-der-errado)

---

## 1. Visão geral

O **Comendador Compras** é o sistema de gestão de compras da Rede Comendador. Ele organiza todo o caminho que um pedido percorre — desde o momento em que alguém da equipe solicita um material até o instante em que o item entra no estoque da unidade. Nada de planilha perdida no e-mail, nada de aprovação boca-a-boca: cada etapa é registrada, rastreável e auditável. A compradora tem visibilidade total do que está pendente, qual fornecedor foi escolhido, quem ainda precisa aprovar e quanto foi gasto por centro de custo.

### Fluxo completo de uma compra

```
  [Solicitante]          [Compradora]              [Aprovador]         [Almoxarife]
       |                     |                         |                    |
  Abre a           Faz a triagem            Aprova ou               Registra o
  Requisição  -->  e lança a       -->      reprova a      -->      Recebimento
  (Rascunho)       Cotação                  compra                  no estoque
       |                |                      |                        |
  Aguardando       Em cotação /           Aprovada /              Recebida /
  triagem          Cotação concluída      Reprovada               Concluída
                        |
                   Emite Pedido
                   de Compra (PDF)
```

O sistema cuida de notificações, histórico de status e controle de alçada automaticamente — a compradora só precisa seguir o fluxo.

---

## 2. Primeiro acesso

### Login

Abra o sistema no navegador e informe seu e-mail e senha.

**Usuários de demonstração** (ambiente de testes):

| Perfil              | E-mail                              | Senha       |
|---------------------|--------------------------------------|-------------|
| Administrador       | admin@comendador.com.br              | senha@123   |
| Compradora Sênior   | compradora@comendador.com.br         | senha@123   |
| Diretor (Aprovador) | diretor@comendador.com.br            | senha@123   |
| Gestor (Aprovador)  | gestor@comendador.com.br             | senha@123   |
| Solicitante         | solicitante@comendador.com.br        | senha@123   |
| Almoxarife          | almoxarife@comendador.com.br         | senha@123   |

> ⚠️ **Troque a senha imediatamente no primeiro acesso ao ambiente de produção.** A senha `senha@123` é apenas para testes — nunca use em produção.

---

### Tela inicial — Dashboard

Ao entrar, você cai no **Dashboard** (`/dashboard`). Ele reúne os números mais importantes do dia em cards de métrica e atalhos rápidos.

```
┌─────────────────────────────────────────────────────────────────────────┐  (tema escuro)
│  ▐█ COMENDADOR COMPRAS                                       [usuário ▾]│
├──────────────────┬──────────────────────────────────────────────────────┤
│                  │                                                       │
│  MENU            │   BOM DIA, COMPRADORA!                                │
│  ─────────────   │                                                       │
│  Dashboard    ◀  │  ┌──────────────┐  ┌──────────────┐  ┌────────────┐  │
│                  │  │ Requisições  │  │  Em cotação  │  │ Aguardando │  │
│  COMPRAS         │  │  pendentes   │  │              │  │ aprovação  │  │
│  Requisições     │  │     12       │  │      5       │  │     3      │  │
│  Triagem      ●3 │  └──────────────┘  └──────────────┘  └────────────┘  │
│  Itens a Repor   │                                                       │
│  Cotações        │  ┌──────────────┐  ┌──────────────┐                  │
│  Pedidos         │  │  Em compra   │  │  Concluídas  │                  │
│                  │  │              │  │   (30 dias)  │                  │
│  APROVAÇÕES      │  │      2       │  │     38       │                  │
│  Aprovações      │  └──────────────┘  └──────────────┘                  │
│                  │                                                       │
│  ESTOQUE         │  Últimas requisições ──────────────────────────────  │
│  Estoque         │  #042 · Cimento CP-II 50kg    ● Em triagem           │
│  Recebimentos    │  #041 · Tinta Acrílica Branca ● Aguardando aprovação │
│                  │  #040 · Luvas de Proteção     ● Em compra            │
│  RELATÓRIOS ▾    │                                                       │
│  Gastos por CC   │                                                       │
│  Por Fornecedor  │                                                       │
│  Tempo Aprovação │                                                       │
│  ...             │                                                       │
│                  │                                                       │
└──────────────────┴──────────────────────────────────────────────────────┘
  ● badge verde = novo / pendente de ação sua
```

- **Sidebar esquerda** — navegação principal, agrupada por área (Compras, Aprovações, Estoque, Relatórios).
- **Cards de métrica** — clique em qualquer card para ir direto à lista filtrada.
- **Badge vermelho/verde** no menu — indica itens aguardando a sua ação (ex.: "3" em Triagem = 3 requisições para triar).

---

## 3. Passo a passo do fluxo

---

### 3.1 Requisição

#### O que é

A requisição é o ponto de partida. Um **solicitante** (funcionário da obra, almoxarife, gestor) abre uma requisição descrevendo o que precisa comprar. Ela chega para a compradora com status **Aguardando triagem**.

#### Como o solicitante cria uma requisição

1. Acesse **Requisições** → **Nova requisição** (`/requisicoes/nova`).
2. Preencha: título, unidade/obra, centro de custo, data de necessidade.
3. Adicione os itens: descrição, quantidade, unidade de medida.
4. Clique em **Enviar para triagem**.
   - O status muda de *Rascunho* → *Aguardando triagem*.

#### Mockup — Lista de requisições

```
┌─────────────────────────────────────────────────────────────────────────┐  (tema escuro)
│  Requisições                               [+ Nova requisição]          │
├─────────────────────────────────────────────────────────────────────────┤
│  Filtros: [Todas ▾]  [Unidade ▾]  [Status ▾]  [Data ▾]   🔍 Buscar     │
├────┬──────────────────────────────┬───────────────┬──────────┬─────────┤
│ #  │ Título                       │ Unidade        │ Data     │ Status  │
├────┼──────────────────────────────┼───────────────┼──────────┼─────────┤
│ 42 │ Cimento CP-II 50kg (x50 sc)  │ Obra Exp. Norte│ 25/06/26 │ ● triagem│
│ 41 │ Tinta Acrílica Branca 18L    │ Matriz         │ 24/06/26 │ ● aprova.│
│ 40 │ Luvas de Proteção CA-39      │ Obra Expansão  │ 20/06/26 │ ● compra │
│ 39 │ Parafusos Sextavados 3/8"    │ Filial Sul     │ 18/06/26 │ ✔ concl. │
└────┴──────────────────────────────┴───────────────┴──────────┴─────────┘
```

#### Exemplo real

> A **Obra Expansão Norte** precisa de **50 sacos de Cimento CP-II 50kg** para a semana seguinte. João (solicitante) abre a requisição, informa data de necessidade 25/06/2026 e envia. A compradora recebe a notificação e a vê na lista de triagem.

---

### 3.2 Triagem

#### O que é

A **triagem** é a etapa em que a compradora analisa o pedido antes de ir para cotação. Ela verifica se as informações estão corretas, se o item já existe em estoque, se a urgência justifica uma compra imediata, e pode devolver para o solicitante corrigir caso algo esteja errado.

#### Como fazer

1. Acesse **Triagem** (`/compradora/triagem`).
2. Clique na requisição para abrir o detalhe.
3. Leia os itens solicitados e verifique:
   - Os dados (quantidade, unidade, urgência) estão completos?
   - Há estoque disponível para algum item? (o sistema sinaliza)
4. Se precisar ajuste: clique em **Devolver** e informe o motivo. O solicitante recebe notificação.
5. Se estiver ok: clique em **Iniciar cotação**. O status passa para **Em cotação**.

#### Mockup — Tela de triagem

```
┌─────────────────────────────────────────────────────────────────────────┐  (tema escuro)
│  Triagem · Requisição #042                                               │
├─────────────────────────────────────────────────────────────────────────┤
│  Obra Expansão Norte · CC: OBRAS-NORTE · Solicitante: João Silva        │
│  Data de necessidade: 25/06/2026 · Aberta em: 22/06/2026               │
├─────────────────────────────────────────────────────────────────────────┤
│  ITENS                                                                   │
│  ┌──────────────────────────────┬────────┬──────┬───────────────────┐   │
│  │ Descrição                    │ Qtde   │ Un.  │ Estoque atual     │   │
│  ├──────────────────────────────┼────────┼──────┼───────────────────┤   │
│  │ Cimento CP-II 50kg           │  50    │ saco │ 5 sc (insufic.)   │   │
│  └──────────────────────────────┴────────┴──────┴───────────────────┘   │
│                                                                          │
│  Observações do solicitante: "Urgente — concretagem na sexta-feira"      │
│                                                                          │
│  [  Devolver para correção  ]              [  Iniciar cotação  ▶  ]     │
└─────────────────────────────────────────────────────────────────────────┘
```

#### Exemplo real

> A compradora abre a req. #042 (Cimento CP-II, Obra Expansão Norte). O estoque mostra apenas 5 sacos — insuficiente para os 50 pedidos. Ela clica **Iniciar cotação** e passa para a próxima etapa.

---

### 3.3 Cotação

#### O que é

A cotação é onde a compradora busca preços com fornecedores e escolhe o melhor. O sistema exige um **número mínimo de cotações** por faixa de valor (por exemplo, 3 fornecedores para valores acima de determinado limite). Só depois de atingir esse mínimo com valores confirmados é possível marcar a cotação vencedora e avançar.

#### Como fazer

1. Acesse **Cotações** pela requisição em triagem ou pelo menu lateral.
2. Adicione cada fornecedor consultado: nome, valor unitário, prazo de entrega e observações.
3. Quando tiver o mínimo de cotações com valor preenchido e confirmado, o botão **Concluir cotação** é habilitado.
4. Marque a cotação **vencedora** (melhor preço/condição).
5. Clique em **Concluir cotação**. O status passa para **Cotação concluída** → **Aguardando aprovação**.

#### Mockup — Tela de cotações

```
┌─────────────────────────────────────────────────────────────────────────┐  (tema escuro)
│  Cotação · Requisição #042 · Cimento CP-II 50kg (50 sacos)              │
├─────────────────────────────────────────────────────────────────────────┤
│  Mínimo exigido: 3 cotações com valor confirmado   [2 de 3 ✔]           │
├──────────────┬──────────────┬─────────┬────────┬────────────────────────┤
│ Fornecedor   │ Valor unit.  │ Total   │ Prazo  │ Status                 │
├──────────────┼──────────────┼─────────┼────────┼────────────────────────┤
│ Construfácil │ R$ 32,50     │R$1.625  │ 3 dias │ ✔ Confirmado  [🏆]     │
│ Cimento Boa  │ R$ 34,00     │R$1.700  │ 5 dias │ ✔ Confirmado           │
│ ABC Materiais│ (aguardando) │   —     │  —     │ ⏳ Aguardando resposta  │
├──────────────┴──────────────┴─────────┴────────┴────────────────────────┤
│  [+ Adicionar fornecedor]          [  Concluir cotação  ] (aguardando)  │
└─────────────────────────────────────────────────────────────────────────┘
```

> ⚠️ O botão **Concluir cotação** fica desabilitado enquanto o mínimo de cotações com valor confirmado não for atingido. Se o sistema não o deixa avançar, verifique se todas as cotações marcadas têm valor preenchido e confirmado — não apenas digitado.

#### Exemplo real

> Para os 50 sacos de cimento (total estimado R$ 1.625), a compradora consulta 3 fornecedores. A **Construfácil Ltda** oferece R$ 32,50/saco com entrega em 3 dias — a melhor proposta. Ela marca como vencedora e conclui a cotação.

---

### 3.4 Aprovação

#### O que é

Toda compra passa por aprovação antes de virar pedido. **Quem aprova** depende do valor total da requisição:

| Faixa de valor        | Quem aprova           |
|-----------------------|-----------------------|
| Até R$ 5.000          | Gestor                |
| R$ 5.001 a R$ 20.000  | Diretor               |
| Acima de R$ 20.000    | Diretor + CEO         |
| Emergencial           | Faixa específica      |

O aprovador recebe notificação automática e acessa a tela de aprovação para analisar e decidir.

#### Como acompanhar

1. Acesse **Aprovações** (`/aprovacoes`) para ver o status de cada aprovação pendente.
2. Clique em uma aprovação para ver o detalhe (`/aprovacoes/{id}`): itens, fornecedor vencedor, valor total e histórico.
3. Após a decisão do aprovador, o status muda para **Aprovada** ou **Reprovada**.
   - **Aprovada** → a compradora pode emitir o Pedido de Compra.
   - **Reprovada** → a requisição é encerrada (ou volta para análise, dependendo do motivo).

#### Mockup — Detalhe de aprovação

```
┌─────────────────────────────────────────────────────────────────────────┐  (tema escuro)
│  Aprovação · Requisição #042                                             │
├─────────────────────────────────────────────────────────────────────────┤
│  Valor total: R$ 1.625,00   ·   Faixa: até R$ 5.000 → Gestor           │
│  Fornecedor vencedor: Construfácil Ltda · Prazo: 3 dias                 │
│                                                                          │
│  Status atual:  ● Aguardando aprovação do Gestor                        │
│                                                                          │
│  Histórico:                                                              │
│  22/06 10:15 · Cotação concluída por Ana (Compradora)                   │
│  22/06 10:16 · Enviado para aprovação do Gestor                         │
│                                                                          │
│            [  Reprovar  ]                  [  Aprovar  ✔  ]             │
└─────────────────────────────────────────────────────────────────────────┘
```

#### Exemplo real

> R$ 1.625,00 cai na faixa "até R$ 5.000" — apenas o **Gestor** precisa aprovar. Ele recebe o e-mail, acessa o sistema e clica **Aprovar**. A compradora é notificada imediatamente.

---

### 3.5 Pedido de Compra

#### O que é

Com a requisição **Aprovada**, a compradora emite o **Pedido de Compra** — o documento formal que vai para o fornecedor. O sistema gera um PDF pronto para enviar por e-mail ou imprimir.

#### Como fazer

1. Acesse **Pedidos** (`/compradora/pedidos`).
2. Localize o pedido gerado pela requisição aprovada e clique para abrir (`/compradora/pedidos/{id}`).
3. Confira os dados: fornecedor, itens, quantidades, valores, prazo de entrega e local de entrega.
4. Clique em **Gerar PDF** para baixar o documento (`/compradora/pedidos/{id}/pdf`).
5. Envie o PDF ao fornecedor pelo canal combinado (e-mail, WhatsApp, portal do fornecedor).
6. O status da requisição passa para **Em compra**.

#### Mockup — Detalhe do pedido

```
┌─────────────────────────────────────────────────────────────────────────┐  (tema escuro)
│  Pedido de Compra · PC-2026-0042                                        │
│  Emitido em 22/06/2026 · Status: ● Em compra                           │
├─────────────────────────────────────────────────────────────────────────┤
│  Fornecedor : Construfácil Ltda                                          │
│  Entrega em : 25/06/2026 · Obra Expansão Norte                          │
│                                                                          │
│  ┌───────────────────────────┬───────┬──────────┬──────────┐            │
│  │ Item                      │ Qtde  │ Unit.    │ Total    │            │
│  ├───────────────────────────┼───────┼──────────┼──────────┤            │
│  │ Cimento CP-II 50kg        │  50   │ R$ 32,50 │R$1.625,00│            │
│  └───────────────────────────┴───────┴──────────┴──────────┘            │
│  Total geral: R$ 1.625,00                                               │
│                                                                          │
│  [  Gerar PDF  📄  ]                                                    │
└─────────────────────────────────────────────────────────────────────────┘
```

#### Exemplo real

> A compradora abre o pedido PC-2026-0042, confere tudo e clica **Gerar PDF**. O arquivo é baixado automaticamente. Ela anexa ao e-mail para a Construfácil Ltda com as instruções de entrega na Obra Expansão Norte.

---

### 3.6 Recebimento

#### O que é

Quando o material chega na unidade, o **Almoxarife** registra o recebimento no sistema. Isso encerra o ciclo da compra e atualiza o estoque automaticamente.

#### Como funciona (visão da compradora)

- O almoxarife acessa **Recebimentos** (`/almoxarife/recebimentos`) e registra o que chegou: quantidades, estado dos itens, número de nota fiscal e, se aplicável, número do lote e data de validade.
- O estoque é atualizado com **custo médio ponderado** — o sistema recalcula o custo médio automaticamente.
- Para itens com lote/validade, o sistema usa **FEFO** (Primeiro a Vencer, Primeiro a Sair) nas saídas.
- A requisição passa para **Recebida** → **Concluída**.

#### Mockup — Tela de estoque (visão do almoxarife)

```
┌─────────────────────────────────────────────────────────────────────────┐  (tema escuro)
│  Estoque · Obra Expansão Norte                                           │
├──────────────────────────────────┬──────────┬──────────┬────────────────┤
│ Item                             │ Qtde     │ Custo M. │ Próx. venc.    │
├──────────────────────────────────┼──────────┼──────────┼────────────────┤
│ Cimento CP-II 50kg               │ 55 sacos │ R$ 32,60 │ —              │
│ Tinta Acrílica Branca 18L        │  8 latas │ R$ 87,00 │ ⚠️ 10/07/2026  │
│ Luvas de Proteção CA-39          │ 24 pares │ R$ 12,00 │ —              │
└──────────────────────────────────┴──────────┴──────────┴────────────────┘
  ⚠️ badge amarelo = lote vencido ou próximo do vencimento (só informativo)
```

> ⚠️ O alerta de **lote vencido** é visual — o sistema não bloqueia a saída, mas a compradora e o almoxarife devem avaliar o descarte conforme a política da empresa.

---

## 4. Enviar Cotação por E-mail (sugestão automática)

Esta funcionalidade permite solicitar preços por e-mail diretamente do sistema e receber as respostas dos fornecedores de forma semi-automática — sem precisar copiar e colar manualmente.

### Como funciona, passo a passo

**1. Solicitar cotação por e-mail**

Na tela de Cotações de uma requisição, procure o painel **"Solicitar por e-mail"**:

```
┌─────────────────────────────────────────────────────────────────────────┐  (tema escuro)
│  Solicitar por e-mail                                                   │
├─────────────────────────────────────────────────────────────────────────┤
│  Selecione os fornecedores:                                             │
│  [✔] Construfácil Ltda    <compras@construfacil.com.br>                 │
│  [✔] Cimento Boa Ltda     <vendas@cimentoboa.com.br>                   │
│  [ ] ABC Materiais        <abc@materiais.com.br>                        │
│                                                                          │
│  [  Enviar solicitação  ✉  ]                                            │
└─────────────────────────────────────────────────────────────────────────┘
```

- Marque os fornecedores desejados e clique **Enviar solicitação**.
- O sistema cria cotações com status **Aguardando resposta** (sem valor ainda) e envia um e-mail a cada fornecedor selecionado.

**2. O fornecedor responde**

O fornecedor recebe um e-mail com as especificações do item. Ele responde ao mesmo e-mail (mantendo o assunto, que contém um código como `[COT-123]`) com algo como:

```
Valor: R$ 32,50 | Prazo: 3 dias úteis
```

**3. O sistema captura a resposta automaticamente**

A cada 5 minutos, o sistema verifica as respostas recebidas. Quando identifica o valor:

```
┌─────────────────────────────────────────────────────────────────────────┐  (tema escuro)
│  Cotação · Construfácil Ltda                    ● Resposta recebida     │
├─────────────────────────────────────────────────────────────────────────┤
│  Sugerido: R$ 32,50 / Prazo: 3 dias          (em cinza — não oficial)  │
│                                                                          │
│  Valor oficial: [_____________]  Prazo: [________]                      │
│                                                                          │
│  [  Confirmar sugestão  ✔  ]         [  Preencher manualmente  ]        │
└─────────────────────────────────────────────────────────────────────────┘
```

- O valor **em cinza** é a sugestão — é informativo, não conta para o mínimo de cotações ainda.
- Você recebe um e-mail de aviso: *"Resposta recebida para cotação #COT-123"*.

**4. Confirmar o valor**

- Clique em **Confirmar sugestão** para aceitar o valor proposto pelo fornecedor — ele vira o valor oficial da cotação.
- Ou clique em **Preencher manualmente** se quiser digitar um valor diferente (após negociação por telefone, por exemplo).
- Apenas cotações com valor **confirmado** contam para o mínimo exigido e podem ser marcadas como vencedoras.

> ⚠️ **Atenção — fallback manual:** Se o fornecedor escrever o valor de forma incomum (ex.: "trinta e dois reais e cinquenta centavos" ou em uma tabela em anexo), o sistema pode não identificar automaticamente. Nesse caso, a resposta aparece **sem valor sugerido** e o texto original do e-mail fica salvo nas observações da cotação. Basta preencher o valor manualmente.

### Resumo visual do fluxo

```
  Compradora                 Sistema                   Fornecedor
      |                         |                           |
  Seleciona                 Cria cotação               Recebe e-mail
  fornecedores   ────────►  "Aguardando         ─────► com código
  e clica                   resposta"                  [COT-123]
  "Enviar"                     |                           |
      |                        |          Responde o e-mail com
      |                    A cada         valor e prazo
      |                    5 min:             |
      |                   verifica ◄──────────┘
      |                   resposta
      |                        |
  Recebe aviso            Exibe "Sugerido:          
  por e-mail  ◄────────── R$ XX,XX" em cinza
      |
  Clica                  
  "Confirmar"  ────────►  Valor vira oficial
  sugestão                    |
      |                   Cotação conta
      |                   para o mínimo
```

---

## 5. Dicas & atalhos para acelerar o trabalho

- **Itens a Repor** — acesse `/compradora/itens-a-repor` para ver automaticamente quais produtos do estoque estão abaixo do ponto de reposição. Ótimo para antecipar compras antes de receber requisições.

- **Filtros na lista de requisições** — use os filtros de status, unidade e data para não se perder quando há muitas requisições abertas. Salve mentalmente: status *Aguardando triagem* = ação urgente sua.

- **Badge no menu** — o número ao lado de "Triagem" no menu lateral mostra quantas requisições precisam da sua atenção agora. Zere o badge diariamente.

- **Cotação por e-mail em lote** — quando tiver várias requisições para o mesmo tipo de material, pode enviar a solicitação de cotação para o mesmo fornecedor em cada requisição separadamente. O código `[COT-XXX]` garante que as respostas não se misturem.

- **Relatório "Gastos por Fornecedor"** — antes de cotar, consulte este relatório para ver histórico de preços praticados pelo fornecedor. Ajuda a identificar se a proposta atual está acima da média.

- **Relatório "Compras Emergenciais"** — monitore se o volume de compras emergenciais está alto. Um número elevado pode indicar falta de planejamento nas requisições — leve para a gestão.

- **PDF do pedido** — gere o PDF logo após a aprovação, antes de entrar em contato com o fornecedor. O documento já vem formatado com todos os dados necessários.

- **Histórico na requisição** — em caso de dúvida sobre o que aconteceu com uma requisição, abra o detalhe (`/requisicoes/{id}`) e desça até o histórico. Cada mudança de status é registrada com data, hora e nome do usuário responsável.

- **Relatório "Tempo de Aprovação"** — se as aprovações estão demorando, use este relatório para identificar o gargalo (qual aprovador está segurando mais pedidos) e escalar para a gestão.

- **Relatório "Custo por Obra"** — excelente para apresentar à diretoria quanto cada obra está consumindo. Exportável para análise.

---

## 6. FAQ — 15 perguntas comuns

| # | Pergunta | Resposta |
|---|----------|----------|
| 1 | **Como devolver uma requisição para o solicitante corrigir?** | Na tela de Triagem, abra a requisição e clique em **Devolver para correção**. Informe o motivo — o solicitante recebe uma notificação e pode editar a requisição. Enquanto estiver com status *Devolvida*, ela não avança no fluxo. |
| 2 | **Por que não consigo clicar em "Concluir cotação"?** | O botão só é habilitado quando o número mínimo de cotações **com valor confirmado** é atingido. Verifique: (a) se todas as cotações têm valor preenchido, e (b) se o valor foi **confirmado** (não apenas digitado como sugestão). |
| 3 | **O que é o "mínimo de cotações"?** | É o número de fornecedores que precisam ser consultados antes de escolher o melhor preço. Esse número varia conforme a faixa de valor da compra. É uma regra de compliance que garante concorrência justa. |
| 4 | **Por que um fornecedor não aparece na lista de cotação?** | O fornecedor precisa estar cadastrado no sistema. Se não encontrar, contate o Administrador para fazer o cadastro. Não é possível adicionar fornecedores diretamente na tela de cotação. |
| 5 | **O que significa cada status da requisição?** | Rascunho = ainda sendo preenchida. Aguardando triagem = enviada, esperando a compradora. Em triagem = compradora analisando. Devolvida = voltou para correção. Em cotação = buscando preços. Cotação concluída = pronta para aprovação. Aguardando aprovação = com o aprovador. Aprovada/Reprovada = decisão tomada. Em compra = pedido emitido. Recebida = material chegou. Concluída = processo encerrado. Cancelada = encerrada sem compra. |
| 6 | **Como gerar o PDF do pedido de compra?** | Acesse **Pedidos** no menu lateral, abra o pedido desejado e clique no botão **Gerar PDF**. O arquivo é baixado automaticamente pelo navegador. |
| 7 | **O que é FEFO e como afeta o estoque?** | FEFO (First Expired, First Out — Primeiro a Vencer, Primeiro a Sair) é a regra que o sistema usa para itens com lote e data de validade. Na hora de dar baixa no estoque, ele prioriza o lote que vence mais cedo. Isso reduz perdas por vencimento. |
| 8 | **O que fazer quando aparece o alerta de lote vencido?** | O alerta é visual — o sistema não bloqueia a saída. Avalie junto com o almoxarife se o material deve ser descartado. Se sim, registre a baixa e documente o descarte conforme a política da empresa. |
| 9 | **O fornecedor não manteve o assunto do e-mail — o sistema não captou a resposta. O que fazer?** | Nesse caso, a resposta não será identificada automaticamente. Você pode preencher o valor manualmente na tela de cotação. O texto do e-mail original fica salvo nas observações para consulta. |
| 10 | **Quanto tempo o sistema demora para capturar a resposta do e-mail do fornecedor?** | O sistema verifica as respostas a cada 5 minutos. Se o fornecedor acabou de responder, aguarde até 5 minutos e recarregue a tela de cotações. |
| 11 | **Posso aprovar uma requisição em nome de um aprovador ausente?** | Não. Apenas o aprovador com a alçada correta pode aprovar. Se o aprovador estiver ausente, escale para o Administrador do sistema para tratar a delegação conforme a política da empresa. |
| 12 | **Como sei qual aprovador está responsável por uma requisição?** | Abra o detalhe da requisição ou da aprovação. O sistema mostra o perfil de alçada exigido (Gestor, Diretor ou CEO) e quem já aprovou em aprovações multi-nível. |
| 13 | **O relatório "Pendentes por Aprovador" pode me ajudar a cobrar aprovações atrasadas?** | Sim. Acesse **Relatórios → Pendentes por Aprovador**. Ele mostra quantos pedidos estão aguardando cada aprovador e há quanto tempo — útil para escalar casos críticos. |
| 14 | **Uma requisição foi reprovada por engano. Posso reabrí-la?** | Requisições reprovadas ou canceladas não podem ser reabertas diretamente. O solicitante deve abrir uma nova requisição. Se houver erro no processo, contate o Administrador. |
| 15 | **Como comparar custos entre as unidades da rede?** | Acesse **Relatórios → Comparativo entre Unidades**. Ele mostra lado a lado os gastos de cada unidade, facilitando identificar onde há oportunidade de padronização ou negociação conjunta. |

---

## 7. Quem contatar se algo der errado

Se você encontrar um erro no sistema, uma tela que não carrega, um comportamento inesperado ou uma dúvida que não está neste manual:

**1. Tente primeiro:**
- Recarregar a página (F5).
- Sair e entrar novamente no sistema.
- Verificar se o problema acontece em outro navegador.

**2. Abra um chamado com o time de TI** informando:
- O que você estava tentando fazer.
- Qual tela/menu estava usando (o endereço da página no navegador ajuda muito).
- O que apareceu de errado (uma mensagem de erro, tela em branco, botão que não funcionou).
- Horário aproximado do ocorrido.
- Capturas de tela se possível.

**3. Documentação técnica disponível:**
- [Manual Técnico](MANUAL-TECNICO.md) — para o time de TI: arquitetura, configuração e operação do sistema.
- [Runbook de Pilot](RUNBOOK-PILOT.md) — guia de operação para o piloto de implantação, com procedimentos de contingência.

> ⚠️ **Nunca compartilhe sua senha com ninguém**, nem com o time de TI. Solicitações de senha são golpe. O suporte acessa o sistema com conta própria de administrador.

---

---

*Comendador Compras — Manual da Compradora · v1 · 22/06/2026*

[Manual Técnico](MANUAL-TECNICO.md) · [Runbook de Pilot](RUNBOOK-PILOT.md)
