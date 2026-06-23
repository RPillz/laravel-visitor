<?php

namespace RPillz\LaravelVisitor\Commands;

use Illuminate\Console\Command;
use RPillz\LaravelVisitor\Models\Visit;

class PruneVisitsCommand extends Command
{
    public $signature = 'visitor:prune {--days= : Number of days of visits to retain (overrides config)}';

    public $description = 'Prune visit records older than the configured retention period';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('visitor.pruning.days', 365));

        $count = Visit::where('created_at', '<', now()->subDays($days))->delete();

        $this->info("Pruned {$count} visit records older than {$days} days.");

        return self::SUCCESS;
    }
}
