<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    | The database connection used to store visit records. Defaults to a
    | separate 'visitor' connection (SQLite) so tracking data is isolated
    | from your main application database.
    |
    | Set VISITOR_DB_CONNECTION to use an existing named connection from
    | your config/database.php instead of the auto-registered one below.
    */
    'connection' => env('VISITOR_DB_CONNECTION', 'visitor'),

    /*
    |--------------------------------------------------------------------------
    | Auto-registered Database Connection Config
    |--------------------------------------------------------------------------
    | These settings are used to auto-register the visitor database connection
    | if one isn't already defined by your application. The default driver
    | is SQLite, writing to storage/app/visitor.sqlite.
    |
    | To use a remote libSQL/Turso database, install the driver:
    |     composer require tursodatabase/turso-driver-laravel
    |
    | Then set VISITOR_DB_DRIVER=libsql and configure the remote endpoint:
    |
    |   Remote only (Turso cloud):
    |     VISITOR_DB_DRIVER=libsql
    |     VISITOR_DB_URL=libsql+wss://your-db.turso.io
    |     VISITOR_DB_AUTH_TOKEN=your-token
    |
    |   Embedded replica (local file + remote sync):
    |     VISITOR_DB_DRIVER=libsql
    |     VISITOR_DB_URL=libsql+wss://your-db.turso.io
    |     VISITOR_DB_AUTH_TOKEN=your-token
    |     VISITOR_DB_DATABASE=/absolute/path/to/local/replica.sqlite
    */
    'db' => [
        'driver' => env('VISITOR_DB_DRIVER', 'sqlite'),
        'url' => env('VISITOR_DB_URL', null),
        'auth_token' => env('VISITOR_DB_AUTH_TOKEN', null),
        'database' => env('VISITOR_DB_DATABASE', null),  // SQLite path or libSQL embedded replica path
        'sync_interval' => env('VISITOR_DB_SYNC_INTERVAL', 5),
        'read_your_writes' => env('VISITOR_DB_READ_YOUR_WRITES', true),
        'encryption_key' => env('VISITOR_DB_ENCRYPTION_KEY', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    | All tracking is dispatched to a queue so it never impacts page load
    | times. Set connection/name to null to use the application defaults.
    */
    'queue' => [
        'connection' => env('VISITOR_QUEUE_CONNECTION', null),
        'name' => env('VISITOR_QUEUE_NAME', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto Track
    |--------------------------------------------------------------------------
    | When true, the visitor.track middleware is automatically appended to
    | the web middleware group so all web routes are tracked without any
    | manual middleware configuration. Set to false to opt out and apply
    | visitor.track selectively to specific route groups.
    */
    'auto_track' => env('VISITOR_AUTO_TRACK', true),

    /*
    |--------------------------------------------------------------------------
    | Excluded Paths
    |--------------------------------------------------------------------------
    | Requests whose path matches any pattern here will not be tracked.
    | Supports wildcards (* and ?).
    */
    'exclude_paths' => [
        'admin*',
        '_debugbar*',
        'horizon*',
        'telescope*',
        'livewire*',
        '_ignition*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Bot Tracking
    |--------------------------------------------------------------------------
    | When true, requests detected as bots/crawlers are tracked and stored
    | with their bot_name and user_agent. Set to false to silently drop bot
    | visits without recording them. The check runs in the middleware (before
    | the queue) so untracked bot requests never consume a queue slot.
    */
    'track_bots' => env('VISITOR_TRACK_BOTS', true),

    /*
    |--------------------------------------------------------------------------
    | Excluded IPs
    |--------------------------------------------------------------------------
    | Requests from these IP addresses will not be tracked. Useful for
    | excluding your own machine, office, or staging server.
    */
    'exclude_ips' => [
        // '127.0.0.1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracked HTTP Methods
    |--------------------------------------------------------------------------
    | Only requests using these HTTP methods will be tracked.
    */
    'track_methods' => ['GET'],

    /*
    |--------------------------------------------------------------------------
    | Anonymous Mode
    |--------------------------------------------------------------------------
    | When true, user IDs are never stored — all visits are anonymous.
    | You can also force anonymous tracking per-call: Visitor::anonymous()->track($request)
    */
    'anonymous' => true,

    /*
    |--------------------------------------------------------------------------
    | IP Address Storage
    |--------------------------------------------------------------------------
    | When false, IP addresses (and the country/city derived from them) are
    | never stored or passed to the queue. Combined with 'anonymous' => true,
    | no personal data is recorded and GDPR consent requirements largely
    | fall away.
    */
    'store_ip' => env('VISITOR_STORE_IP', false),

    /*
    |--------------------------------------------------------------------------
    | Visit Deduplication
    |--------------------------------------------------------------------------
    | Prevents the same session from recording multiple visits to the same
    | path within the configured window. Has no effect on stateless requests
    | that have no session ID.
    */
    'deduplication' => [
        'enabled' => env('VISITOR_DEDUP_ENABLED', true),
        'window' => env('VISITOR_DEDUP_WINDOW', 30), // minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | GeoIP Resolution
    |--------------------------------------------------------------------------
    | Resolves country and city from IP addresses using a local MaxMind
    | GeoLite2-City.mmdb file. No external API calls at runtime.
    |
    | Download the free database at: https://dev.maxmind.com/geoip/geolite2-free-geolocation-data
    | Place it at the path below (or override VISITOR_GEOIP_DATABASE).
    */
    'geoip' => [
        'enabled' => env('VISITOR_GEOIP_ENABLED', true),
        'database' => env('VISITOR_GEOIP_DATABASE', storage_path('app/geoip/GeoLite2-City.mmdb')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Probe Path Detection
    |--------------------------------------------------------------------------
    | When block_probes is true, requests matching any probe_paths pattern are
    | treated as scraper/scanner activity: the requesting IP is automatically
    | blocked and receives a 403. Supports wildcards (* and ?).
    |
    | probe_block_duration sets how long (in minutes) the automatic block lasts.
    | Omit or set to null for a permanent block.
    */
    'block_probes' => env('VISITOR_BLOCK_PROBES', true),

    'log_blocks' => env('VISITOR_LOG_BLOCKS', false),

    'probe_paths' => [
        // 'wp-admin*',
        // 'wp-login*',
        // '.env*',
        // 'phpinfo*',
        // 'xmlrpc.php',
    ],

    'probe_block_duration' => env('VISITOR_PROBE_BLOCK_DURATION', null), // minutes, null = permanent

    'probe_404' => [
        'threshold' => env('VISITOR_PROBE_404_THRESHOLD', 10), // 404s within the window before blocking
        'window' => env('VISITOR_PROBE_404_WINDOW', 5),        // minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Pruning
    |--------------------------------------------------------------------------
    | Automatically prune old visit records. Run `php artisan visitor:prune`
    | on a schedule (e.g. daily) to keep your database tidy.
    */
    'pruning' => [
        'enabled' => true,
        'days' => env('VISITOR_PRUNE_DAYS', 365),
    ],

];
