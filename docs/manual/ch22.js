const L = require("./lib");
const { run } = L;
const b = (t) => run(t, { bold: true });

module.exports = () => [
  L.H1("22. Boas Práticas"),
  L.P("Recomendações para extrair o máximo do sistema: menos retrabalho, compras melhores, aprovações mais rápidas e dados confiáveis."),

  L.H2("22.1 Como evitar retrabalho"),
  L.bullet([b("Use itens de catálogo: "), run("padronizam, habilitam estoque e via expressa, e evitam descrições ambíguas que travam a cotação.")]),
  L.bullet([b("Capriche na justificativa: "), run("ela acompanha a requisição e reduz idas e vindas com a Compradora e os aprovadores.")]),
  L.bullet([b("Prefira a decisão por linha a reprovar tudo: "), run("rejeitar um item ruim evita o ciclo reprovar → corrigir → recotar → reaprovar.")]),

  L.H2("22.2 Como melhorar a qualidade das compras"),
  L.bullet([b("Mantenha o catálogo homologado em dia: "), run("preços homologados atualizados alimentam a via expressa e dão referência de preço justa.")]),
  L.bullet([b("Use o Mapa de Cotação para decidir: "), run("compare por item, não só pelo total; às vezes dividir entre fornecedores compensa.")]),
  L.bullet([b("Agrupe requisições: "), run("consolidar compras do mesmo fornecedor ganha escala e reduz o custo unitário.")]),

  L.H2("22.3 Como acelerar as aprovações"),
  L.bullet([b("Garanta aprovadores por nível em cada unidade: "), run("a falta de um aprovador trava a fila. Use suplentes quando alguém estiver ausente.")]),
  L.bullet([b("Acompanhe o Pendentes por Aprovador: "), run("identifique e cobre quem está segurando a fila.")]),
  L.bullet([b("Padronize a cauda longa na via expressa: "), run("compras pequenas e repetitivas saem em um clique, liberando a Compradora para o que é estratégico.")]),

  L.H2("22.4 Como reduzir erros"),
  L.bullet([b("Siga os checklists (cap. 21): "), run("a maioria dos erros vem de pular uma verificação simples.")]),
  L.bullet([b("Concilie em ciclos curtos: "), run("divergências bancárias são mais fáceis de achar perto do lançamento.")]),
  L.bullet([b("Faça inventários cíclicos: "), run("contagens menores e frequentes são mais precisas que um inventário geral anual.")]),

  L.H2("22.5 Como organizar fornecedores"),
  L.bullet([b("Homologue antes de comprar: "), run("só fornecedor homologado e ativo pode ser cotado; mantenha o cadastro limpo.")]),
  L.bullet([b("Defina preferenciais no catálogo: "), run("o fornecedor preferencial agiliza o desempate na via expressa.")]),
  L.bullet([b("Revise periodicamente o Gastos por Fornecedor: "), run("concentre volume nos melhores e renegocie os mais caros.")]),

  L.H2("22.6 Governança e conformidade"),
  L.bullet([b("Respeite a alçada: "), run("ela existe para proteger a empresa; a regra anti-fracionamento impede contorná-la.")]),
  L.bullet([b("Confie na trilha de auditoria: "), run("tudo é registrado. Em dúvidas e disputas, o histórico é a fonte da verdade.")]),
  L.bullet([b("Trate o Financeiro com rigor: "), run("é área de dinheiro — confira sempre antes de confirmar pagamentos e conciliações.")]),

  L.callout("bp", "Em uma frase: padronize a cauda longa (catálogo homologado + via expressa), reserve o esforço humano para as compras estratégicas, e confie no processo — cotação, alçada e auditoria — para manter tudo sob controle."),

  L.H1("Encerramento"),
  L.P("Este manual cobre o ciclo completo de compras do HELIX Compras, do acesso ao pagamento, refletindo o comportamento real do sistema. Mantenha-o à mão durante o onboarding e use-o como referência na operação do dia a dia."),
  L.callout("info", "Dúvidas não cobertas aqui devem ser encaminhadas ao Administrador do sistema ou à equipe de produto, que mantêm este documento atualizado a cada nova versão."),
];
