<?php

namespace RPillz\LaravelVisitor;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
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
            ->hasViews()
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
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'laravel-visitor');

        parent::boot();

        $this->ensureSqliteDatabaseExists();

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

    protected function ensureSqliteDatabaseExists(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $connectionName = LaravelVisitor::resolveConnection();
        $connection = config("database.connections.{$connectionName}");

        if (($connection['driver'] ?? null) !== 'sqlite') {
            return;
        }

        $path = $connection['database'] ?? null;

        if (! $path || $path === ':memory:' || file_exists($path)) {
            return;
        }

        try {
            $directory = dirname($path);
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            touch($path);

            $stubs = glob(__DIR__.'/../database/migrations/*.php.stub');
            sort($stubs);

            foreach ($stubs as $stub) {
                $migration = require $stub;
                $migration->up();
            }

            $this->markVisitorMigrationsAsRun();

            Log::info("laravel-visitor: Created SQLite database at {$path}.");
        } catch (\Throwable $e) {
            Log::error("laravel-visitor: Failed to auto-create SQLite database at {$path}: {$e->getMessage()}");
        }
    }

    protected function markVisitorMigrationsAsRun(): void
    {
        try {
            if (! Schema::hasTable('migrations')) {
                return;
            }

            $batch = (DB::table('migrations')->max('batch') ?? 0) + 1;

            foreach (['create_visits_table', 'create_visitor_ignores_table'] as $name) {
                foreach (glob(database_path("migrations/*_{$name}.php")) as $file) {
                    $key = pathinfo($file, PATHINFO_FILENAME);

                    if (! DB::table('migrations')->where('migration', $key)->exists()) {
                        DB::table('migrations')->insert([
                            'migration' => $key,
                            'batch' => $batch,
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("laravel-visitor: Could not record visitor migrations as run: {$e->getMessage()}");
        }
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
