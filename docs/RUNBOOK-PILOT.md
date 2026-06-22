# Runbook de Pilot — Comendador Compras v1

**Versão v1 · 22/06/2026 · Público: PM, QA, Compradora Piloto**

---

## Índice

1. [Objetivo do Pilot](#1-objetivo-do-pilot)
2. [Pré-requisitos](#2-pré-requisitos)
3. [Setup do Ambiente (Time Técnico)](#3-setup-do-ambiente-time-técnico)
4. [Roteiro Executável](#4-roteiro-executável)
5. [Matriz de Aceite](#5-matriz-de-aceite)
6. [Formulário de Feedback](#6-formulário-de-feedback)
7. [Problemas Conhecidos e Workarounds](#7-problemas-conhecidos-e-workarounds)
8. [Como Reportar Bugs](#8-como-reportar-bugs)
9. [Rodapé e Links](#9-rodapé-e-links)

---

## 1. Objetivo do Pilot

O pilot tem como finalidade:

- Validar o fluxo ponta a ponta — da abertura de requisição até a emissão do Pedido de Compra e entrada no estoque — em condições próximas ao uso real.
- Identificar bugs, comportamentos inesperados e pontos de atrito antes da entrada em produção.
- Coletar feedback qualitativo da compradora piloto sobre usabilidade, clareza das telas e aderência ao processo real da Rede Comendador.
- Confirmar que os controles de aprovação por alçada (Gestor / Diretor / CEO) e o isolamento por unidade funcionam corretamente.

---

## 2. Pré-requisitos

### 2.1 Acesso

| Campo | Valor |
|---|---|
| URL do sistema | `http://127.0.0.1:8000` (ou a porta exibida pelo `php artisan serve`) |
| Tela de login | `http://127.0.0.1:8000/login` |
| Ambiente | **TESTE — dados fake** |

### 2.2 Credenciais de Acesso (Logins Demo)

Todos os usuários abaixo usam a mesma senha: **`senha@123`**

| Perfil | E-mail |
|---|---|
| Admin | `admin@comendador.com.br` |
| Compradora Sênior | `compradora@comendador.com.br` |
| Aprovador Diretor | `diretor@comendador.com.br` |
| Aprovador Gestor | `gestor@comendador.com.br` |
| Solicitante | `solicitante@comendador.com.br` |
| Almoxarife | `almoxarife@comendador.com.br` |

### 2.3 Navegador

Chrome ou Edge (versão recente). Não foi homologado em Firefox ou Safari.

### 2.4 Aviso importante

> ⚠️ **Este é um ambiente de TESTE com dados completamente fictícios. Nenhuma compra real será realizada. Você pode errar, criar, cancelar e tentar novamente à vontade. Se algo quebrar, avise o time técnico — esse é exatamente o objetivo do pilot.**

---

## 3. Setup do Ambiente (Time Técnico)

### 3.1 Carregar dados demo

Execute o comando abaixo para criar o banco do zero e popular com usuários, unidades, fornecedores, catálogo, requisições e estoque fake:

```bash
/c/Users/Usuario/.config/herd/bin/php84/php.exe artisan migrate:fresh --seed
```

⚠️ **Este comando apaga todos os dados existentes.** Execute apenas uma vez antes do pilot. Se precisar reiniciar do zero durante o pilot, avise todos os participantes antes de rodar novamente.

### 3.2 Iniciar o servidor local

```bash
/c/Users/Usuario/.config/herd/bin/php84/php.exe artisan serve
```

Anote a URL exibida no terminal (ex.: `http://127.0.0.1:8000`). Compartilhe essa URL com a compradora piloto. O terminal deve permanecer aberto durante todo o pilot.

### 3.3 Como abrir

Abra a URL no Chrome ou Edge. Você verá a tela de login em `/login`.

### 3.4 Captura de e-mail (IMAP) — modo real vs. modo simulação

O sistema suporta dois modos de operação para a etapa de cotação por e-mail:

---

**MODO REAL (com caixa IMAP configurada)**

- Requer as variáveis `IMAP_*` configuradas no `.env` (host, porta, usuário, senha, caixa).
- O fornecedor responde ao e-mail com o token `[COT-{id}]` no assunto e o corpo no formato: `Valor: R$ X | Prazo: Y dias`.
- O comando abaixo lê a caixa, casa o token, valida o remetente e grava a sugestão:

```bash
/c/Users/Usuario/.config/herd/bin/php84/php.exe artisan cotacoes:capturar-respostas
```

Em produção esse comando é agendado para rodar automaticamente a cada 5 minutos.

---

**MODO SIMULAÇÃO (sem caixa IMAP — recomendado para o pilot)**

Sem IMAP configurado, simule a etapa de resposta do fornecedor por uma das duas formas abaixo:

**(a) A compradora registra o valor manualmente:**
Na tela de Cotações (`/compradora/cotacoes/{id}`), use o botão **"+ Nova Cotação"** para digitar o valor e o prazo diretamente para cada fornecedor. Esta é a forma mais simples para o pilot.

**(b) Inserção técnica direta para demonstração:**
O time técnico pode inserir a sugestão diretamente no banco de dados (via tinker ou ferramenta de banco) para simular a chegada da resposta do fornecedor. Use esta opção apenas se quiser demonstrar o fluxo de sugestão automática sem depender de e-mail real.

> ⚠️ **Para o pilot, recomenda-se o Modo Simulação (a) — registro manual pela compradora.** O Modo Real exige IMAP configurado e caixa de e-mail ativa, o que vai além do escopo do pilot.

---

## 4. Roteiro Executável

### Cenário do Pilot

- **Unidade:** Obra Expansão Norte
- **Item:** Cimento CP-II 50kg
- **Quantidade:** 200 sacos
- **Fornecedores:** Construfácil Ltda · Materiais Norte SA · Cimentos União
- **Valores de referência:** R$ 32,00 / R$ 33,50 / R$ 31,80 por saco

---

### Passo 1 — Login como Solicitante e criação de requisição

**AÇÃO:** Abra `/login`. Faça login com `solicitante@comendador.com.br` / `senha@123`. No menu, acesse **Requisições → Nova Requisição** (`/requisicoes/nova`).

**DADO:**
- Unidade solicitante: `Obra Expansão Norte`
- Item: `Cimento CP-II 50kg`
- Quantidade: `200`
- Justificativa (se solicitada): `Consumo previsto para alvenaria do bloco C`

**RESULTADO ESPERADO:** A requisição é criada com status **"Aguardando Triagem"**. Ela aparece na lista em `/requisicoes`.

---

### Passo 2 — Login como Compradora e triagem da requisição

**AÇÃO:** Faça logout. Acesse `/login` e entre com `compradora@comendador.com.br` / `senha@123`. Navegue até **Triagem** (`/compradora/triagem`). Localize a requisição de Cimento CP-II 50kg da Obra Expansão Norte. Clique em **Aceitar**.

**DADO:** Nenhum dado adicional. Apenas confirmar a ação.

**RESULTADO ESPERADO:** A requisição muda de status para **"Em Cotação"** e desaparece da fila de triagem. Uma mensagem de confirmação deve aparecer na tela.

---

### Passo 3 — Cotação: solicitar por e-mail ou registrar manualmente

**AÇÃO:** Acesse **Cotações** (`/compradora/cotacoes/{id}` — o `{id}` corresponde à requisição aceita). Localize o painel **"Solicitar por e-mail"**.

**Modo Real (IMAP configurado):**
Selecione os 3 fornecedores: `Construfácil Ltda`, `Materiais Norte SA`, `Cimentos União`. Clique em **"Enviar solicitação"**.

**Modo Simulação (recomendado para o pilot):**
Clique em **"+ Nova Cotação"** para cada fornecedor. Informe o valor e prazo manualmente (ver Passo 4-b).

**RESULTADO ESPERADO (Modo Real):** As 3 cotações aparecem com status **"Aguardando resposta"**. Os e-mails são enviados com o token `[COT-{id}]` no assunto.

**RESULTADO ESPERADO (Modo Simulação):** As cotações aparecem na lista com os valores informados.

---

### Passo 4 — Simular respostas dos fornecedores

**Modo Real (com caixa IMAP configurada):**

Cada fornecedor deve responder o e-mail recebido (com o token `[COT-{id}]` mantido no assunto) com o corpo no formato:

```
Valor: R$ 32,00 | Prazo: 7 dias
```

Após as respostas chegarem à caixa, rode o comando de captura:

```bash
/c/Users/Usuario/.config/herd/bin/php84/php.exe artisan cotacoes:capturar-respostas
```

**Modo Simulação (b) — inserção técnica:**

O time técnico insere a sugestão diretamente no banco para a cotação de cada fornecedor. O campo a preencher é a sugestão (valor e prazo), mantendo o status da cotação como "Aguardando resposta" até a compradora confirmar.

**RESULTADO ESPERADO:** Após a captura (Modo Real) ou inserção (Modo Simulação b), as cotações exibem o badge **"Sugerido"** com o valor e prazo.

---

### Passo 5 — Visualizar sugestões na tela de Cotações

**AÇÃO:** Permaneça na tela `/compradora/cotacoes/{id}`. Aguarde ou recarregue a página após rodar `cotacoes:capturar-respostas`.

**RESULTADO ESPERADO:** A tela exibe as sugestões em cinza para cada cotação. Exemplo de layout (mockup):

```
┌─────────────────────────────────────────────────────────────────┐
│  Cotação — Cimento CP-II 50kg (200 un) · Obra Expansão Norte   │
├──────────────────────────┬──────────────┬───────────────────────┤
│ Fornecedor               │ Status       │ Sugestão              │
├──────────────────────────┼──────────────┼───────────────────────┤
│ Construfácil Ltda        │ Aguardando   │ Sugerido: R$ 32,00    │
│                          │ resposta     │ Prazo: 7 dias  [cinza]│
├──────────────────────────┼──────────────┼───────────────────────┤
│ Materiais Norte SA       │ Aguardando   │ Sugerido: R$ 33,50    │
│                          │ resposta     │ Prazo: 5 dias  [cinza]│
├──────────────────────────┼──────────────┼───────────────────────┤
│ Cimentos União           │ Aguardando   │ Sugerido: R$ 31,80    │
│                          │ resposta     │ Prazo: 10 dias [cinza]│
└──────────────────────────┴──────────────┴───────────────────────┘
         [ Confirmar sugestão ]  [ Editar ]  [ Ignorar ]
```

---

### Passo 6 — Confirmar valores e marcar a vencedora

**AÇÃO:** Para cada cotação com sugestão, clique em **"Confirmar sugestão"**. O valor muda de cinza (sugerido) para confirmado. Após confirmar as 3 cotações, marque a **vencedora**: selecione `Cimentos União` (menor preço: R$ 31,80/saco) e clique em **"Marcar como vencedora"** (ou equivalente na interface).

**DADO:**
- Vencedora: `Cimentos União` — R$ 31,80/saco — Prazo: 10 dias

**RESULTADO ESPERADO:** A cotação da Cimentos União aparece destacada como vencedora. As demais ficam como "não selecionada". O status da requisição avança para **"Aguardando Aprovação"**.

---

### Passo 7 — Aprovação por alçada (Gestor ou Diretor)

**AÇÃO:** Faça logout. O perfil que deve aprovar depende do valor total do pedido (200 × R$ 31,80 = **R$ 6.360,00**). Consulte a alçada configurada no sistema. Faça login com o aprovador adequado (`gestor@comendador.com.br` ou `diretor@comendador.com.br`, senha `senha@123`). Acesse **Aprovações** (`/aprovacoes`). Localize o pedido de Cimento CP-II 50kg. Clique em **Aprovar**.

**DADO:** Nenhum dado adicional. Adicione uma observação opcional se a tela permitir.

**RESULTADO ESPERADO:** A aprovação é registrada. O status avança para **"Aprovado"** ou **"Pedido Gerado"** conforme o fluxo. Se o valor exigir múltiplas alçadas (ex.: Gestor e depois Diretor), repita o passo com cada perfil.

---

### Passo 8 — Emitir o Pedido de Compra e baixar o PDF

**AÇÃO:** Ainda logado como compradora (`compradora@comendador.com.br`), acesse **Pedidos** (`/compradora/pedidos`). Localize o pedido correspondente e clique em **"Emitir Pedido"** (se ainda não emitido automaticamente). Em seguida, clique em **"Baixar PDF"** ou acesse diretamente `/compradora/pedidos/{id}/pdf`.

**RESULTADO ESPERADO:** O PDF do Pedido de Compra é gerado e baixado. Verifique se contém: número do pedido, unidade, item, quantidade, fornecedor vencedor (Cimentos União), valor unitário (R$ 31,80), valor total (R$ 6.360,00) e prazo de entrega.

---

### Passo 9 — Recebimento pelo Almoxarife (opcional)

**AÇÃO:** Faça logout. Login com `almoxarife@comendador.com.br` / `senha@123`. Acesse **Estoque** (`/almoxarife/estoque`). Localize o pedido aguardando recebimento. Registre o recebimento informando a quantidade efetivamente recebida (ex.: 200 sacos).

**DADO:**
- Quantidade recebida: `200`
- Data de recebimento: data atual

**RESULTADO ESPERADO:** O estoque é atualizado. Os 200 sacos de Cimento CP-II 50kg aparecem no saldo da unidade Obra Expansão Norte.

---

### Passo 10 — Verificar o Dashboard

**AÇÃO:** Faça login com qualquer perfil que tenha acesso ao Dashboard. Acesse `/dashboard`.

**RESULTADO ESPERADO:** As métricas refletem o fluxo concluído: a requisição não aparece mais como pendente, o pedido emitido está contabilizado e (se o Passo 9 foi executado) o estoque atualizado aparece nos indicadores relevantes.

---

## 5. Matriz de Aceite

| # | Item Testado | Resultado Esperado | OK? | Observações |
|---|---|---|---|---|
| 1 | Login com cada perfil | Acesso concedido com o perfil correto; redirecionamento para a tela inicial do perfil | ☐ | |
| 2 | Criar requisição (solicitante) | Requisição criada com status "Aguardando Triagem"; aparece na lista `/requisicoes` | ☐ | |
| 3 | Triagem — aceitar requisição (compradora) | Requisição muda para "Em Cotação"; desaparece da fila de triagem | ☐ | |
| 4 | Triagem — devolver requisição (compradora) | Requisição retorna ao solicitante com motivo; status atualizado | ☐ | |
| 5 | Solicitar cotação por e-mail (Modo Real) | E-mails enviados com token `[COT-{id}]`; cotações com status "Aguardando resposta" | ☐ | Testar apenas se IMAP configurado |
| 6 | Registrar cotação manualmente (Modo Simulação) | Cotações criadas com valores informados; visíveis na tela | ☐ | |
| 7 | Captura de sugestão via e-mail (`cotacoes:capturar-respostas`) | Sugestão aparece em cinza "Sugerido: R$ X / Y dias" na cotação correspondente | ☐ | Testar apenas se IMAP configurado |
| 8 | Confirmar sugestão (compradora) | Valor muda de sugerido (cinza) para confirmado; status da cotação atualizado | ☐ | |
| 9 | Aprovação por alçada (Gestor / Diretor) | Aprovador correto visualiza o pedido; aprovação registrada; status avança | ☐ | Verificar se alçada por valor está correta |
| 10 | Emissão do Pedido de Compra | Pedido gerado com número único; aparece em `/compradora/pedidos` | ☐ | |
| 11 | Download do PDF do Pedido | PDF gerado e baixado; contém dados completos (fornecedor, item, valor, prazo) | ☐ | |
| 12 | Recebimento no estoque (almoxarife) | Saldo atualizado em `/almoxarife/estoque`; lote registrado com data e quantidade | ☐ | |
| 13 | Dashboard com métricas atualizadas | Indicadores refletem o fluxo executado (requisição, pedido, estoque) | ☐ | |
| 14 | Isolamento por unidade | Usuário da Obra Expansão Norte não vê dados de outras unidades; e vice-versa | ☐ | |

---

## 6. Formulário de Feedback

*Imprima esta seção ou preencha digitalmente. Uma via por participante por sessão.*

---

**COMENDADOR COMPRAS — FORMULÁRIO DE FEEDBACK DO PILOT**

---

**Nome:** __________________________________________ **Data:** _____ / _____ / ______

**Perfil testado:** ( ) Solicitante ( ) Compradora ( ) Aprovador ( ) Almoxarife ( ) Admin

---

| Campo | Resposta |
|---|---|
| **Tela / Rota visitada** | |
| **O que funcionou bem** | |
| **O que travou ou confundiu** | |
| **Severidade do problema** | ( ) Baixa — incômodo cosmético ( ) Média — atrapalha mas tem contorno ( ) Alta — impede de continuar |
| **Sugestão de melhoria** | |

---

*Repetir para cada tela ou problema encontrado.*

---

**Tela / Rota:** _______________________________________________

**O que funcionou:**

_____________________________________________________________

_____________________________________________________________

**O que travou / confundiu:**

_____________________________________________________________

_____________________________________________________________

**Severidade:** ( ) Baixa ( ) Média ( ) Alta

**Sugestão:**

_____________________________________________________________

_____________________________________________________________

---

**Tela / Rota:** _______________________________________________

**O que funcionou:**

_____________________________________________________________

_____________________________________________________________

**O que travou / confundiu:**

_____________________________________________________________

_____________________________________________________________

**Severidade:** ( ) Baixa ( ) Média ( ) Alta

**Sugestão:**

_____________________________________________________________

_____________________________________________________________

---

**Impressão geral do sistema (0–10):** _______

**Comentário livre:**

_____________________________________________________________

_____________________________________________________________

_____________________________________________________________

---

*Entregue ao PM ou envie para o time técnico ao fim da sessão. Obrigado pela participação.*

---

## 7. Problemas Conhecidos e Workarounds

### 7.1 Captura IMAP exige caixa real

**Problema:** O comando `cotacoes:capturar-respostas` só funciona se as variáveis `IMAP_*` estiverem configuradas no `.env` e a caixa de e-mail estiver acessível.

**Workaround para o pilot:** Use o Modo Simulação (a) — registrar o valor manualmente pela compradora via "**+ Nova Cotação**" na tela de Cotações. Isso não afeta nenhum outro passo do fluxo.

---

### 7.2 Formato inesperado na resposta do fornecedor

**Problema:** Se o fornecedor responder o e-mail fora do formato esperado (`Valor: R$ X | Prazo: Y dias`), a captura automática não consegue extrair a sugestão e o campo ficará em branco.

**Workaround:** A compradora preenche o valor manualmente na cotação correspondente. O fluxo continua normalmente.

---

### 7.3 `migrate:fresh` apaga todos os dados

**Problema:** Se o comando `php artisan migrate:fresh --seed` for executado durante o pilot, todos os dados — inclusive os criados durante o roteiro — serão apagados e substituídos pelos dados fake iniciais.

**Workaround:** Execute `migrate:fresh --seed` apenas antes do início do pilot, com todos os participantes cientes. Caso precise reiniciar o cenário, avise a todos antes de executar.

---

### 7.4 Alçada de aprovação e valor total

**Problema:** Se o valor total da requisição (quantidade × preço unitário) não atingir o limite mínimo da alçada configurada, o pedido pode ir direto para emissão sem passar por aprovação, ou pode exigir um aprovador diferente do esperado.

**Workaround:** Verifique as faixas de alçada configuradas no sistema antes de iniciar o pilot. Para o cenário do Cimento (R$ 6.360,00), confirme qual aprovador é o correto e ajuste o roteiro se necessário.

---

### 7.5 Auto-replies são ignorados

**Comportamento esperado (não é bug):** O sistema ignora respostas automáticas de ausência ou confirmação de entrega de e-mail. Apenas respostas com conteúdo de valor e prazo são processadas.

---

## 8. Como Reportar Bugs

Ao encontrar um comportamento inesperado, anote as informações abaixo antes de reportar:

1. **Tela e rota:** qual página você estava (ex.: `/compradora/cotacoes/3`).
2. **Passo a passo:** o que você fez exatamente, na ordem em que fez.
3. **Resultado obtido:** o que o sistema mostrou ou fez.
4. **Resultado esperado:** o que deveria ter acontecido conforme o roteiro.
5. **Print de tela:** capture a tela com o erro visível (incluindo a URL no navegador).
6. **Perfil logado:** qual usuário estava autenticado (ex.: `compradora@comendador.com.br`).
7. **Hora aproximada:** para facilitar a busca nos logs do servidor.

### Onde reportar

Registre o bug diretamente no canal de comunicação do projeto com o time técnico. Para detalhes sobre logs de servidor, configuração de ambiente e acesso ao banco, consulte o **[Manual Técnico](MANUAL-TECNICO.md)**.

> ⚠️ **Não tente corrigir o ambiente por conta própria durante o pilot.** Anote o problema e chame o time técnico. Um ambiente quebrado pode gerar dados inconsistentes e comprometer as demais etapas do roteiro.

---

## 9. Rodapé e Links

---

**Comendador Compras — Runbook de Pilot · v1 · 22/06/2026**

- [Manual da Compradora](MANUAL-COMPRADORA.md)
- [Manual Técnico](MANUAL-TECNICO.md)
