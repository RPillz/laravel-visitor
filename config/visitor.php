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
    | Download the free database at: https://dev.maxmind.com/geoip/geolite2-free-geolection-data
    | Place it at the path below (or override VISITOR_GEOIP_DATABASE).
    */
    'geoip' => [
        'enabled' => env('VISITOR_GEOIP_ENABLED', false),
        'database' => env('VISITOR_GEOIP_DATABASE', storage_path('app/geoip/GeoLite2-City.mmdb')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Block Verified Bots
    |--------------------------------------------------------------------------
    | Bot names listed here are blocked even when the crawler can be verified
    | via rDNS or a published IP list. The name is resolved from the User-Agent
    | string by jenssegers/agent. Comment out any bots you want to allow.
    |
    | Note: search engines (Googlebot, Bingbot, etc.) are not listed here —
    | leave them verified and unblocked so they continue to index your site.
    */
    'block_verified_bots' => [
        // AI training / content scrapers
        'ClaudeBot',        // Anthropic
        'GPTBot',           // OpenAI
        'PerplexityBot',    // Perplexity AI
        'Amazonbot',        // Amazon
        'CCBot',            // Common Crawl (primary AI training corpus)
        'Bytespider',       // ByteDance / TikTok

        // Meta crawlers
        // 'Facebookexternalhit',  // Meta social preview
        'Meta-WebIndexer',      // Meta web indexer
        'Meta-ExternalAds',     // Meta ads crawler
        'Meta-ExternalAgent',   // Meta external agent
        // 'Meta-ExternalFetcher', // Meta user-directed fetcher (may be useful for recommendations)

        // Commercial SEO / link-analysis scrapers
        'Semrush',   // SEMrush
        'Ahrefs',    // Ahrefs
        'DotBot',    // Moz
        'MJ12bot',   // Majestic
        'Diffbot',   // Diffbot
        'PetalBot',  // Huawei / PetalSearch

        // Generic scraping tools
        'Scrapy',
    ],

    /*
    |--------------------------------------------------------------------------
    | Block Unverified Bots
    |--------------------------------------------------------------------------
    | When true, any request from a crawler that self-identifies by name in
    | its User-Agent but cannot be verified (via rDNS domain match or a
    | published IP list) is blocked. Bots that pass verification — such as
    | Googlebot — are allowed through regardless of this setting.
    |
    | Use this as a catch-all for unknown crawlers that are not covered by
    | block_verified_bots but also cannot prove their identity.
    */
    'block_unverified_bots' => env('VISITOR_BLOCK_UNVERIFIED_BOTS', true),

    /*
    |--------------------------------------------------------------------------
    | Request Rate Limiting (by header fingerprint)
    |--------------------------------------------------------------------------
    | Limits the total number of requests from a given header fingerprint
    | within the configured window. Unlike the probe_404 limiter (which only
    | counts 404s), this counts every request and therefore catches high-volume
    | scrapers that hit only valid pages.
    |
    | When auto_block is true, a fingerprint that exceeds the threshold is
    | written to visitor_ignores so subsequent requests are caught by the
    | isBlocked() check rather than re-counted by the rate limiter.
    */
    'rate_limit' => [
        'enabled' => env('VISITOR_RATE_LIMIT', true),
        'threshold' => env('VISITOR_RATE_LIMIT_THRESHOLD', 60), // requests per window
        'window' => env('VISITOR_RATE_LIMIT_WINDOW', 1),        // minutes
        'auto_block' => env('VISITOR_RATE_LIMIT_AUTO_BLOCK', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | robots.txt
    |--------------------------------------------------------------------------
    | When enabled, the package serves GET /robots.txt with Disallow: / entries
    | for each listed User-agent. Leave disabled (the default) if your
    | application already manages its own robots.txt.
    |
    | Add 'robots.txt' to exclude_paths above if you do not want these hits
    | recorded in your visit analytics.
    */
    'robots_txt' => [
        'enabled' => env('VISITOR_ROBOTS_TXT', false),
        'disallow' => [
            'ClaudeBot',
            'Amazonbot',
            'meta-externalagent',
            'meta-webindexer',
            'meta-externalads',
            'GPTBot',
            'Google-Extended',
            'PerplexityBot',
            'CCBot',
        ],
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

    'log_blocks' => env('VISITOR_LOG_BLOCKS', true),

    'probe_paths' => [
         'wp-admin*',
         'wp-login*',
         '.env*',
         'phpinfo*',
         'xmlrpc.php',
    ],

    'probe_block_duration' => env('VISITOR_PROBE_BLOCK_DURATION', 60*24*3), // minutes, null = permanent

    'probe_404' => [
        'threshold' => env('VISITOR_PROBE_404_THRESHOLD', 5), // 404s within the window before blocking
        'window' => env('VISITOR_PROBE_404_WINDOW', 3),        // minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Verified Crawlers
    |--------------------------------------------------------------------------
    | When enabled, known crawlers are verified via two methods:
    |
    |   rDNS: the bot's IP resolves to a hostname that forward-resolves back
    |   to the same IP, with the suffix matching a known search engine domain
    |   (Googlebot, Bingbot, DuckDuckBot, etc.). These domains are hardcoded
    |   in VerifiedCrawlerResolver and do not need configuration.
    |
    |   IP lists: the bot's IP is checked against CIDR prefix lists published
    |   by crawler operators. The list is fetched once and cached for
    |   cache_ttl minutes.
    |
    | Verified bots bypass automatic probe-path and 404-rate blocking, and
    | are not subject to block_unverified_bots or fingerprint rate limiting.
    |
    | Results are cached per IP for cache_ttl minutes.
    */
    'verified_crawlers' => [
        'enabled' => env('VISITOR_VERIFIED_CRAWLERS', true),
        'cache_ttl' => env('VISITOR_CRAWLER_CACHE_TTL', 1440), // minutes
        'ip_lists' => [
            // hexydec/ip-ranges: daily-updated crawler IP ranges for ClaudeBot, BingBot,
            // Meta, GPTBot, Perplexity, and others in a single file.
            // Also accepts Anthropic's own format: https://claude.com/crawling/bots.json
            'https://raw.githubusercontent.com/hexydec/ip-ranges/main/output/crawlers.json',
        ],
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
        'days' => env('VISITOR_PRUNE_DAYS', 90),
    ],

];
