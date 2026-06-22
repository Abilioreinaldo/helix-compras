# Runbook de Go-Live — Comendador Compras (v1)

Passo a passo para colocar a v1 em produção (MySQL). Validado contra MySQL 8.0.46.

> **Regra de ouro:** produção é **MySQL**, os testes rodam em **SQLite**. Toda a validação
> de dialeto está no checklist do `PLANO.md` (seções A–D, todas ✅). Não pular a ordem das
> migrations (passo 3) se o banco já tiver dados legados.

---

## 0. Pré-requisitos

- PHP 8.4, Composer, Node 18+ (build do front).
- MySQL 8.0+ com banco criado em **utf8mb4 / utf8mb4_unicode_ci** (crítico — base do item C6):
  ```sql
  CREATE DATABASE comendador CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'comendador'@'%' IDENTIFIED BY '<senha forte>';
  GRANT ALL ON comendador.* TO 'comendador'@'%';
  FLUSH PRIVILEGES;
  ```
- SMTP configurado (o sistema envia e-mails: aprovação, recebimento, lembrete +48h).
- Cron disponível no servidor (para o scheduler).

---

## 1. Configuração (.env de produção)

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://compras.suaempresa.com

DB_CONNECTION=mysql
DB_HOST=...
DB_PORT=3306
DB_DATABASE=comendador
DB_USERNAME=comendador
DB_PASSWORD=<senha forte>

# E-mails: o sistema só envia se o mailer estiver configurado.
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="compras@suaempresa.com"

# Fila: Mailables são Queueable. Em produção use uma fila + worker (passo 5),
# OU 'sync' se preferir envio inline (mais simples, sem worker):
QUEUE_CONNECTION=database   # (ou 'sync' para envio imediato sem worker)
```

`php artisan key:generate` se ainda não houver `APP_KEY`.

---

## 2. Instalação de dependências + build do front

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build      # gera o manifest do Vite (senão dá ViteException)
```

---

## 3. Migrations — ORDEM OBRIGATÓRIA

### 3a. Instalação NOVA (banco vazio, sem dados legados)
Sem duplicatas a sanear → roda tudo de uma vez:
```bash
php artisan migrate --force
```
(`--force` é obrigatório em produção.)

### 3b. Banco com DADOS LEGADOS de estoque (migração de base existente)
A constraint UNIQUE de catálogo (`saldos_estoque_catalogo_unique`) **trava** se houver
saldos duplicados. Ordem mandatória (item A3 do PLANO):

1. Migrar **até antes** da migration do UNIQUE de catálogo
   (`2026_06_16_153000_add_unique_catalogo_to_saldos_estoque`):
   ```bash
   php artisan migrate --force --step   # avance até o passo anterior ao UNIQUE
   ```
   (ou aplique migrations seletivamente até esse ponto)
2. Auditar e fundir duplicatas legadas:
   ```bash
   php artisan estoque:sanear-duplicatas-catalogo --dry-run
   php artisan estoque:sanear-duplicatas-catalogo --executado-por=<ID do Admin>
   ```
3. **Só então** aplicar o resto (cria o UNIQUE + coluna gerada STORED):
   ```bash
   php artisan migrate --force
   ```

> Se inverter a ordem num banco com duplicatas, a criação do índice falha (1062) e o deploy trava.

---

## 4. Cache de produção

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> Se mexer no `.env` depois, rode `php artisan config:clear && php artisan config:cache`.

---

## 5. Scheduler + fila (jobs automáticos)

O sistema tem **2 jobs agendados** (em `routes/console.php`):
- `requisicoes:marcar-atrasadas` — de hora em hora (SLA 24h de triagem).
- `aprovacoes:lembrar-pendentes` — diário 08:00 (lembrete de aprovação +48h).

Para o scheduler rodar, adicionar **uma** linha no cron do servidor:
```cron
* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

Se `QUEUE_CONNECTION` ≠ `sync` (recomendado para não bloquear requests no envio de e-mail),
subir um worker (supervisor/systemd):
```bash
php artisan queue:work --tries=3 --max-time=3600
```
(e `php artisan migrate` já criou a tabela `jobs` se usar `database`.)

---

## 6. Smoke check pós-deploy (logado como Admin)

- [ ] Login funciona; menu lateral aparece conforme perfil.
- [ ] Abrir/aprovar uma requisição de teste (fluxo de alçada + e-mail de aprovação).
- [ ] Registrar um Pedido de Compra → Recebimento → entra no estoque (Saldos).
- [ ] Catálogo: ligar `controla_lote` num item (saldo 0); recebimento coleta lote/validade.
- [ ] Saída/triagem mostra alerta de "vencido" quando aplicável.
- [ ] Transferência entre unidades debita origem e credita destino (Saldos → "Transferir").
- [ ] Rateio: `php artisan rateio:executar-mensal --executado-por=<Admin>` (mês anterior).
- [ ] Relatórios (Posição de estoque, Custo por obra, Tempo de aprovação) abrem sem erro de SQL.
- [ ] Forçar o lembrete: `php artisan aprovacoes:lembrar-pendentes` e conferir e-mail.

---

## 7. Rollback

- Migrations de v1.1-C/rateio/transferência são **aditivas**; o `down()` da migration que
  torna `saldo_estoque_id` nullable é **praticamente irreversível** após existirem
  movimentações de rateio/transferência (documentado na própria migration). Em caso de
  problema, prefira corrigir adiante (forward-fix) a reverter migration em produção.
- Antes do deploy: **backup do banco** (`mysqldump`).

---

## Estado de qualidade no go-live

- Suíte: **465 testes verdes** (SQLite). Suíte contra MySQL: 449/465 (16 = 13 testes de
  fusão SQLite-only documentados + 3 já corrigidos). Ver nota no `PLANO.md`.
- Sec/QA (rito completo) nas fatias de dinheiro/ledger: lote/FEFO, rateio, transferência.
- Checklist MySQL A1–A6, B4/B5, C6/C7/C8, D9–D12 — todos validados em MySQL 8.0.46 real.
