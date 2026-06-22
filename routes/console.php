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
