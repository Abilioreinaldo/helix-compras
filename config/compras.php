<?php

/*
|--------------------------------------------------------------------------
| Helix Compras — controles de negócio (4ª auditoria adversarial)
|--------------------------------------------------------------------------
|
| Valores LITERAIS de propósito (mesma regra do config/foundation.php): um
| `env()` vazio vira `false`/`0`/`null` e afrouxa o controle em silêncio. Para
| mudar um limite, muda-se este arquivo — com revisão e histórico no git.
|
*/

return [

    'pedido' => [
        // COMPRAS-V1: quanto o valor unitário do item do PEDIDO pode passar do preço
        // cotado do mesmo item na cotação vencedora. Fração (0.05 = 5%). Default 0:
        // o preço do pedido é, no máximo, o preço cotado. Preço MENOR é sempre aceito.
        'tolerancia_preco_cotado' => 0.0,
    ],

    'pagamento' => [
        // COMPRAS-V2: teto do TOTAL pago (acumulado) sobre o total devido — cobre
        // juros/multa de última hora não lançados. 1.10 = até 110% do devido.
        'teto_total_pago' => 1.10,
    ],

    'cotacao_email' => [
        // COMPRAS-4: avisos "chegou resposta por e-mail" que o comprador recebe.
        // A referência [COT-ULID] é pública (vai no assunto): sem teto, qualquer
        // remetente que a conheça dispara avisos ilimitados com a marca Helix.
        //
        // Dois baldes por cotação/dia, de propósito: o flood de estranhos NÃO consome a
        // cota do fornecedor verdadeiro (remetente = e-mail do cadastro E SPF/DKIM/DMARC
        // aprovados) — senão o limite viraria um jeito de calar a resposta legítima.
        'avisos_por_remetente_dia' => 1,
        'avisos_de_estranhos_por_cotacao_dia' => 3,
        'avisos_do_fornecedor_por_cotacao_dia' => 5,
        // COMPRAS-4r: o remetente do CADASTRO sem SPF/DKIM tem balde PRÓPRIO. Antes ele
        // dividia o balde dos estranhos, e três mensagens forjadas com a referência
        // pública calavam por 24h o fornecedor de verdade cujo domínio não autentica.
        'avisos_do_fornecedor_sem_autenticacao_por_cotacao_dia' => 3,
    ],

    'proposta_publica' => [
        // COMPRAS-8: teto do valor unitário e do TOTAL da proposta enviada pelo link
        // público. O total também não pode passar de N vezes o valor estimado da
        // requisição (quando há estimativa) — proposta absurda é erro de digitação
        // ou abuso, e nos dois casos volta como erro de validação amigável.
        'valor_unitario_maximo' => 99999999.99,
        'valor_total_maximo' => 999999999.99,
        'multiplo_maximo_do_estimado' => 100,
        'validade_maxima_anos' => 2,

        // COMPRAS-4r: recusas por incoerência toleradas a cada 24h. O teto era ÚNICO por
        // link — três tentativas incoerentes de QUALQUER origem trancavam o link por 24h,
        // inclusive para a proposta COERENTE do fornecedor legítimo. Agora quem erra paga
        // (balde por ORIGEM) e o teto por link segue barrando a sondagem do orçamento por
        // tentativa e erro, só que sem virar botão de "calar o fornecedor".
        'tentativas_incoerentes_por_origem_dia' => 3,
        'tentativas_incoerentes_por_link_dia' => 10,
    ],

];
