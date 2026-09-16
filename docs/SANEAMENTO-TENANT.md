# Saneamento multitenant — relatório de integridade (passo 1 do plano do DBA)

Decisão 15. O comando `helix:integridade-tenant` levanta, **somente com SELECT**, o que
impediria as FKs compostas `(fk, tenant_id) → mãe(id, tenant_id)` de serem criadas no
MySQL. Ele não corrige nada: a reconciliação é decisão do DBA com o dono do dado.

> **Nunca rode contra a base local de desenvolvimento nem contra o primário de produção.**
> O alvo é uma **cópia restaurada da produção**, com usuário de banco somente leitura.

## O que o relatório mostra

| Seção | O que conta | Reprova (exit ≠ 0)? |
|---|---|---|
| FKs de domínio | Para cada FK `filha.coluna → mãe` (as duas com `tenant_id`), descoberta no schema das tabelas criadas em `database/migrations`: **órfãos** (mãe inexistente) e **cruzados** (mãe existe em outro tenant). Saída ordenada mãe → filha. | **Sim**, se houver órfão ou cruzado |
| `tenant_id` nulos | Linhas sem tenant, por tabela. | Não (mas bloqueia o passo 5) |
| FKs para `users` | Autoria (`solicitante_id`, `criada_por`, …) cujo usuário **não tem vínculo ativo** (`tenant_user.status = active`) no tenant da linha. | Não — informativo: autor que saiu da empresa é histórico legítimo |

FKs para `tenants` e para tabelas globais sem `tenant_id` (ex.: `bancos`) não entram.

## Como rodar (em cópia da produção)

1. Restaurar o dump mais recente num MySQL 8 **isolado** (staging de dados, sem tráfego).
2. Criar um usuário só leitura para o relatório:
   ```sql
   CREATE USER 'helix_integridade'@'%' IDENTIFIED BY '<senha forte>';
   GRANT SELECT ON <base_copia>.* TO 'helix_integridade'@'%';
   ```
3. Numa cópia do app (mesmo commit da produção), apontar o `.env` para a cópia:
   `DB_HOST=<copia>`, `DB_DATABASE=<base_copia>`, `DB_USERNAME=helix_integridade`.
   Não rode `migrate` nesse ambiente.
4. Gerar o relatório:
   ```bash
   php artisan helix:integridade-tenant            # tabelas legíveis
   php artisan helix:integridade-tenant --json > integridade-$(date +%F).json
   echo $?                                         # 0 = sem órfão/cruzado; 1 = há bloqueios
   ```
5. Anexar o JSON ao ticket de saneamento. Para cada linha com contagem > 0, levantar as
   linhas com as queries abaixo e decidir caso a caso (mover para o tenant certo,
   reapontar a FK ou remover) — **na produção, com backup, por script revisado**.

### Queries de diagnóstico

Substitua `filha`, `coluna`, `mae` pelos valores da linha do relatório.

```sql
-- órfãos
SELECT f.* FROM filha f
WHERE f.coluna IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM mae m WHERE m.id = f.coluna);

-- cruzados
SELECT f.*, m.tenant_id AS tenant_da_mae FROM filha f
JOIN mae m ON m.id = f.coluna
WHERE m.tenant_id <> f.tenant_id;

-- tenant_id nulo
SELECT * FROM filha WHERE tenant_id IS NULL;

-- autoria sem vínculo ativo (informativo)
SELECT f.* FROM filha f
WHERE f.coluna IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM tenant_user tu
                  WHERE tu.user_id = f.coluna AND tu.tenant_id = f.tenant_id AND tu.status = 'active');
```

## Plano do DBA (7 passos)

| # | Passo | Situação |
|---|---|---|
| 1 | **Saneamento** — este relatório em cópia da produção, até exit 0 e `tenant_id` nulo = 0 nas tabelas do passo 5 | **Entregue** (comando + testes) |
| 2 | **Backup** completo e verificado (restore testado) antes de qualquer DDL | Pendente — validação MySQL de infra |
| 3 | **Ensaio em staging MySQL** com a cópia saneada: cadeia de migrations inteira, tempos de ALTER, locks | Pendente — validação MySQL de infra |
| 4 | **Uniques `(id, tenant_id)` nas mães** (índice que o InnoDB exige para a FK composta) | Pendente — validação MySQL de infra |
| 5 | **`tenant_id` NOT NULL** nas tabelas de negócio | Pendente — validação MySQL de infra |
| 6 | **FKs compostas** com `ALGORITHM=INPLACE` / `foreign_key_checks=0` na criação, e **relatório repetido** logo depois (a criação com checks desligados não valida o legado — o relatório é a validação) | Pendente — validação MySQL de infra |
| 7 | **Rollback** ensaiado (`down()` das migrations + restore do backup do passo 2) | Pendente — validação MySQL de infra |

Já aplicado no código (fora do plano acima): a FK composta
`unidade_user(user_id, tenant_id) → tenant_user(user_id, tenant_id) ON DELETE CASCADE`
(decisão 17, migration `2026_09_21_000002`), que **aborta com a query de diagnóstico**
se encontrar vínculo órfão ou cruzado — rode este relatório antes do deploy dela.
