<?php

namespace RPillz\LaravelVisitor\Commands;

use Illuminate\Console\Command;

class InstallVisitorCommand extends Command
{
    public $signature = 'visitor:install';

    public $description = 'Install and set up the laravel-visitor package';

    public function handle(): int
    {
        $this->info('Installing laravel-visitor...');

        $this->call('vendor:publish', [
            '--tag' => 'visitor-config',
            '--ansi' => true,
        ]);

        $this->call('vendor:publish', [
            '--tag' => 'visitor-migrations',
            '--ansi' => true,
        ]);

        $this->ensureSqliteFileExists();

        $this->call('migrate', ['--ansi' => true]);

        $this->info('');
        $this->info('laravel-visitor installed successfully.');

        if (! config('visitor.store_ip', false)) {
            $this->info('');
            $this->line('  <fg=green>✓</> Running in privacy-safe mode (no IPs or user IDs stored).');
        } else {
            $this->info('');
            $this->line('  <fg=yellow>!</> IP storage is enabled. If using GeoIP, download GeoLite2-City.mmdb from:');
            $this->line('      https://dev.maxmind.com/geoip/geolite2-free-geolocation-data');
            $this->line('      Place it at: '.config('visitor.geoip.database'));
        }

        $this->info('');
        $this->line('  Add the middleware to track visits automatically:');
        $this->line("      Route::middleware('visitor.track')->group(...)");
        $this->info('');
        $this->line('  Schedule the prune command to clean up old records:');
        $this->line("      Schedule::command('visitor:prune')->daily();");

        return self::SUCCESS;
    }

    protected function ensureSqliteFileExists(): void
    {
        $connectionName = config('visitor.connection', 'visitor');
        $connection = config("database.connections.{$connectionName}");

        if (($connection['driver'] ?? null) !== 'sqlite') {
            return;
        }

        $path = $connection['database'] ?? null;

        if (! $path || $path === ':memory:' || file_exists($path)) {
            return;
        }

        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        touch($path);
        $this->line("  Created SQLite database at: {$path}");
    }
}
