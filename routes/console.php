<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Agendamentos (auditoria multitenant 2026-09-15 — pilar Infra).
|
| onOneServer(): dedup entre NÓS (multi-instância) — exige lock store
| COMPARTILHADO: CACHE_STORE=redis (ou database) em produção. Com file/array
| cada nó tem o próprio lock e a tarefa roda N vezes.
| withoutOverlapping(): impede sobreposição da MESMA tarefa no mesmo nó (uma
| execução lenta de um tenant grande não empilha a próxima por cima).
*/

Schedule::command('requisicoes:marcar-atrasadas')->hourly()->withoutOverlapping()->onOneServer();
Schedule::command('aprovacoes:lembrar-pendentes')->dailyAt('08:00')->withoutOverlapping()->onOneServer();
Schedule::command('cotacoes:capturar-respostas')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('precos:expirar-homologacoes')->dailyAt('00:30')->withoutOverlapping()->onOneServer();

// Retencao da fundacao: arquiva (.jsonl.gz) e expurga events processados/audit_logs
// antigos, e drena os failed_jobs — antes cresciam sem teto (achado 4.10).
Schedule::command('platform:prune --force')->dailyAt('03:30')->withoutOverlapping()->onOneServer();
Schedule::command('queue:prune-failed --hours=168')->dailyAt('03:45')->withoutOverlapping()->onOneServer();

// Observabilidade (2.4): alerta de failed_jobs/dead_letter/backlog acima do teto.
Schedule::command('platform:health')->hourly()->withoutOverlapping()->onOneServer();
