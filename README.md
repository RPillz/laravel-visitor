# Laravel Visitor

[![Latest Version on Packagist](https://img.shields.io/packagist/v/rpillz/laravel-visitor.svg?style=flat-square)](https://packagist.org/packages/rpillz/laravel-visitor)
[![GitHub Tests Action Status](https://github.com/rpillz/laravel-visitor/actions/workflows/run-tests.yml/badge.svg)](https://github.com/rpillz/laravel-visitor/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://github.com/rpillz/laravel-visitor/actions/workflows/fix-php-code-style-issues.yml/badge.svg)](https://github.com/rpillz/laravel-visitor/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/rpillz/laravel-visitor.svg?style=flat-square)](https://packagist.org/packages/rpillz/laravel-visitor)

Minimalist page-visit analytics for Laravel. Records visits to an isolated SQLite database, resolves country and device info in the background, and surfaces reports through a Filament admin panel plugin — with zero impact on page load times.

## Features

- All tracking runs on a **queued job** — never blocks a request
- **Separate database connection** (SQLite by default) keeps analytics data out of your main DB
- Resolves **country & city** from a local MaxMind GeoLite2 database (no external API calls)
- Detects **device type, browser, and OS** from the User-Agent string
- Optionally stores the **authenticated user ID**
- Supports **anonymous mode** globally or per-call
- **Bot filtering** out of the box
- **Database-driven ignore list** — block IPs and user IDs from tracking via Filament UI; existing visits are deleted automatically when an entry is added
- **Filament v5 plugin** with an analytics dashboard: stats overview, visits chart, top pages, referrers, device breakdown, and ignore list management

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

Force a specific call to skip storing the user ID, regardless of the global config:

```php
Visitor::anonymous()->track($request);
```

Or disable user ID tracking globally in `config/visitor.php`:

```php
'anonymous' => true,
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

This adds an **Analytics** page to your panel at `/your-panel/analytics` with five widgets, plus an **Ignore List** resource for managing blocked IPs and user IDs:

| Widget | Description |
|---|---|
| Overview Stats | Total visits, unique visitors (by session), today's count |
| Visits Chart | Line chart of visits over time — filter by 7, 30, or 90 days |
| Top Pages | Most-visited paths ranked by visit count |
| Top Referrers | Referring domains ranked by visit count |
| Devices & Browsers | Breakdown of device type, browser, and OS |

## Ignore List

The ignore list lets you permanently block specific IP addresses or authenticated user IDs from being tracked — useful for excluding yourself, your team, or known bots that slip past the user-agent filter.

### Managing ignored visitors

When the Filament plugin is registered, an **Ignore List** resource appears in the Analytics navigation group. From there you can:

- Add an IP address or user ID to the ignore list
- Remove entries to resume tracking for that visitor

**When an entry is added**, all existing visit records matching that IP or user ID are deleted immediately. Future visits from that IP or user will be silently skipped in the middleware.

### How it works

The ignore list is stored in a `visitor_ignores` table on the same database connection as visit records. The middleware loads and caches the full list for 5 minutes (`visitor.ignore_list`), so there is no per-request database query after the first hit. The cache is flushed automatically whenever an entry is added or removed.

The middleware checks both the **request IP** and the **authenticated user ID** on every tracked request.

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

    // Automatically append visitor.track to the web middleware group (tracks all web routes)
    // Set to false to apply the middleware selectively to specific route groups instead
    'auto_track' => env('VISITOR_AUTO_TRACK', true),

    // Paths to exclude from automatic middleware tracking (supports * and ? wildcards)
    'exclude_paths' => [
        'admin*', '_debugbar*', 'horizon*', 'telescope*', 'livewire*', '_ignition*',
    ],

    // Skip requests detected as bots or crawlers (check runs in middleware, never hits the queue)
    'exclude_bots' => true,

    // Skip requests from these IP addresses (useful for your own machine or staging server)
    'exclude_ips' => [],

    // Only track requests using these HTTP methods
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
| `ip_address` | Visitor IP |
| `country` | ISO 3166-1 alpha-2 country code |
| `city` | City name |
| `device_type` | `desktop`, `mobile`, or `tablet` |
| `browser` | Browser name |
| `os` | Operating system |
| `user_id` | Auth user ID (nullable) |
| `session_id` | Session ID for unique visitor counting (indexed) |
| `created_at` | Timestamp (indexed) |

## GDPR Considerations

By default this package stores IP addresses and user IDs, which are personal data under GDPR. Depending on your jurisdiction and use case you may need user consent before tracking, or you may want to avoid storing personal data altogether.

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
