# Laravel Package Guidelines

These guidelines supplement the framework-agnostic Package Boost
Guidelines (`foundation.md`) for Composer packages that target
Laravel. A consumer receives both files, composed — read this one
together with `foundation.md`, not instead of it.

Apply this file only when `composer.json` declares a Laravel
dependency — a `require.illuminate/*` entry or
`require.laravel/framework`. A framework-agnostic package ignores
everything below.

## Laravel Context

A Laravel package has no host application of its own. A Laravel
kernel is booted only at test time, by Orchestra Testbench. The base
test case is `Orchestra\Testbench\TestCase`.

- `composer.json`'s `require.illuminate/*` (or
  `require.laravel/framework`) defines the supported Laravel range.
  Check it before using a version-specific framework API.
- The service provider is the package's entry point into a host
  app. One per package, named `{PackageStudly}ServiceProvider`,
  registered under `extra.laravel.providers` for package discovery.
- Test fixtures — migrations, routes, views, factories — live under
  `workbench/`, not `tests/`. Testbench's conventions place them
  there; follow them.

## Use `vendor/bin/testbench`, not `php artisan`

Running artisan directly against the package fails — there is no
host application. Use Testbench's binary, which boots a kernel
first:

| Instead of | Use |
|---|---|
| `php artisan test` | `vendor/bin/pest` or `vendor/bin/phpunit` |
| `php artisan tinker` | `vendor/bin/testbench tinker` |
| `php artisan make:*` | Create files manually under `src/` |
| `php artisan vendor:publish` | `vendor/bin/testbench vendor:publish` |

Register the package's service provider in `testbench.yaml` under
`providers:` so Testbench boots it. Published files land in
`workbench/` by default, not the `config/` or `resources/` of a
host app.

### Commands that require `laravel/boost`

These apply only when the package has `laravel/boost` as a dev
dependency. Skip them if Boost is not installed — `boost sync`
prints a warning and moves on.

| Instead of | Use |
|---|---|
| `php artisan boost:install` | `vendor/bin/testbench boost:install` |
| `php artisan boost:mcp` | `vendor/bin/testbench boost:mcp` |

## Cross-Version Compatibility

Supporting multiple Laravel and PHP majors in one release is routine
for a Laravel package. Constraints use `||` between major ranges
(`^12.0||^13.0`), and CI runs a matrix that includes `prefer-lowest`
so the declared floor is actually exercised.

- Activate the `cross-version-laravel-support` skill **before**
  writing version-spanning code.
- Activate the `ci-matrix-troubleshooting` skill **after** a matrix
  cell has failed.
- See the `package-development` skill for the Testbench and
  `workbench/` layout.

---

# Package Boost Guidelines

These guidelines replace Laravel Boost's default foundation for
repositories that ship as Composer packages — Laravel-targeted or
framework-agnostic. The framing, tooling, and trade-offs differ from
application development; follow this version when working inside a
package codebase.

## Foundational Context

This codebase is a **Composer package**, not an application. The rules
below hold regardless of which framework (if any) the package targets.

- There is no `app/`, `bootstrap/`, `routes/`, `.env`, or database by
  default. Tooling that assumes an application context (e.g. running
  `php artisan` against the package itself) does not apply.
- The primary artefact is the package's public API — entry-point
  classes, service providers, exposed contracts. Everything else is
  scaffolding.
- Downstream consumers depend on this package via Composer. Every
  public change is a user-facing API change governed by semver.
- `composer.json` is the source of truth for supported PHP versions
  and any framework constraints. Check `require.php` (and any
  `require.<framework>/*` entries) before using version-specific
  features.

## Source Layout

- `src/` — package source, PSR-4 autoloaded per `composer.json`
- `tests/` — Pest or PHPUnit suite
- `config/` — publishable defaults shipped with the package, when
  applicable
- `resources/` — views, translations, Boost skills / guidelines, when
  applicable
- `database/migrations`, `database/factories` — only if the package
  ships them
- `workbench/` — developer-only Testbench scaffolding when Testbench
  is in use; never shipped

Check sibling files before inventing structure. Do not introduce new
top-level directories without a clear reason.

## Tests Are the Specification

The package has no running application to click through. Tests are how
behaviour is pinned down.

- Write tests alongside any behavioural change.
- Do not create "verification scripts" when a test can prove the same
  thing.
- Run the project's configured test runner (`vendor/bin/pest` or
  `vendor/bin/phpunit`) before claiming a change is done.

## Public API Discipline

- Every `public`, `protected`, or exported symbol is part of the
  package's surface. Breaking changes require a major version bump.
- Prefer `final` classes and `private`/`@internal` markers for
  anything not intended for extension.
- Keep config keys, published asset paths, and service container
  bindings stable across patch and minor versions.

## Conventions

- Match existing code style, naming, and structural patterns — check
  sibling files before writing new ones.
- Use descriptive names (`resolvePublishDestination`, not `resolve()`).
- Reuse existing helpers before adding new ones.
- Do not add dependencies without approval; every new `require` is a
  constraint downstream consumers inherit.

## Extending boost-core

If your package authors a custom `FileEmitter` (to write a file like
`.mcp.json` into the host during `boost sync`), declare the
`boost-extension` tag in your `boost.php` `withTags([...])`. That pulls
the `writing-file-emitter` skill — gated off by default so consumers
who do not extend the engine don't carry it, which is why an
emitter-authoring package has to opt in explicitly. The same tag pulls
`skill-authoring` for writing boost-family skills.

## Documentation Files

Only create or edit documentation (README, CHANGELOG, docs/) when
explicitly requested or when a behaviour change requires it.

## Replies

Be concise. Focus on what changed and why. Skip restating what the
diff already shows.

---

# Release Automation

Conventions the package-boost family shares for release flow. The
procedural detail lives in the `pre-release` and `release-notes`
skills — loaded on-demand, not pinned here.

## CHANGELOG is CI-managed

`.github/workflows/update-changelog.yml` prepends the release body to
`CHANGELOG.md` on `release: released` and commits to the release's
target branch (typically `main`). Don't hand-edit `CHANGELOG.md` as
part of a release. Post-release typo fixes are committed directly.

## Release notes live in `internal/release-notes-<version>.md`

`internal/` is gitignored — drafts stay local. The notes file becomes
the release body. The first line pins the green commit so the pre-tag
gate can fail closed on drift:

```
<!-- verified-sha: <full sha> -->
```

## Tag and title

- Tag: bare version (`0.7.0`) — Composer and Packagist read the tag.
- Release title: `v`-prefixed (`v0.7.0`) — cosmetic.
- Notes file: bare (`internal/release-notes-0.7.0.md`).

## Agent handoff

Agents stop at the ready-to-tag handoff. The user runs the pre-tag
gate and publishes the release (GitHub UI, `gh`, or otherwise). See
the `pre-release` skill for the full procedure and the no-release-create
rule.
