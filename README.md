# HELIX Compras — Rede Comendador

ERP de compras da suíte **HELIX**. Requisição → cotação → aprovação por alçada → pedido de
compra → recebimento → estoque → contas a pagar. Compras 100% centralizadas na Compradora
Sênior; requisições partem das unidades; estoque e consumo controlados por unidade.

É um **app próprio, com login próprio**, construído sobre o pacote de fundação
[`helix/foundation`](../helix-foundation) (Identity + 2FA, RBAC, Company, Audit, Event,
Notifications, entitlements). O acesso é liberado pelo entitlement `feature:compras`.

## Arquitetura (importante)

- **1 tenant por instalação.** O domínio de compras é particionado por **`unidade_id`**, não
  por `tenant_id` — não há coluna de tenant nas tabelas de negócio. A camada de identidade e
  os middlewares `tenant.ctx` / `tenant.ativo` / `feature:compras` vêm da fundação e servem ao
  controle comercial (suspensão de tenant, entitlement), não a isolar dados de compras entre
  tenants no mesmo banco. **Multi-tenant real = uma instalação (um banco) por cliente.**
- **Banco de identidade compartilhado** com o app People (mesmas credenciais + 2FA), via
  `path` repository do `helix/foundation`. O domínio de compras usa IDs auto-incrementais
  próprios, sem colisão com a fundação.
- Autorização é feita in-line nos componentes Livewire / Actions (`abort_unless`, `temPerfil()`,
  checagem de nível de alçada) — **não há `app/Policies`**.

## Papéis

Solicitante · Compradora Sênior · Aprovador (por nível de alçada) · Almoxarife · Financeiro · Admin.

## Requisitos

- PHP 8.4, Composer, Node 18+
- Dev: **SQLite**. **Produção: MySQL 8.0+** (ver regra de portabilidade abaixo).

## Instalação (dev)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install && npm run build   # ou: composer run dev (serve + queue + vite)
```

## Testes

```bash
php artisan test --compact                       # suíte completa (Pest)
php artisan test --compact --filter=NomeDoTeste  # um caso
vendor/bin/pint --format agent                   # estilo, antes de commitar
```

## Regra de ouro: produção é MySQL, testes rodam em SQLite

Nunca usar função/sintaxe de um dialeto sem ramo por `DB::getDriverName()`
(ex.: `julianday`/`TIMESTAMPDIFF`, enum via `ALTER`, índice parcial vs coluna gerada `STORED`).
Todo ponto cego SQL fica registrado no checklist do [`PLANO.md`](PLANO.md) (seções A–D) e deve
ser validado em MySQL real **antes** do go-live. Ver [`RUNBOOK-GO-LIVE.md`](RUNBOOK-GO-LIVE.md).

## Documentação

- [`ESCOPO.md`](ESCOPO.md) — escopo funcional e perfis
- [`PLANO.md`](PLANO.md) — fases, decisões e checklist MySQL pré-go-live
- [`RUNBOOK-GO-LIVE.md`](RUNBOOK-GO-LIVE.md) — passo a passo de produção
- [`claude.md`](claude.md) / [`AGENTS.md`](AGENTS.md) — regras de trabalho no repositório
