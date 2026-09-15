<?php

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\RateLimiter;

/*
| Auditoria multitenant 2026-09-15 — pilar Infra/DevOps. Invariantes que, se
| regredirem, deixam um tenant prejudicar os demais (job em dobro, tarefa
| agendada N vezes em multi-nó, um tenant esgotando o app de todos).
*/

it('mantém retry_after acima do maior timeout de job (senão o job roda em dobro)', function () {
    $maxTimeout = 60; // --timeout padrão do queue:work (ProcessDomainEvent da fundação)

    $jobs = app_path('Jobs');
    if (is_dir($jobs)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($jobs, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $class = 'App\\'.str_replace([app_path().DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, '.php'], ['', '\\', ''], $file->getPathname());
            if (! class_exists($class)) {
                continue;
            }

            $timeout = (new ReflectionClass($class))->getDefaultProperties()['timeout'] ?? null;
            if (is_int($timeout)) {
                $maxTimeout = max($maxTimeout, $timeout);
            }
        }
    }

    foreach (['database', 'redis'] as $connection) {
        expect((int) config("queue.connections.{$connection}.retry_after"))->toBeGreaterThan($maxTimeout);
    }
});

it('agenda toda tarefa com onOneServer e withoutOverlapping', function () {
    Artisan::call('schedule:list'); // carrega routes/console.php

    $events = app(Schedule::class)->events();
    expect($events)->not->toBeEmpty();

    foreach ($events as $event) {
        $label = $event->description ?: $event->command;
        expect($event->onOneServer)->toBeTrue("sem onOneServer: {$label}");
        expect($event->withoutOverlapping)->toBeTrue("sem withoutOverlapping: {$label}");
    }
});

it('aplica o limiter web a todo o grupo web', function () {
    expect(app(HttpKernel::class)->getMiddlewareGroups()['web'])->toContain('throttle:web');
});

it('isola a cota do limiter web por tenant+usuário, com IP para visitante', function () {
    $limiter = RateLimiter::limiter('web');
    $userModel = config('auth.providers.users.model');

    $keyFor = function (?string $tenantId, ?int $userId) use ($limiter, $userModel): string {
        $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '10.0.0.9']);

        if ($userId !== null) {
            $user = (new $userModel)->forceFill(['id' => $userId, 'tenant_id' => $tenantId]);
            $request->setUserResolver(fn () => $user);
        }

        $limit = $limiter($request);
        expect($limit)->toBeInstanceOf(Limit::class)
            ->and($limit->maxAttempts)->toBe(300);

        return $limit->key;
    };

    $a = $keyFor('tenant-a', 1);

    expect($a)->toContain('tenant-a')->toContain('user:1')
        ->and($keyFor('tenant-b', 1))->not->toBe($a)
        ->and($keyFor('tenant-a', 2))->not->toBe($a)
        ->and($keyFor(null, null))->toBe('ip:10.0.0.9');
});
