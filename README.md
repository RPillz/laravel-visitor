# Laravel Visitor

[![Latest Version on Packagist](https://img.shields.io/packagist/v/rpillz/laravel-visitor.svg?style=flat-square)](https://packagist.org/packages/rpillz/laravel-visitor)
[![GitHub Tests Action Status](https://github.com/rpillz/laravel-visitor/actions/workflows/run-tests.yml/badge.svg)](https://github.com/rpillz/laravel-visitor/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/rpillz/laravel-visitor.svg?style=flat-square)](https://packagist.org/packages/rpillz/laravel-visitor)

Minimalist page-visit analytics for Laravel. Records visits to an isolated SQLite database, resolves country and device info in the background, and surfaces reports through a Filament admin panel plugin — with zero impact on page load times.

Since we're checking all the visits anyway we can also track, and potentially block, bots and crawlers to your site.

## Features

- All tracking runs on a **queued job** — never blocks a request
- **Separate database connection** (SQLite by default) keeps analytics data out of your main DB
- Resolves **country & city** from a local MaxMind GeoLite2 database (no external API calls)
- Detects **device type, browser, and OS** from the User-Agent string
- **Anonymous by default** — no user IDs or IPs stored without opt-in
- User ID tracking opt-in, overridable per-call via `Visitor::anonymous()`
- **Bot tracking** — records bots with their name and header fingerprint; unidentified non-browser requests labelled automatically
- **Probe path blocking** — auto-blocks scanners hitting known attack paths (wp-admin, .env, etc.)
- **404 rate-limit blocking** — auto-blocks IPs that rack up too many 404s in a short window
- **Header fingerprinting** — tracks and blocks bots that rotate IPs
- **Verified crawler passthrough** — rDNS-verified search engines (Google, Bing, etc.) bypass auto-blocking
- **Database-driven ignore/block list** — block by IP, user ID, user agent wildcard, or header fingerprint via Filament UI; soft-ignore or hard-block (403 returned); temporary or permanent
- **Block logging** — optionally record blocked requests for auditing
- **Filament v5 plugin** with an analytics dashboard and bot management: stats overview, visits chart, top pages, referrers, device breakdown, bot stats, bot list, and ignore/block list management

## Requirements

- PHP 8.4+
- Laravel 11+
- Filament 5+ (only required for the admin panel plugin)

## Installation

```bash
composer require rpillz/laravel-visitor
```

Run the install command:

```bash
php artisan visitor:install
```

This publishes the config file, publishes the migration, creates the SQLite database file if needed, and runs the migration. You can also do these steps manually:

```bash
php artisan vendor:publish --tag="visitor-config"
php artisan vendor:publish --tag="visitor-migrations"
php artisan migrate
```

### Database connection

By default the package registers a `visitor` SQLite connection automatically, writing to `storage/app/visitor.sqlite`. No changes to your `database.php` are needed unless you want to point it at a different database:

```php
// config/database.php
'visitor' => [
    'driver' => 'sqlite',
    'database' => storage_path('app/analytics.sqlite'),
],
```

Or set the `VISITOR_DB_CONNECTION` environment variable to use an existing named connection from your app.

### Remote database (libSQL / Turso)

To store visit data in a remote [Turso](https://turso.tech) database or any libSQL-compatible endpoint, install the Turso driver:

```bash
composer require tursodatabase/turso-driver-laravel
```

Then configure your `.env`:

```env
# Remote-only (Turso cloud — no local file)
VISITOR_DB_DRIVER=libsql
VISITOR_DB_URL=libsql+wss://your-database.turso.io
VISITOR_DB_AUTH_TOKEN=your-auth-token

# Embedded replica (local SQLite file kept in sync with the remote)
VISITOR_DB_DRIVER=libsql
VISITOR_DB_URL=libsql+wss://your-database.turso.io
VISITOR_DB_AUTH_TOKEN=your-auth-token
VISITOR_DB_DATABASE=/absolute/path/to/local/replica.sqlite
```

The package auto-registers the connection — no changes to `config/database.php` are needed unless you have a naming conflict (see below).

> **Note:** The Turso driver requires the `libsql` PHP extension. See the [turso-driver-laravel documentation](https://github.com/tursodatabase/turso-driver-laravel) for installation instructions.

#### Naming conflicts

The Turso driver resolves connection config from `database.connections.libsql`. If your app already uses that key for another database, set `VISITOR_DB_CONNECTION` to a unique name and define the connection manually in your `config/database.php`:

```php
// config/database.php
'visitor_remote' => [
    'driver'    => 'libsql',
    'url'       => env('VISITOR_DB_URL'),
    'authToken' => env('VISITOR_DB_AUTH_TOKEN'),
    'prefix'    => '',
],
```

```env
VISITOR_DB_CONNECTION=visitor_remote
```

### GeoIP setup

Country and city resolution uses a local MaxMind GeoLite2 database. Download the free `GeoLite2-City.mmdb` file from [MaxMind](https://dev.maxmind.com/geoip/geolite2-free-geolocation-data) (free account required) and place it at:

```
storage/app/geoip/GeoLite2-City.mmdb
```

Override the path via `VISITOR_GEOIP_DATABASE` or in the config. Geo resolution is silently skipped if the file is absent.

## Usage

### Automatic tracking via middleware

By default the package appends `visitor.track` to Laravel's `web` middleware group automatically, so all web routes are tracked with no extra configuration.

To disable auto-tracking and apply the middleware selectively, set `auto_track` to `false` in `config/visitor.php` (or `VISITOR_AUTO_TRACK=false` in your `.env`):

```php
// config/visitor.php
'auto_track' => false,
```

Then apply the alias to specific route groups:

```php
// routes/web.php
Route::middleware('visitor.track')->group(function () {
    Route::get('/', HomeController::class);
    // ...
});
```

Tracking fires in the middleware's `terminate()` method — after the response is sent to the browser.

### Manual tracking

Use the `Visitor` facade anywhere in your code:

```php
use RPillz\LaravelVisitor\Facades\Visitor;

Visitor::track($request);
```

### Anonymous tracking

By default (`anonymous = true`), no user IDs are ever stored. If you've enabled user ID storage globally (`anonymous = false`), you can force a specific call to skip it:

```php
Visitor::anonymous()->track($request);
```

To enable user ID storage globally, set this in `config/visitor.php`:

```php
'anonymous' => false,
```

### Multi-tenant support

If your app serves multiple tenants, you can route each tenant's visit data and ignore list to a separate database connection. Register a resolver once at boot and the package calls it lazily on every request — no per-request wiring needed:

```php
// AppServiceProvider::boot()
use RPillz\LaravelVisitor\LaravelVisitor;

LaravelVisitor::resolveConnectionUsing(function () {
    return tenant() ? 'tenant_' . tenant()->id : config('visitor.connection', 'visitor');
});
```

Both the `visits` table and the `visitor_ignores` table (including the Filament ignore list UI) will use whichever connection the resolver returns for the current request.

If no resolver is registered the package behaves exactly as normal, falling back to `config('visitor.connection', 'visitor')`.

#### Per-tenant database setup

You are responsible for registering each tenant's connection in `config/database.php` (or dynamically via `config([...])`) and running the package migrations against it before tracking begins. The package ships two migration stubs — `create_visits_table` and `create_visitor_ignores_table` — that you can run against each tenant connection as part of your tenant-provisioning flow.

#### Per-call connection override

To route a single tracking call to a specific connection without a global resolver:

```php
Visitor::setConnection('tenant_42')->track($request);
```

This takes priority over any registered resolver for that call only.

### Pruning old records

Schedule the prune command to keep your database tidy:

```php
// routes/console.php
Schedule::command('visitor:prune')->daily();
```

The default retention period is 365 days. Override per-run:

```bash
php artisan visitor:prune --days=90
```

## Blocking & Bot Protection

The middleware runs active blocking logic **before** the request reaches your application, so malicious scanners and repeat offenders are rejected at the edge with no application overhead.

### Probe path blocking

Requests hitting known scanner paths (wp-admin, .env, phpinfo, etc.) are automatically blocked and the requesting IP is added to the block list. Blocked requests receive a 404 response so scanners get no information about your stack.

Configure the paths and block duration in `config/visitor.php`:

```php
'block_probes' => true, // set false to disable entirely

'probe_paths' => [
    'wp-admin*',
    'wp-login*',
    '.env*',
    'phpinfo*',
    'xmlrpc.php',
    // add your own patterns — supports * and ? wildcards
],

'probe_block_duration' => null, // minutes, null = permanent block
```

Set `VISITOR_BLOCK_PROBES=false` to disable probe blocking without touching the config file.

### 404 rate-limit blocking

IPs that generate too many 404 responses in a short window are automatically blocked. This catches scanners that don't match any specific probe path but are obviously enumerating your routes.

```php
'probe_404' => [
    'threshold' => env('VISITOR_PROBE_404_THRESHOLD', 10), // 404s before blocking
    'window'    => env('VISITOR_PROBE_404_WINDOW', 5),     // rolling window in minutes
],
```

Once the threshold is exceeded, further requests from that IP return 429 until the block expires.

### Header fingerprinting

The middleware computes a lightweight fingerprint from the request's HTTP headers. This fingerprint is stored alongside each visit and is used when auto-blocking — so a scanner that rotates IPs is still caught and blocked by its fingerprint.

Manual blocks via the Filament **Bot List** resource also prefer fingerprint-based blocks over user-agent wildcards when a fingerprint is available.

### Verified crawlers

Legitimate search engine bots verify themselves via reverse DNS. When `verified_crawlers` is enabled, the middleware checks whether the requesting IP reverse-resolves to a hostname that forward-resolves back to the same IP and whose suffix matches a known crawler domain. Verified bots bypass probe-path and 404-rate blocking entirely, and their visits are stored with `is_verified = true`.

```php
'verified_crawlers' => [
    'enabled'   => env('VISITOR_VERIFIED_CRAWLERS', true),
    'cache_ttl' => env('VISITOR_CRAWLER_CACHE_TTL', 1440), // minutes per IP
    'domains'   => [
        'googlebot.com',
        'google.com',
        'search.msn.com',
        'duckduckgo.com',
        'applebot.apple.com',
        'yandex.com', 'yandex.net', 'yandex.ru',
        'crawl.baidu.com',
    ],
],
```

DNS results are cached per IP for `cache_ttl` minutes so verification only runs once per unique crawler address.

### Discouraging scraper bots with robots.txt

Commercial SEO crawlers (Semrush, Ahrefs, Majestic, Moz, etc.) and aggressive scrapers provide no SEO or indexing benefit to your site — they exist to gather data for their own platforms. A `robots.txt` `Disallow` rule won't stop a bot that ignores it, but most of the named commercial crawlers do respect it.

```
# /public/robots.txt

User-agent: SemrushBot
User-agent: AhrefsBot
User-agent: MJ12bot
User-agent: DotBot
User-agent: BLEXBot
User-agent: DataForSeoBot
User-agent: MauiBot
User-agent: Bytespider
User-agent: TikTokSpider
User-agent: PetalBot
Disallow: /
```

Bots that ignore `robots.txt` are exactly the kind of traffic this package's probe-path blocking and 404 rate-limiting are designed to catch.

### Block logging

To record blocked requests for auditing, enable block logging:

```php
'log_blocks' => env('VISITOR_LOG_BLOCKS', false),
```

When enabled, blocked requests are dispatched to the queue and stored as visit records with `is_blocked = true`. The `Visit` model's default global scope excludes these from all normal queries, so they never appear in analytics — only in the raw table.

## Ignore List & Block List

The ignore/block list controls what happens to a visitor:

- **Ignore** (tracking skipped) — the visit is silently not recorded; the request proceeds normally
- **Block** (`is_blocked = true`) — the request is rejected with a 403 before reaching your application

Entries can target:

| Type | Matches |
|---|---|
| `ip` | Exact IP address |
| `user_id` | Authenticated user ID |
| `user_agent` | User-Agent string (supports `*` and `?` wildcards) |
| `header_fingerprint` | Computed header fingerprint hash |

Entries can be **permanent** or **temporary** (`expires_at`). Automatic blocks (from probe detection and 404 rate limiting) are flagged `is_automatic = true` and are distinct from manually added entries.

### Managing the list

When the Filament plugin is registered, an **Ignore List** resource appears in the Analytics navigation group. From there you can add, edit, or remove entries.

**When an ignore entry is added**, all existing visit records matching that value are deleted immediately. Future visits are silently skipped.

**When a block entry is added**, the IP, user agent, or fingerprint is rejected at the middleware with a 403 — no application code runs at all.

The list is loaded from the database and cached for 5 minutes (`visitor.ignore_list.<connection>`). The cache is flushed automatically whenever an entry is added or removed.

## Filament Plugin

Register the plugin in your Filament panel provider:

```php
use RPillz\LaravelVisitor\Filament\VisitorPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugins([
            VisitorPlugin::make(),
        ]);
}
```

This adds an **Analytics** page to your panel at `/your-panel/analytics` with the following widgets and resources:

### Analytics dashboard widgets

| Widget | Description |
|---|---|
| Overview Stats | Total visits, unique visitors (by session), today's count |
| Visits Chart | Line chart of visits over time — filter by 7, 30, or 90 days |
| Top Pages | Most-visited paths ranked by visit count |
| Top Referrers | Referring domains ranked by visit count |
| Devices & Browsers | Breakdown of device type, browser, and OS |
| Bot Stats | Total bot visits, today's count, and verified crawler count (hidden when `track_bots = false`) |
| Top Bots | Table of bots ranked by visit count with verified status (hidden when `track_bots = false`) |

### Resources

| Resource | Description |
|---|---|
| Bot List | All tracked bots grouped by name and fingerprint; one-click block action per entry |
| Ignore List | Full ignore/block list management — add entries by IP, user ID, user agent, or fingerprint |

The **Bot List** block action uses the header fingerprint when one is recorded, falling back to a wildcard user-agent rule (`*BotName*`) when not.

## Configuration

```php
// config/visitor.php

return [
    // Database connection for visit records
    'connection' => env('VISITOR_DB_CONNECTION', 'visitor'),

    // Queue connection and name for the tracking job
    'queue' => [
        'connection' => env('VISITOR_QUEUE_CONNECTION', null),
        'name'       => env('VISITOR_QUEUE_NAME', 'default'),
    ],

    // Automatically append visitor.track to the web middleware group
    'auto_track' => env('VISITOR_AUTO_TRACK', true),

    // Paths to exclude from tracking (supports * and ? wildcards)
    'exclude_paths' => [
        'admin*', '_debugbar*', 'horizon*', 'telescope*', 'livewire*', '_ignition*',
    ],

    // Track bot/crawler visits (stores bot_name, fingerprint, is_verified)
    'track_bots' => env('VISITOR_TRACK_BOTS', true),

    // Skip requests from these IPs
    'exclude_ips' => [],

    // Only track these HTTP methods
    'track_methods' => ['GET'],

    // Never store the authenticated user ID
    'anonymous' => true,

    // Never store IP addresses (also skips country/city resolution)
    'store_ip' => env('VISITOR_STORE_IP', false),

    // Prevent duplicate records for the same session+path within a rolling window
    'deduplication' => [
        'enabled' => env('VISITOR_DEDUP_ENABLED', true),
        'window'  => env('VISITOR_DEDUP_WINDOW', 30), // minutes
    ],

    // Local MaxMind GeoLite2 database for country/city resolution
    'geoip' => [
        'enabled'  => env('VISITOR_GEOIP_ENABLED', true),
        'database' => env('VISITOR_GEOIP_DATABASE', storage_path('app/geoip/GeoLite2-City.mmdb')),
    ],

    // Auto-block IPs and fingerprints that hit probe paths; return 404
    'block_probes' => env('VISITOR_BLOCK_PROBES', true),

    // Record blocked requests as visits with is_blocked=true (excluded from analytics)
    'log_blocks' => env('VISITOR_LOG_BLOCKS', false),

    // Paths treated as probe/scanner activity (supports wildcards)
    'probe_paths' => [
        'wp-admin*', 'wp-login*', '.env*', 'phpinfo*', 'xmlrpc.php',
    ],

    // How long auto-blocks last (minutes); null = permanent
    'probe_block_duration' => env('VISITOR_PROBE_BLOCK_DURATION', null),

    // Auto-block IPs that hit this many 404s within the window
    'probe_404' => [
        'threshold' => env('VISITOR_PROBE_404_THRESHOLD', 10),
        'window'    => env('VISITOR_PROBE_404_WINDOW', 5), // minutes
    ],

    // Verify legitimate search engine bots via reverse DNS
    'verified_crawlers' => [
        'enabled'   => env('VISITOR_VERIFIED_CRAWLERS', true),
        'cache_ttl' => env('VISITOR_CRAWLER_CACHE_TTL', 1440), // minutes
        'domains'   => [
            'googlebot.com', 'google.com', 'search.msn.com',
            'duckduckgo.com', 'applebot.apple.com',
            'yandex.com', 'yandex.net', 'yandex.ru', 'crawl.baidu.com',
        ],
    ],

    // Retention period for visit records
    'pruning' => [
        'enabled' => true,
        'days'    => env('VISITOR_PRUNE_DAYS', 365),
    ],
];
```

## What Gets Recorded

Each visit record stores:

| Column | Description |
|---|---|
| `url` | Full URL |
| `path` | URL path (indexed) |
| `query` | Query string |
| `referrer` | Full referrer URL |
| `referrer_domain` | Referrer domain only (indexed) |
| `ip_address` | Visitor IP (nullable — off by default) |
| `country` | ISO 3166-1 alpha-2 country code |
| `city` | City name |
| `device_type` | `desktop`, `mobile`, or `tablet` |
| `browser` | Browser name |
| `os` | Operating system |
| `user_agent` | Raw User-Agent string |
| `header_fingerprint` | Hash of request headers for bot fingerprinting |
| `bot_name` | Bot/crawler name (null for human visits) |
| `is_blocked` | `true` when the record is a logged blocked request |
| `is_verified` | `true` for rDNS-verified crawlers (Google, Bing, etc.) |
| `is_user` | `true` when an authenticated user was present |
| `user_id` | Auth user ID (nullable — off by default) |
| `session_id` | Session ID for unique visitor counting (indexed) |
| `created_at` | Timestamp (indexed) |

Blocked visit records (`is_blocked = true`) are excluded from all normal queries via a global scope on the `Visit` model. They are only visible in the raw table or when explicitly calling `withoutGlobalScope('exclude_blocked')`.

## GDPR Considerations

By default this package does not track personal data, but does have the option to store IP addresses, Geolocation, and user IDs, which are personal data under GDPR. Depending on your jurisdiction and use case you may need user consent before tracking, or you may want to avoid storing personal data altogether.

### Default behaviour (consent-free)

Out of the box, `anonymous = true` and `store_ip = false`, so no personal data is stored. Records contain only path, referrer domain, device type, browser, OS, and session ID — none of which are personal data on their own. No consent mechanism is required.

If you want to opt in to storing user IDs and IP addresses (for richer analytics), set these in your `.env`:

```env
VISITOR_STORE_IP=true
```

```php
// config/visitor.php
'anonymous' => false,
'store_ip' => true,
```

When storing personal data you are responsible for obtaining user consent and disclosing this in your privacy policy.

### Right to erasure

To delete all visit records linked to a specific user (GDPR Article 17):

```bash
# By user ID
php artisan visitor:forget {userId}

# By session ID (for anonymous visitors)
php artisan visitor:forget --session={sessionId}

# By IP address
php artisan visitor:forget --ip={ipAddress}
```

Safe to call from your app's user deletion flow:

```php
Artisan::call('visitor:forget', ['userId' => $user->id, '--force' => true]);
```

### Data retention

Set a retention period and schedule the prune command so old records are automatically removed:

```php
// config/visitor.php
'pruning' => ['enabled' => true, 'days' => 365],
```

```php
// routes/console.php
Schedule::command('visitor:prune')->daily();
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [RPillz](https://github.com/RPillz)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
