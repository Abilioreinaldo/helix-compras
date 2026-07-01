const L = require("./lib");

module.exports = () => [
  L.H1("19. Troubleshooting"),
  L.P("Mensagens que o sistema pode exibir, com a causa provável e a solução. Cada mensagem corresponde a uma regra de negócio que protege a integridade do processo."),

  L.H2("19.1 Requisição"),
  L.table(
    ["Mensagem", "Causa", "Solução"],
    [
      ["Nenhuma alçada configurada para este valor.", "Não há faixa de alçada cobrindo o valor total.", "O Administrador deve cadastrar/ajustar a faixa (cap. 13.6)."],
      ["Verba da obra esgotada (100%).", "A obra atingiu o limite do orçamento.", "Reduza o valor, troque a obra ou solicite revisão da verba."],
      ["A justificativa é obrigatória para compras emergenciais.", "Marcou Emergencial sem justificar.", "Preencha a justificativa (mín. 10 caracteres)."],
      ["Adicione ao menos um item.", "Requisição sem itens.", "Inclua pelo menos um item antes de submeter."],
    ],
    [3200, 3080, 3080],
  ),

  L.H2("19.2 Cotação"),
  L.table(
    ["Mensagem", "Causa", "Solução"],
    [
      ["São necessárias ao menos N cotação(ões) com valor confirmado.", "Faltam cotações para a faixa.", "Registre mais cotações com valor confirmado (ou use a Via Expressa)."],
      ["É necessário marcar exatamente uma cotação como vencedora.", "Nenhuma ou mais de uma vencedora.", "Marque exatamente uma cotação como vencedora."],
      ["Fornecedor não homologado / inativo.", "Tentou cotar fornecedor desqualificado.", "Use fornecedor homologado e ativo, ou homologue-o antes (Admin)."],
    ],
    [3200, 3080, 3080],
  ),

  L.H2("19.3 Aprovação"),
  L.table(
    ["Mensagem", "Causa", "Solução"],
    [
      ["Esta requisição não está aguardando aprovação.", "Status mudou ou ação fora de hora.", "Atualize a tela; confira o status atual da requisição."],
      ["O solicitante não pode aprovar a própria requisição.", "Aprovador = solicitante.", "Outro aprovador do nível deve decidir."],
      ["Você não tem permissão para aprovar esta etapa.", "Nível/unidade incompatível.", "Confirme o seu nível de alçada e a unidade da requisição."],
      ["Não é possível rejeitar todos os itens.", "Marcou todos para rejeitar.", "Para barrar a compra inteira, use Reprovar."],
      ["Informe o motivo da rejeição de cada item.", "Item marcado sem motivo.", "Preencha o motivo de cada item rejeitado."],
      ["Não há aprovadores com nível '…' cadastrados nesta unidade.", "Falta aprovador do nível exigido.", "O Administrador deve vincular um aprovador desse nível à unidade."],
    ],
    [3200, 3080, 3080],
  ),

  L.H2("19.4 Via Expressa"),
  L.table(
    ["Mensagem", "Causa", "Solução"],
    [
      ["A requisição não está elegível à via expressa…", "Item avulso, sem homologação válida ou fornecedores distintos.", "Use o fluxo normal de cotação, ou ajuste o catálogo/homologações (Admin)."],
    ],
    [3200, 3080, 3080],
  ),

  L.H2("19.5 Estoque"),
  L.table(
    ["Mensagem", "Causa", "Solução"],
    [
      ["Saldo insuficiente / saída bloqueada.", "Quantidade pedida maior que o saldo.", "Confira o saldo; reponha via requisição de compra se necessário."],
      ["Requisição contém item avulso. Atendimento direto não permitido.", "Atendimento do estoque com item avulso.", "Itens avulsos não têm saldo; trate por compra."],
      ["⚠️ Vencido (alerta na saída).", "A saída debitaria um lote vencido.", "Apenas alerta; avalie antes de confirmar a saída."],
    ],
    [3200, 3080, 3080],
  ),

  L.callout("info", "Toda mensagem de bloqueio corresponde a uma regra do capítulo 15. Se uma mensagem não estiver aqui, anote-a e consulte o Administrador — ela reflete uma regra de proteção do processo."),
];
