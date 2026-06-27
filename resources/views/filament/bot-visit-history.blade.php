<div>
    @forelse ($visits as $visit)
        <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.375rem 0.25rem; border-bottom: 1px solid rgba(128,128,128,0.1);">
            @if ($visit->is_blocked)
                <x-filament::icon icon="heroicon-m-shield-exclamation" class="h-4 w-4 text-danger-500" style="flex-shrink: 0;" />
            @else
                <x-filament::icon icon="heroicon-m-check-circle" class="h-4 w-4 text-success-500" style="flex-shrink: 0;" />
            @endif
            <span style="flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-family: ui-monospace, monospace; font-size: 0.8125rem;">{{ $visit->path }}</span>
            <span class="text-gray-400 dark:text-gray-500" style="flex-shrink: 0; font-size: 0.75rem;">{{ $visit->created_at->diffForHumans() }}</span>
        </div>
    @empty
        <p class="text-gray-400 dark:text-gray-500" style="padding: 1rem 0; text-align: center; font-size: 0.875rem;">No visits found.</p>
    @endforelse
</div>
