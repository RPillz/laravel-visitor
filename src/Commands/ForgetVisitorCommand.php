<?php

namespace RPillz\LaravelVisitor\Commands;

use Illuminate\Console\Command;
use RPillz\LaravelVisitor\Models\Visit;

class ForgetVisitorCommand extends Command
{
    public $signature = 'visitor:forget
                         {userId? : The user ID to erase from visit records}
                         {--session= : The session ID to erase from visit records}
                         {--ip= : The IP address to erase from visit records}
                         {--force : Skip confirmation prompt}';

    public $description = 'Erase visit records by user ID or session ID (GDPR right to erasure)';

    public function handle(): int
    {
        $userId = $this->argument('userId');
        $sessionId = $this->option('session');
        $ip = $this->option('ip');

        if (! $userId && ! $sessionId && ! $ip) {
            $this->error('Provide a userId argument or --session= or --ip= option.');

            return self::FAILURE;
        }

        if ($userId) {
            return $this->forgetByUserId($userId);
        }

        if ($sessionId) {
            return $this->forgetBySessionId($sessionId);
        }

        return $this->forgetByIp($ip);
    }

    protected function forgetByUserId(string $userId): int
    {
        $count = Visit::where('user_id', $userId)->count();

        if ($count === 0) {
            $this->info("No visit records found for user ID {$userId}.");

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Delete {$count} visit record(s) for user ID {$userId}?")) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        Visit::where('user_id', $userId)->delete();
        $this->info("Erased {$count} visit record(s) for user ID {$userId}.");

        return self::SUCCESS;
    }

    protected function forgetBySessionId(string $sessionId): int
    {
        $count = Visit::where('session_id', $sessionId)->count();

        if ($count === 0) {
            $this->info("No visit records found for session ID {$sessionId}.");

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Delete {$count} visit record(s) for session ID {$sessionId}?")) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        Visit::where('session_id', $sessionId)->delete();
        $this->info("Erased {$count} visit record(s) for session ID {$sessionId}.");

        return self::SUCCESS;
    }

    protected function forgetByIp(string $ip): int
    {
        $count = Visit::where('ip_address', $ip)->count();

        if ($count === 0) {
            $this->info("No visit records found for IP address {$ip}.");

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Delete {$count} visit record(s) for IP address {$ip}?")) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        Visit::where('ip_address', $ip)->delete();
        $this->info("Erased {$count} visit record(s) for IP address {$ip}.");

        return self::SUCCESS;
    }
}
