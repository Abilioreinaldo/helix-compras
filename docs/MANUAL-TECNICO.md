# Manual Técnico — Comendador Compras v1

**Versão v1 · 22/06/2026 · Público: TI, DevOps, Tech Lead**

---

## Índice

1. [Arquitetura](#1-arquitetura)
2. [Pré-requisitos e Deploy](#2-pré-requisitos-e-deploy)
3. [Variáveis de Ambiente](#3-variáveis-de-ambiente)
4. [Configuração IMAP](#4-configuração-imap)
5. [Scheduler e Jobs](#5-scheduler-e-jobs)
6. [Monitoring](#6-monitoring)
7. [Troubleshooting](#7-troubleshooting)
8. [Backup e Restore](#8-backup-e-restore)
9. [Performance Tuning](#9-performance-tuning)
10. [Segurança](#10-segurança)
11. [Update / Upgrade Path](#11-update--upgrade-path)

---

## 1. Arquitetura

### Stack

| Camada | Tecnologia | Versão |
|---|---|---|
| Linguagem | PHP | 8.4 |
| Framework | Laravel | 13 |
| UI reativa | Livewire | 4 |
| Front-end CSS | Tailwind CSS | 4 |
| Bundler | Vite | — |
| Banco (produção) | MySQL | 8.0+ |
| Banco (dev/testes) | SQLite | — |
| IMAP (PHP puro) | webklex/php-imap | ^6.2 |
| Fila padrão | database | — |
| PDF | barryvdh/laravel-dompdf | ^3.1 |
| Testes | Pest | 4 |

> **Redis:** NÃO é usado nem requerido pelo projeto. Pode ser adotado opcionalmente para fila/cache em escala substituindo `QUEUE_CONNECTION=database`, mas o padrão funcional é `database`.

### Diagrama de Componentes

```
┌──────────────────────────────────────────────────────────────────┐
│  Navegador                                                       │
│  (HTML/Tailwind + Alpine.js via Livewire)                        │
└──────────────────────┬───────────────────────────────────────────┘
                       │ HTTPS
                       ▼
┌──────────────────────────────────────────────────────────────────┐
│  Proxy reverso (Nginx / Apache / Caddy)                          │
│  TLS termination · headers de segurança                          │
└──────────────────────┬───────────────────────────────────────────┘
                       │
                       ▼
┌──────────────────────────────────────────────────────────────────┐
│  Laravel 13 + Livewire 4  (PHP 8.4 / PHP-FPM)                   │
│                                                                  │
│  ┌──────────────┐  ┌──────────────┐  ┌───────────────────────┐  │
│  │  Controllers │  │  Livewire    │  │  Eloquent / Query     │  │
│  │  Requests    │  │  Components  │  │  Builder              │  │
│  └──────────────┘  └──────────────┘  └───────────┬───────────┘  │
│                                                   │              │
└───────────────────────────────────────────────────┼──────────────┘
                                                    │
                       ┌────────────────────────────▼──────────┐
                       │  MySQL 8.0+  (utf8mb4_unicode_ci)     │
                       │  tabela jobs (fila database)          │
                       └───────────────────────────────────────┘

── Scheduler (cron único: * * * * * php artisan schedule:run) ─────

  ┌──────────────────────────────────────────────────────────────┐
  │  php artisan schedule:run  (a cada minuto via cron)          │
  │                                                              │
  │  ├─ requisicoes:marcar-atrasadas    (a cada hora)            │
  │  ├─ aprovacoes:lembrar-pendentes   (diário 08:00)            │
  │  └─ cotacoes:capturar-respostas    (a cada 5 min, sem overlap│
  └───────┬──────────────────────────────────┬───────────────────┘
          │                                  │
          ▼                                  ▼
  ┌───────────────┐                 ┌────────────────────────────┐
  │  MySQL        │                 │  Caixa IMAP (opcional)     │
  │  (atualiza    │                 │  IMAP_HOST:993 / SSL       │
  │   status SLA) │                 │  webklex/php-imap (PHP     │
  └───────────────┘                 │  puro, sem ext-imap)       │
                                    └────────────────────────────┘

── Worker de fila (se QUEUE_CONNECTION=database) ──────────────────

  php artisan queue:work --tries=3
         │
         ▼  processa tabela `jobs`
  ┌─────────────────────────────────────┐
  │  Mailables (aprovação, recebimento, │  ──►  SMTP externo
  │  lembrete +48h)                     │
  └─────────────────────────────────────┘
```

### Isolamento Multi-Unidade

Cada usuário enxerga apenas os dados das suas próprias unidades via global scope `PertenceAUnidade`. Perfis Admin e CompradoraSenior têm visibilidade irrestrita. Os perfis existentes são: **Admin**, **CompradoraSenior**, **Aprovador** (sub-níveis: Gestor / Diretor / CEO), **Solicitante** e **Almoxarife**.

---

## 2. Pré-requisitos e Deploy

### Pré-requisitos mínimos do servidor

- PHP 8.4 com extensões padrão do Laravel (mbstring, pdo_mysql, openssl, etc.)
- Composer
- Node 18+ (somente para o build do front; não precisa ficar no servidor em produção)
- MySQL 8.0+ com banco criado em **utf8mb4 / utf8mb4_unicode_ci**
- SMTP configurado para envio de e-mails
- Cron disponível para o scheduler

### Resumo dos passos de deploy

O detalhe completo e atualizado está em **[../RUNBOOK-GO-LIVE.md](../RUNBOOK-GO-LIVE.md)**. O resumo abaixo serve de orientação rápida:

```bash
# 1. Clonar / atualizar o repositório
git pull origin master

# 2. Criar banco MySQL (instalação nova)
# CREATE DATABASE comendador CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# 3. Configurar .env de produção (ver Seção 3)
php artisan key:generate   # se ainda não houver APP_KEY

# 4. Instalar dependências PHP e buildar front
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# 5. Migrations (ver aviso abaixo)
php artisan migrate --force

# 6. Cache de produção
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Subir scheduler (ver Seção 5) e worker de fila (se QUEUE_CONNECTION=database)
```

### ⚠️ Ordem obrigatória das migrations em banco com dados legados

Em uma **instalação nova** (banco vazio), `php artisan migrate --force` roda tudo de uma vez sem problema.

Em um **banco com dados legados de estoque**, a constraint UNIQUE de catálogo (`saldos_estoque_catalogo_unique`) trava se já existirem registros duplicados. Siga esta sequência:

**Passo A** — Migrar até antes da migration do UNIQUE de catálogo (`2026_06_16_153000_add_unique_catalogo_to_saldos_estoque`):

```bash
php artisan migrate --force --step   # avance passo a passo até o ponto anterior ao UNIQUE
```

**Passo B** — Sanear duplicatas legadas:

```bash
php artisan estoque:sanear-duplicatas-catalogo --dry-run --executado-por=<ID do Admin>
php artisan estoque:sanear-duplicatas-catalogo --executado-por=<ID do Admin>
```

**Passo C** — Só então aplicar o restante das migrations:

```bash
php artisan migrate --force
```

> Se a ordem for invertida num banco com duplicatas, o MySQL retorna erro 1062 (Duplicate entry) e o deploy trava. Consulte o RUNBOOK-GO-LIVE.md, seção 3b, para o detalhe completo.

---

## 3. Variáveis de Ambiente

Tabela de todas as variáveis essenciais para produção. Copie `.env.example` e ajuste.

### Aplicação e Banco

| Variável | Exemplo | Descrição |
|---|---|---|
| `APP_ENV` | `production` | Ambiente da aplicação. Use `production` em produção. |
| `APP_DEBUG` | `false` | Nunca `true` em produção (expõe stack traces). |
| `APP_KEY` | `base64:...` | Chave de criptografia. Gerar com `php artisan key:generate`. |
| `APP_URL` | `https://compras.suaempresa.com` | URL base da aplicação (sem barra final). |
| `DB_CONNECTION` | `mysql` | Driver do banco. Em dev/testes usa-se `sqlite`. |
| `DB_HOST` | `127.0.0.1` | Host do MySQL. |
| `DB_PORT` | `3306` | Porta do MySQL. |
| `DB_DATABASE` | `comendador` | Nome do banco de dados. |
| `DB_USERNAME` | `comendador` | Usuário do banco. |
| `DB_PASSWORD` | `<senha forte>` | Senha do banco. |

### Fila

| Variável | Exemplo | Descrição |
|---|---|---|
| `QUEUE_CONNECTION` | `database` | Driver de fila. `database` recomendado (requer worker). Use `sync` para envio inline sem worker. Redis é opcional e não configurado por padrão. |

### E-mail (SMTP de envio)

| Variável | Exemplo | Descrição |
|---|---|---|
| `MAIL_MAILER` | `smtp` | Mailer padrão. `log` em dev (grava em storage/logs). |
| `MAIL_HOST` | `smtp.suaempresa.com` | Servidor SMTP. |
| `MAIL_PORT` | `587` | Porta SMTP. |
| `MAIL_USERNAME` | `compras@suaempresa.com` | Usuário SMTP. |
| `MAIL_PASSWORD` | `<senha>` | Senha SMTP. |
| `MAIL_FROM_ADDRESS` | `compras@suaempresa.com` | Endereço remetente dos e-mails do sistema. |
| `MAIL_FROM_NAME` | `Comendador Compras` | Nome do remetente. |

### IMAP (captura de respostas de cotação — opcional)

| Variável | Exemplo | Descrição |
|---|---|---|
| `IMAP_HOST` | `imap.suaempresa.com` | Host IMAP. **Se ausente, a captura é desativada sem erros.** |
| `IMAP_PORT` | `993` | Porta IMAP. Padrão: 993 (SSL). |
| `IMAP_USERNAME` | `cotacoes@suaempresa.com` | Usuário da caixa dedicada de cotações. |
| `IMAP_PASSWORD` | `<senha>` | Senha da caixa IMAP. |
| `IMAP_ENCRYPTION` | `ssl` | Protocolo de criptografia (`ssl` ou `tls`). |
| `IMAP_MAILBOX` | `INBOX` | Pasta monitorada. Padrão: `INBOX`. |

As variáveis de IMAP alimentam o bloco `mail.imap` em `config/mail.php`. O binding condicional em `AppServiceProvider` usa `webklex/php-imap` quando `mail.imap.host` está definido; caso contrário, ativa o fallback `LeitorCaixaCotacoesIndisponivel` (não quebra nada).

---

## 4. Configuração IMAP

### O que é

O command `cotacoes:capturar-respostas` monitora uma caixa de e-mail dedicada e casa automaticamente as respostas dos fornecedores com as cotações abertas. A correspondência é feita pelo token `[COT-{id}]` no assunto do e-mail, com validação de que o remetente coincide com o `contato_email` cadastrado no fornecedor. Os campos gravados na tabela `cotacoes` são: `valor_respondido`, `prazo_respondido`, `observacoes_fornecedor`, `resposta_recebida_em` e `email_externo_id` (UNIQUE para idempotência). O valor oficial da cotação nunca é alterado automaticamente (caráter advisory).

A biblioteca `webklex/php-imap` é PHP puro — **não exige a extensão `ext-imap` do PHP**.

### Passo a passo para habilitar

**1. Criar uma caixa de e-mail dedicada** no provedor de e-mail da empresa (ex.: `cotacoes@suaempresa.com`). Recomenda-se uma caixa separada da caixa de envio do sistema para evitar processar e-mails não relacionados.

**2. Adicionar as variáveis ao `.env` de produção:**

```dotenv
IMAP_HOST=imap.suaempresa.com
IMAP_PORT=993
IMAP_USERNAME=cotacoes@suaempresa.com
IMAP_PASSWORD=<senha da caixa>
IMAP_ENCRYPTION=ssl
IMAP_MAILBOX=INBOX
```

**3. Limpar e regenerar o cache de configuração:**

```bash
php artisan config:clear
php artisan config:cache
```

**4. Validar a conexão** inspecionando o log após o próximo ciclo do scheduler (a cada 5 minutos) ou forçando manualmente:

```bash
php artisan cotacoes:capturar-respostas
```

Erros de conexão são registrados em `storage/logs/laravel.log` com `Log::error`.

### Como o scheduler dispara a captura

O command é registrado em `routes/console.php`:

```php
Schedule::command('cotacoes:capturar-respostas')->everyFiveMinutes()->withoutOverlapping();
```

O modificador `withoutOverlapping()` garante que uma execução não se sobrepõe à anterior caso o processamento de muitos e-mails demore mais de 5 minutos.

O scheduler é acionado pelo cron único do servidor (ver Seção 5).

### Comportamento sem IMAP configurado

Se `IMAP_HOST` não estiver definido no `.env`, o `AppServiceProvider` registra automaticamente o fallback `LeitorCaixaCotacoesIndisponivel`. O command `cotacoes:capturar-respostas` executa sem erros e sem efeito colateral — nenhuma exception é lançada, nenhum log de erro é gerado. O restante do sistema não é afetado.

### Detalhes do cliente IMAP

- Classe concreta: `App\Imap\WebklexLeitorCaixaCotacoes`
- Interface mockável: `App\Imap\LeitorCaixaCotacoes`
- Modo de leitura: PEEK (não marca como lido imediatamente)
- Após processar com sucesso: marca a mensagem como lida
- Idempotência: `email_externo_id` UNIQUE impede reprocessamento do mesmo e-mail

---

## 5. Scheduler e Jobs

### Cron único do servidor

Adicione **uma única linha** no crontab do servidor (ou do usuário que executa o PHP):

```cron
* * * * * cd /caminho/absoluto/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

Todo o controle de frequência fica no `routes/console.php` — não é preciso criar entradas individuais no cron.

### Commands agendados

| Command | Frequência | Função |
|---|---|---|
| `requisicoes:marcar-atrasadas` | A cada hora | Marca requisições sem triagem há mais de 24h como atrasadas (SLA de triagem). |
| `aprovacoes:lembrar-pendentes` | Diário às 08:00 | Envia lembrete de e-mail para aprovações pendentes há mais de 48h. |
| `cotacoes:capturar-respostas` | A cada 5 minutos (withoutOverlapping) | Lê e-mails UNSEEN da caixa IMAP e grava respostas de fornecedores nas cotações correspondentes. |

### Commands manuais (não agendados)

| Command | Quando usar |
|---|---|
| `php artisan rateio:executar-mensal --executado-por=<ID do Admin>` | Executar o rateio de custos mensais. Requer ID de um usuário Admin. |
| `php artisan estoque:sanear-duplicatas-catalogo [--dry-run] --executado-por=<ID do Admin>` | Auditar e fundir registros duplicados no catálogo de estoque. Usar `--dry-run` primeiro para inspecionar sem alterar dados. |

### Worker de fila

Se `QUEUE_CONNECTION=database` (recomendado), é preciso manter um worker ativo. Configure via **Supervisor** ou **systemd**:

```bash
php artisan queue:work --tries=3 --max-time=3600
```

A tabela `jobs` é criada automaticamente pelo `php artisan migrate`. Os Mailables do sistema (aprovação, recebimento, lembrete) são `Queueable` e dependem do worker quando a fila não é `sync`.

---

## 6. Monitoring

### Health checks sugeridos

| Check | Como verificar | Frequência sugerida |
|---|---|---|
| Aplicação responde | HTTP GET em `APP_URL` — espera HTTP 200 | A cada 1 min |
| Scheduler rodou | Verificar se `storage/logs/laravel.log` tem atividade nos últimos 2 min | A cada 5 min |
| Fila processando | Monitorar o tamanho da tabela `jobs` no MySQL; alertar se crescer indefinidamente | A cada 5 min |
| Erros de IMAP | Buscar `Log::error` com `cotacoes:capturar-respostas` nos logs | Contínuo |

### Logs

Todos os logs da aplicação ficam em:

```
storage/logs/laravel.log
```

O framework usa o canal `stack` por padrão (configurável em `config/logging.php`). A captura IMAP registra:

- `Log::info` — quando uma resposta de fornecedor é capturada com sucesso
- `Log::error` — quando há falha de conexão IMAP ou erro ao processar uma mensagem

Em produção, recomenda-se rotação de logs (logrotate ou canal `daily` do Laravel).

### Alertas recomendados

- **Worker parado:** alertar quando a tabela `jobs` ultrapassar N registros sem decrescer por X minutos.
- **Scheduler silencioso:** ferramentas como Laravel Pulse, Healthchecks.io ou Cronitor podem verificar se o scheduler "bateu o ponto" nos últimos 2 minutos.
- **Erros 5xx crescentes:** monitorar no proxy reverso ou via ferramenta APM.
- **Caixa IMAP com falha repetida:** alertar quando `Log::error` de IMAP aparecer mais de N vezes consecutivas.

---

## 7. Troubleshooting

| # | Sintoma | Causa provável | Solução |
|---|---|---|---|
| 1 | Deploy trava com erro MySQL 1062 durante `migrate` | Tentativa de criar índice UNIQUE de catálogo com dados duplicados preexistentes | Seguir a **ordem obrigatória** da Seção 2: sanear duplicatas com `estoque:sanear-duplicatas-catalogo` antes de aplicar a migration do UNIQUE. |
| 2 | E-mails não saem (aprovação, lembrete) | `MAIL_MAILER=log` em produção, ou worker parado com `QUEUE_CONNECTION=database`, ou credenciais SMTP incorretas | Verificar `.env` (MAIL_MAILER, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD). Se `QUEUE_CONNECTION=database`, verificar se o worker está ativo (`php artisan queue:work`). |
| 3 | Captura IMAP não traz respostas de fornecedores | `IMAP_HOST` não configurado, credenciais inválidas, ou fornecedor respondeu de e-mail diferente do `contato_email` cadastrado | Verificar variáveis IMAP no `.env` e rodar `php artisan cotacoes:capturar-respostas` manualmente; checar `laravel.log` para erros de conexão. Confirmar que o e-mail do remetente coincide com o `contato_email` do fornecedor. |
| 4 | "Unable to locate file in Vite manifest" | `npm run build` não foi executado ou o diretório `public/build` está ausente | Executar `npm ci && npm run build` no servidor. Em dev, rodar `npm run dev` ou `composer run dev`. |
| 5 | Cotação não é casada com o e-mail recebido | Token `[COT-{id}]` ausente ou alterado no assunto pelo fornecedor, ou e-mail duplicado já processado | Verificar o assunto do e-mail recebido. O token deve estar exatamente como `[COT-{id}]`. Se `email_externo_id` já existe na tabela `cotacoes`, o e-mail foi processado anteriormente (idempotência). |
| 6 | Scheduler não roda | Linha de cron ausente ou incorreta, ou PHP não encontrado no PATH do cron | Verificar crontab (`crontab -l`). Usar o caminho absoluto do PHP e do projeto. Testar manualmente: `php artisan schedule:run`. |
| 7 | Fila parada (jobs acumulando) | Worker não está rodando ou caiu sem reiniciar automaticamente | Verificar processo do worker (`ps aux | grep queue:work`). Configurar Supervisor ou systemd para reiniciar automaticamente. Ver logs do worker. |
| 8 | Erro 419 / CSRF Token Mismatch | Sessão expirada, cookie de sessão inválido, ou `APP_URL` incorreto (protocolo HTTP vs HTTPS) | Verificar `APP_URL` no `.env` (deve coincidir com o protocolo real). Limpar cookies do navegador. Verificar configuração do proxy reverso (headers `X-Forwarded-Proto`). |
| 9 | Usuário não vê dados de outra unidade | Comportamento esperado — isolamento por global scope `PertenceAUnidade` | Não é bug. Apenas Admin e CompradoraSenior têm visibilidade irrestrita. Se o usuário precisa de acesso a outra unidade, vincular o perfil à unidade correspondente. |
| 10 | Erro de conexão IMAP nos logs (`laravel.log`) | Credenciais IMAP erradas, porta/criptografia incorreta, firewall bloqueando saída na porta 993 | Verificar `IMAP_HOST`, `IMAP_PORT`, `IMAP_USERNAME`, `IMAP_PASSWORD`, `IMAP_ENCRYPTION` no `.env`. Testar conectividade de rede do servidor na porta configurada. Limpar cache após ajustar: `php artisan config:cache`. |

---

## 8. Backup e Restore

### Backup do banco de dados

```bash
# Dump completo
mysqldump -u comendador -p comendador > backup_comendador_$(date +%Y%m%d_%H%M%S).sql

# Comprimido
mysqldump -u comendador -p comendador | gzip > backup_comendador_$(date +%Y%m%d_%H%M%S).sql.gz
```

Agendar backup diário via cron em horário de baixo uso. Armazenar em local externo ao servidor (S3, NAS, etc.).

### Restore do banco de dados

```bash
# A partir de dump não comprimido
mysql -u comendador -p comendador < backup_comendador_YYYYMMDD_HHmmss.sql

# A partir de dump comprimido
gunzip -c backup_comendador_YYYYMMDD_HHmmss.sql.gz | mysql -u comendador -p comendador
```

### Backup dos arquivos de cotação

Os arquivos anexos de cotação (PDF, JPG, PNG) ficam no disco `local` do Laravel (não público), em:

```
storage/app/
```

Esses arquivos são servidos por rota autenticada — não ficam expostos em `public/`. Incluir o diretório `storage/app/` no plano de backup junto com o dump do banco.

```bash
# Exemplo com rsync
rsync -az /caminho/do/projeto/storage/app/ /destino/backup/storage-app/
```

> Restaurar banco e `storage/app/` do mesmo snapshot para garantir consistência entre registros e arquivos.

---

## 9. Performance Tuning

### Cache de produção

Sempre manter os caches ativos em produção:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Após qualquer alteração no `.env` ou em arquivos de configuração, regenerar:

```bash
php artisan config:clear && php artisan config:cache
```

### Índices

O schema conta com índices nas tabelas de maior volume de consultas (`requisicoes`, `cotacoes`, `saldos_estoque` entre as principais). Não remover índices sem análise prévia de impacto. Em caso de lentidão em consultas específicas, usar `EXPLAIN` no MySQL para diagnóstico.

### N+1

O projeto usa Eloquent com relacionamentos. Ao adicionar novas listagens, sempre verificar se os relacionamentos estão sendo carregados com `with()` (eager loading) para evitar N+1 queries. Em páginas Livewire com listas grandes, considerar paginação.

### Fila para e-mails

Todos os Mailables são `Queueable`. Com `QUEUE_CONNECTION=database` e um worker ativo, o envio de e-mail não bloqueia o request do usuário. Em alto volume, avaliar a adoção de Redis como driver de fila (opcional).

### Front-end

O front é buildado com Vite e entregue como arquivos estáticos em `public/build/`. Configurar o proxy reverso para servir esses assets com headers de cache de longa duração (`Cache-Control: max-age=31536000, immutable`) — o Vite já gera nomes de arquivo com hash de conteúdo.

---

## 10. Segurança

### Implementado e validado

| Item | Status |
|---|---|
| CSRF automático em todos os formulários (Laravel + Livewire) | ✅ |
| SQL injection prevenido — Eloquent/Query Builder sem SQL cru a partir de input do usuário | ✅ |
| Isolamento multi-unidade via global scope `PertenceAUnidade` | ✅ |
| Autorização por perfil (`abort_unless temPerfil`) em todas as ações sensíveis | ✅ |
| Upload de cotação com validação de mimetypes (PDF/JPG/PNG, máx 10 MB) | ✅ |
| Arquivos de cotação no disco `local` (servidos por rota autenticada, não acessíveis publicamente) | ✅ |
| Senhas armazenadas com bcrypt | ✅ |
| Troca obrigatória de senha no primeiro acesso | ✅ |

### Recomendações operacionais

- **HTTPS/TLS obrigatório:** terminar TLS no proxy reverso (Nginx/Caddy/Apache). A aplicação assume que tráfego externo é sempre HTTPS com `APP_URL` iniciando com `https://`.
- **Headers de segurança HTTP:** configurar no proxy reverso `Strict-Transport-Security`, `X-Content-Type-Options`, `X-Frame-Options` e `Content-Security-Policy`.
- **Rotação periódica da senha da caixa IMAP:** atualizar `IMAP_PASSWORD` no `.env` e regenerar o cache de configuração. Preferir senhas de aplicativo (app password) quando o provedor suportar 2FA.
- **Least-privilege no banco:** o usuário MySQL da aplicação deve ter apenas `SELECT`, `INSERT`, `UPDATE`, `DELETE` no schema da aplicação — sem `DROP`, `CREATE`, `GRANT`. Migrations em produção podem ser executadas com um usuário de deploy separado com permissões mais amplas, revogadas após o deploy.
- **`APP_DEBUG=false` em produção:** stack traces expostos são vetores de information disclosure.
- **Rotação do `APP_KEY`:** se o `APP_KEY` for comprometido, gerar novo com `php artisan key:generate` e invalidar todas as sessões ativas.

---

## 11. Update / Upgrade Path

### Procedimento padrão de atualização

Executar o backup do banco antes de qualquer atualização (ver Seção 8).

```bash
# 1. Rodar a suíte completa no ambiente de staging antes de aplicar em produção
php artisan test --compact

# 2. Atualizar o código
git pull origin master

# 3. Atualizar dependências PHP (sem pacotes de dev)
composer install --no-dev --optimize-autoloader

# 4. Aplicar migrations
php artisan migrate --force
# ⚠️ Se houver migrations com restrições de unicidade em banco com dados legados,
#    seguir a ordem da Seção 2 (sanear antes do UNIQUE).

# 5. Rebuildar o front-end
npm ci && npm run build

# 6. Regenerar caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Reiniciar o worker de fila
php artisan queue:restart
# O comando sinaliza o worker para terminar o job atual e reiniciar.
# O Supervisor/systemd subirá o processo novamente automaticamente.
```

### Verificação pós-update

- Confirmar que a aplicação responde (health check HTTP).
- Verificar `storage/logs/laravel.log` por erros nas primeiras execuções.
- Executar o smoke check descrito no RUNBOOK-GO-LIVE.md (seção 6) se a atualização for significativa.

### Suíte de testes

A suíte conta com **490 testes Pest** rodando em SQLite. Rodar antes de qualquer deploy para garantir regressão zero. O banco de produção é MySQL — consultar as seções A–D do `PLANO.md` para o checklist de validação de dialeto SQL em MySQL real.

---

## Rodapé

**Comendador Compras — Manual Tecnico · v1 · 22/06/2026**

Documentos relacionados:

- [Manual da Compradora](MANUAL-COMPRADORA.md)
- [Runbook de Pilot](RUNBOOK-PILOT.md)
- [Runbook de Go-Live](../RUNBOOK-GO-LIVE.md)
