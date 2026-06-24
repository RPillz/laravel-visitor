<?php

namespace RPillz\LaravelVisitor\Commands;

use Illuminate\Console\Command;
use RPillz\LaravelVisitor\Models\Visit;
use RPillz\LaravelVisitor\Models\VisitorIgnore;

class PruneVisitsCommand extends Command
{
    public $signature = 'visitor:prune {--days= : Number of days of visits to retain (overrides config)}';

    public $description = 'Prune visit records older than the configured retention period';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('visitor.pruning.days', 365));

        $count = Visit::withoutGlobalScope('exclude_blocked')
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Pruned {$count} visit records older than {$days} days.");

        $pruned = VisitorIgnore::where('is_automatic', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->delete();

        if ($pruned > 0) {
            $this->info("Pruned {$pruned} expired automatic block(s).");
        }

        return self::SUCCESS;
    }
}
