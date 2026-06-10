# ESCOPO — Sistema de Gestão de Compras | Rede Comendador

## Contexto

Sistema para a Rede Comendador (Grupo Comendador). Unidades heterogêneas: **postos de combustível, construtora (cada obra é uma unidade), cervejaria, central administrativa (escritório) e imobiliárias**. Compras 100% centralizadas numa Compradora Sênior; requisições partem das unidades; estoque e consumo controlados por unidade.

## Problema

Compras hoje são feitas sem processo: pedidos por WhatsApp/e-mail, sem cotação obrigatória, sem alçada de aprovação e sem rastreabilidade. Não há visão de quanto cada área gasta nem com quais fornecedores.

## Objetivo

Sistema web interno onde qualquer colaborador abre requisição de compra, o fluxo de cotação e aprovação roda conforme o valor, e a gestão enxerga todo o gasto por centro de custo.

## Stack

* Laravel 11+ (Blade + Livewire ou Inertia — tech-lead decide)
* MySQL
* Autenticação própria com perfis (sem pacote externo de ACL se der pra resolver com policies)
* Layout responsivo, PT-BR, moeda R$

## Perfis de usuário

1. **Solicitante** — abre e acompanha requisições
2. **Compradora Sênior** — perfil único e centralizado: recebe TODAS as requisições aprovadas para triagem, faz cotações, negocia e emite os pedidos de compra. Ninguém compra fora dela.
3. **Aprovador** — aprova/reprova conforme alçada
4. **Almoxarife** — confere recebimentos, dá entrada/saída no estoque, faz inventário
5. **Admin** — cadastros, parâmetros, relatórios

## Fluxo principal

Requisição → Cotação → Aprovação → Pedido de Compra → Recebimento → Encerrada

1. Solicitante abre requisição: **unidade**, descrição, quantidade, justificativa, centro de custo, urgência
2. Requisição cai na fila única da Compradora Sênior, que tria (pode devolver, agrupar requisições semelhantes ou atender direto do estoque se houver saldo)
3. Compradora anexa cotações (quantidade mínima conforme valor)
4. Sistema roteia para aprovação conforme alçada
5. Aprovado → Compradora gera Pedido de Compra (PDF) e envia ao fornecedor
6. Recebimento: almoxarife confere a entrega contra o pedido (total ou parcial) e a entrada no estoque é automática
7. Reprovada → volta ao solicitante com motivo; pode ajustar e reenviar 1 vez

## Módulo de Estoque (por unidade)

**Cadastro de itens:** código, descrição, unidade de medida, categoria, estoque mínimo POR UNIDADE, localização

**Saldo é controlado por unidade.** Cada unidade enxerga só o próprio estoque; Compradora e Admin enxergam a rede inteira.

**Movimentações:**

* **Entrada** — automática ao confirmar recebimento de pedido de compra na unidade de destino (vincula nota fiscal/pedido)
* **Saída** — por requisição interna de material: colaborador solicita, almoxarife da unidade atende e baixa do saldo
* **Transferência entre unidades** — saída na origem + entrada no destino, num movimento único rastreável \[DECIDIR: precisa aprovação?]
* **Ajuste** — inventário/correção, só Admin ou Almoxarife, sempre com justificativa

**Regras:**

* Saldo nunca fica negativo — saída sem saldo é bloqueada
* Item abaixo do estoque mínimo gera alerta e sugestão de nova requisição de compra
* Toda movimentação registra: item, quantidade, tipo, origem (pedido/requisição/ajuste), usuário, data
* Custo médio do item recalculado a cada entrada \[DECIDIR: custo médio ou último custo?]

**Inventário:** contagem periódica com tela de conferência (saldo sistema × contado) e ajuste em lote

## Alçadas de aprovação \[DECIDIR — valores sugeridos]

**Regra geral: TODA compra, sem exceção, exige cotação (bid) e aprovação manual. Não existe aprovação automática em nenhuma faixa.**

|Valor|Cotações mínimas|Aprovador|
|-|-|-|
|Até R$ 5.000|2|Gestor da área|
|R$ 5.000,01 a R$ 20.000|3|Diretor|
|Acima de R$ 20.000|3|Diretor + CEO (dupla)|

## Cadastros

