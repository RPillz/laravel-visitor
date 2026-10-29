# Changelog

All notable changes to `laravel-visitor` will be documented in this file.

## v1.2 - 2026-10-28

- Added `allow_paths` config to exempt trusted paths from bot blocking.
- Added explicit `allow_agents` by name.
- Improved bot verification using a daily IP range update, allowing stricter blocking of unverified bots by default.
- Modified default ignore paths and added `verify` to untracked paths.
- Fail gracefully when the database is missing (tries to create an sqlite file first).
- Aesthetic improvements to the Filament visitor resources.
- Fixed `expires_at` cache storage causing hits not to be logged.
- Fixed missing-table migrations not being logged correctly, which caused duplication errors.
- Fixed serialized job incompatible data.
- Fixed visit data exceeding column lengths.

## v1.1 - 2026-06-25

- Updated migration to allow for better identification of bots, blocked, and verified visits.
- Automatic recognition and blocking of probing and scraping behavior.
- Improved Filament reporting, along with button-press blocking of bot agents.

## v1.0 - 2026-06-23

Initial release of a minimalist visitor stats.
