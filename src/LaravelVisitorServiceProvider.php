<?php

namespace RPillz\LaravelVisitor;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use RPillz\LaravelVisitor\Commands\ForgetVisitorCommand;
use RPillz\LaravelVisitor\Commands\InstallVisitorCommand;
use RPillz\LaravelVisitor\Commands\PruneVisitsCommand;
use RPillz\LaravelVisitor\Http\Middleware\TrackVisit;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelVisitorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-visitor')
            ->hasConfigFile()
            ->hasMigration('create_visits_table')
            ->hasMigration('create_visitor_ignores_table')
            ->hasCommands([
                InstallVisitorCommand::class,
                PruneVisitsCommand::class,
                ForgetVisitorCommand::class,
            ]);
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(LaravelVisitor::class);

        $connectionName = config('visitor.connection', 'visitor');

        if (! config('database.connections.'.$connectionName)) {
            $this->autoRegisterConnection($connectionName);
        }
    }

    protected function autoRegisterConnection(string $connectionName): void
    {
        $driver = config('visitor.db.driver', 'sqlite');

        if ($driver === 'libsql') {
            $libsqlConfig = [
                'driver' => 'libsql',
                'database' => config('visitor.db.database') ?: null,
                'url' => config('visitor.db.url'),
                'authToken' => config('visitor.db.auth_token'),
                'syncInterval' => (int) config('visitor.db.sync_interval', 5),
                'read_your_writes' => (bool) config('visitor.db.read_your_writes', true),
                'encryptionKey' => config('visitor.db.encryption_key'),
                'prefix' => '',
            ];

            config(['database.connections.'.$connectionName => $libsqlConfig]);

            // The turso driver's connection factory always reads from 'database.connections.libsql'
            // regardless of the named connection. Mirror the config there if it isn't set.
            if (! config('database.connections.libsql')) {
                config(['database.connections.libsql' => $libsqlConfig]);
            }

            return;
        }

        config(['database.connections.'.$connectionName => [
            'driver' => 'sqlite',
            'database' => config('visitor.db.database') ?: storage_path('app/visitor.sqlite'),
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);
    }

    public function boot(): void
    {
        parent::boot();

        $this->app['router']->aliasMiddleware('visitor.track', TrackVisit::class);

        if (config('visitor.auto_track', true)) {
            $this->app['router']->pushMiddlewareToGroup('web', TrackVisit::class);
        }

        Route::get('robots.txt', function () {
            if (! config('visitor.robots_txt.enabled', false)) {
                abort(404);
            }

            $lines = [];
            foreach (config('visitor.robots_txt.disallow', []) as $agent) {
                $lines[] = "User-agent: {$agent}";
                $lines[] = 'Disallow: /';
                $lines[] = '';
            }

            return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=utf-8']);
        })->name('visitor.robots-txt');

        $this->warnIfQueueIsSynchronous();
    }

    protected function warnIfQueueIsSynchronous(): void
    {
        if (app()->runningUnitTests() || app()->runningInConsole()) {
            return;
        }

        $connection = config('visitor.queue.connection')
            ?? config('queue.default', 'sync');

        $driver = config("queue.connections.{$connection}.driver", $connection);

        if ($driver === 'sync') {
            Log::warning(
                'laravel-visitor: The queue driver is set to "sync". '.
                'Visit tracking will run synchronously after each response. '.
                'Consider configuring a real queue driver for better performance.'
            );
        }
    }
}
