<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('requisicoes:marcar-atrasadas')->hourly();
Schedule::command('aprovacoes:lembrar-pendentes')->dailyAt('08:00');
Schedule::command('cotacoes:capturar-respostas')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('precos:expirar-homologacoes')->dailyAt('00:30');

// Retencao da fundacao: arquiva (.jsonl.gz) e expurga events processados/audit_logs
// antigos, e drena os failed_jobs — antes cresciam sem teto (achado 4.10).
Schedule::command('platform:prune --force')->dailyAt('03:30')->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=168')->dailyAt('03:45')->withoutOverlapping();
