<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

// Serveur multithreadé (Apache + PHP thread-safe sous Windows) : l'environnement du processus (getenv/putenv)
// est partagé entre les requêtes simultanées. Le .env pouvait y être lu vide : APP_ENV « production », pas de clé
// de chiffrement, et connexion PostgreSQL par défaut (port 5432, utilisateur root) — donc potentiellement une
// autre base. Les variables sont lues dans $_ENV / $_SERVER, propres à chaque requête.
Illuminate\Support\Env::disablePutenv();

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        $schedule->command('ged:expiration-alerte')->dailyAt('07:00');
        $schedule->command('ged:ocr-traiter')->dailyAt('02:30');
        $schedule->command('admin-technique:capturer-sante')->hourly()->withoutOverlapping();
        $schedule->command('backup:executer --type=base')->dailyAt('02:00')->withoutOverlapping();
        $schedule->command('ged:conservation-appliquer')->weeklyOn(1, '06:00');
        $schedule->command('recettes:relances-auto')->weeklyOn(1, '07:30');
        $schedule->command('workflow:relances')->hourly()->withoutOverlapping();   // rappels comptés en heures (config notifications.rappels)
        $schedule->command('alertes:detecter-budget')->dailyAt('06:30');
        $schedule->command('relances:metier')->dailyAt('09:00');
        $schedule->command('notifications:retry-email')->dailyAt('10:00');
        $schedule->command('notifications:purge-archives')->monthlyOn(1, '03:00');
        // Sans effet tant que AUDIT_PURGE_ENABLED=false (aucune durée de conservation validée).
        $schedule->command('audit:purge-journaux')->monthlyOn(1, '04:00')->withoutOverlapping();
        $schedule->command('audit:journal sceller')->hourly()->withoutOverlapping();
        $schedule->command('audit:journal verifier')->dailyAt('05:30')->withoutOverlapping();
        $schedule->command('data:integrity-check --persister')
            ->weeklyOn(0, '05:00')
            ->withoutOverlapping()
            ->when(fn () => config('audit.integrity.enabled', true));
        $schedule->command('alertes:escalader')->dailyAt('11:00');
        $schedule->command('webhooks:relivrer')->everyMinute();
        $schedule->command('api:agreger-metriques')->dailyAt('01:15');
        $schedule->command('sanctum:prune-expired --hours=24')->dailyAt('03:30');

        if (config('reporting.schedule.enabled', true)) {
            $log = storage_path('logs/reporting-schedule.log');
            if (config('reporting.schedule.trimestriel', true)) {
                $schedule->command('reporting:generer-trimestre --format=pdf')
                    ->cron('0 6 1 1,4,7,10 *')
                    ->withoutOverlapping()
                    ->appendOutputTo($log);
            }
            if (config('reporting.schedule.semestriel', true)) {
                $schedule->command('reporting:generer-semestre --format=pdf')
                    ->cron('0 6 1 1,7 *')
                    ->withoutOverlapping()
                    ->appendOutputTo($log);
            }
            if (config('reporting.schedule.annuel', true)) {
                $schedule->command('reporting:generer-annuel --format=pdf')
                    ->cron('0 6 1 1 *')
                    ->withoutOverlapping()
                    ->appendOutputTo($log);
            }
        }
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->prepend(App\Http\Middleware\IdentifiantRequete::class);
        $middleware->validateCsrfTokens(except: [
            'csp-report',
        ]);
        // Compression en tête du groupe web : elle s'applique à la réponse finale, après tous les autres middlewares.
        $middleware->web(prepend: [App\Http\Middleware\CompresserReponse::class]);
        $middleware->web(append: [
            App\Http\Middleware\EntetesSecurite::class,
            App\Http\Middleware\ForcePasswordChange::class,
            App\Http\Middleware\ForceMfaSetup::class,
            App\Http\Middleware\EnsureExerciceOuvert::class,
            App\Http\Middleware\ArchiverDocumentsGeneres::class,
        ]);
        // Intégration GED automatique des documents générés par l'API (la SPA télécharge aussi les PDF).
        $middleware->api(append: [App\Http\Middleware\ArchiverDocumentsGeneres::class]);
        $middleware->alias([
            'ability' => Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            'abilities' => Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability.or.session' => App\Http\Middleware\EnsureApiAbilityOrSession::class,
            'api.log' => App\Http\Middleware\JournaliserApi::class,
            'api.connected' => App\Http\Middleware\VerifierApplicationConnectee::class,
            'exercice.ouvert' => App\Http\Middleware\EnsureExerciceOuvert::class,
        ]);
        $middleware->prependToPriorityList(
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            App\Http\Middleware\JournaliserApi::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // Journal de sécurité : accès refusés (HTTP 403), une trace par acteur et par chemin et par minute.
        $exceptions->render(function (Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() === 403) {
                App\Support\JournalSecurite::accesRefuse($request, $e->getMessage());
            }

            return null;
        });
    })->create();