* **Unidades da rede**: nome, **tipo (posto / obra / cervejaria / central / imobiliária)**, CNPJ, endereço, gestor responsável, status. **Obras têm ciclo de vida**: data de início, previsão de término, status ativa/encerrada — obra encerrada não recebe requisição nova mas mantém histórico
* Fornecedores: razão social, CNPJ, contato, categoria, status ativo/inativo \[DECIDIR: precisa aprovação pra cadastrar fornecedor novo?]
* Centros de custo: código, nome, gestor responsável, vinculado à unidade
* **Categorias de compra por tipo de unidade**: material de construção e locação de equipamento (obras), insumos de produção (cervejaria), peças/conveniência (postos), escritório/TI/manutenção (todas)
* Usuários e perfis (usuário vinculado a uma ou mais unidades; Compradora e Admin enxergam todas)

## Regras de negócio

* Requisição não pode ser editada após enviada (só cancelada pelo solicitante enquanto não aprovada)
* Compra é 100% centralizada: só o perfil Compradora Sênior emite pedido de compra
* Compradora pode agrupar várias requisições num pedido único ao mesmo fornecedor (ganho de escala)
* Compradora pode atender requisição direto do estoque (vira saída de material, sem compra)
* Painel da Compradora: fila por urgência/idade, com SLA de triagem em 24h \[DECIDIR: SLA]
* Aprovador não pode aprovar a própria requisição
* Toda mudança de status registra log: quem, quando, de→para
* Cotação exige: fornecedor, valor unitário, prazo de entrega, validade da proposta
* Compra emergencial NÃO pula cotação: mínimo 1 cotação + justificativa + aprovação do diretor independente do valor \[DECIDIR: manter?]
* Pedido de compra gera número sequencial PC-AAAA-NNNN
* Pedido agrupado pode ter itens com destinos diferentes: cada item indica a unidade de entrega
* Visibilidade por unidade: solicitante/gestor só vê requisições e estoque da própria unidade

## Particularidades por tipo de unidade

* **Obras (construtora):** todo gasto amarrado à obra — relatório de custo acumulado por obra é obrigatório. Obra pode ter verba/orçamento cadastrado; requisição que estoura a verba exige aprovação superior \[DECIDIR: controla verba na v1?]
* **Cervejaria:** insumos de produção podem exigir controle de lote e validade no estoque \[DECIDIR: lote/validade na v1 ou v2?]
* **Postos:** NÃO inclui compra de combustível (suprimento de pista tem processo próprio) — só peças, conveniência, manutenção e operacional \[DECIDIR: confirma?]
* **Imobiliárias:** baixo volume; manutenção de imóveis pode pedir vínculo da requisição a um imóvel/contrato \[DECIDIR: v1 trata como texto livre na justificativa?]
* **Central:** consome e também rateia gastos compartilhados entre unidades \[DECIDIR: rateio fica pra v2?]

## Notificações

* E-mail para aprovador quando requisição entra na fila dele
* E-mail para solicitante a cada mudança de status
* Lembrete diário de aprovações pendentes há mais de 48h

## Relatórios (Admin)

* Gasto por centro de custo (mês/ano)
* Gasto por fornecedor e por categoria
* Tempo médio de aprovação por alçada
* Requisições pendentes por aprovador
* Posição de estoque (saldo, valor, itens abaixo do mínimo) — consolidado e por unidade
* Consumo de material por centro de custo e por unidade
* Gasto comparativo entre unidades (mês/ano)
* Custo acumulado por obra (construtora), com curva mensal

## Fora de escopo (v1)

* Integração com ERP/contabilidade
* Pagamento e financeiro (contas a pagar)
* App mobile nativo
* Portal do fornecedor
* Contratos recorrentes

## Métrica de sucesso

* 100% das compras passando pelo sistema em 60 dias
* Zero compra sem cotação e sem aprovação registrada
* Tempo médio de aprovação < 24h

## Pontos em aberto pro PM me perguntar

1. Valores das alçadas estão certos? quero parametrizar o valor 
2. Fornecedor novo precisa de aprovação? sim
3. Compra emergencial existe ou todo mundo segue o fluxo? preciso de ajuda
4. Recebimento parcial gera pendência ou encerra com ressalva? ressalva mas preciso de ajuda
5. Custeio do estoque: custo médio ou último custo? preciso de ajuda
6. Quantas unidades a rede tem hoje e quais entram na v1? 10 unidades (deixa parametrizado)
7. Fornecedor entrega direto em cada unidade ou num ponto central que redistribui? ambos cenarios
8. Transferência entre unidades precisa de aprovação? não
9. Verba/orçamento por obra entra na v1? entra
10. Lote/validade pra cervejaria: v1 ou v2? v1
11. Confirma que combustível de pista fica FORA do sistema? sim
12. Rateio de gastos da central entre unidades: v2? v1

