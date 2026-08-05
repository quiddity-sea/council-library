# Plutus — Developer Onboarding Guide

*Phase 5.1 — for new developers (human or AI) joining the project.*

## 1. Environment

- **Host**: WSL2 (Ubuntu), Apache 2.4 + PHP 8.3 + MariaDB 11.8
- **Paths**: code at `/var/www/plutus.invigor.com`; backups at `/foreverbox_data/backups/plutus/`; exports at `/foreverbox_data/exports/plutus/`
- **Domains**: `plutus.invigor.com` (live), `plutus-staging.invigor.com` (staging, anonymised) — both resolve via the Windows hosts file to 127.0.0.1
- **DB access**: `zeon7_user` / `F0reverb0x#2o26sql` (see `db.php`); databases `plutus_thoughts` (live) and `plutus_thoughts_staging`
- **Git**: repo at the site root, branch `main`, identity `quiddity-sea <lightweavers74@gmail.com>`

## 2. First Run

```bash
cd /var/www/plutus.invigor.com
php -l api.php                          # PHP lint sanity
vendor/bin/phpunit                      # PHP unit tests (12 tests)
vendor/bin/phpstan analyse              # static analysis (level 5)
npm test                                # JS Vitest (13 tests)
npx tsc --noEmit                        # type check
npm run build                           # Vite build -> dist/
npx playwright test --config=playwright.config.js   # E2E vs staging
```

## 3. Code Map

| Area | Location | Purpose |
|------|----------|---------|
| API | `api/` | modular controllers/middleware/routes |
| Legacy entry | `api.php` | thin wrapper for backward compat |
| Frontend | `assets/js/app.js` | SPA logic (jQuery, HUD-styled) |
| Frontend modules | `src/` | ES modules extracted for Vite |
| Types | `src/types/schema.js` | generated from DB schema |
| Scripts | `scripts/` | backup, backfill, staging, export, smoke |
| Tests | `tests/` (PHP), `src/__tests__/` (JS), `e2e/` (Playwright) | |
| PWA | `manifest.json`, `sw.js` | installable app + offline sync |

## 4. Adding an Endpoint

1. Add a handler method to a controller in `api/controllers/` (or create a new controller).
2. Register it in `api/routes.php` under the appropriate group: `['method' => 'GET', 'controller' => 'XController', 'handler' => 'method']`.
3. Add middleware in the route entry if needed (e.g. rate limiting).
4. Include the controller in `api/bootstrap.php`.
5. Call it from the frontend via `api.php?action=...` (legacy) or `/api/v1/...` (versioned).
6. Add a path entry to `docs/openapi.json` and a row to the API table in the docs.
7. Test: `php -l`, PHPStan, PHPUnit; manual curl for the new action.

## 5. Database Migrations

Migrations are plain SQL files. Apply to **both** live and staging:

```bash
mariadb -u zeon7_user -p'F0reverb0x#2o26sql' plutus_thoughts < migration_XXX.sql
mariadb -u zeon7_user -p'F0reverb0x#2o26sql' plutus_thoughts_staging < migration_XXX.sql
php scripts/generate_types.php   # regenerate src/types/schema.js
```

## 6. Deploying to Staging

```bash
sudo rsync -a --exclude='.git' --exclude='node_modules' --exclude='dist' \
  --exclude='vendor' --exclude='runtime' --exclude='.phpunit.cache' \
  /var/www/plutus.invigor.com/ /var/www/plutus-staging.invigor.com/
sudo chown -R www-data:www-data /var/www/plutus-staging.invigor.com
# CRITICAL: restore the staging DB pointer if the sync overwrote it
sudo sed -i "s/\$db = 'plutus_thoughts';/\$db = 'plutus_thoughts_staging';/" \
  /var/www/plutus-staging.invigor.com/db.php
sudo -u www-data bash scripts/smoke_staging.sh   # must be 7/7 green
```

## 7. Conventions

- **British English** spelling; no em/en dashes.
- **No test data in the live DB** — use `__`-prefixed temp users/records and delete them after.
- **Commit per phase** with descriptive messages; keep the working tree clean.
- **Plan document** lives at `/foreverbox_data/council-library/docs/Current Started Plans/plutus_update_plan.md` — tick items as they are completed and keep the sign-off table updated.
- **Backup first**: before any migration or risky change, ensure the 03:00 backup ran or take a manual dump.
- **Never commit**: `.env`, `runtime/`, `dist/`, `node_modules/`, `vendor/`, `.phpunit.cache/`, uploads.

## 8. Troubleshooting

| Symptom | Likely cause / fix |
|---------|-------------------|
| Staging shows real data | db.php pointer was overwritten by rsync; re-point to `plutus_thoughts_staging` |
| `sudo` prompts for password | run `sudo -v` once in a PTY so the user can type it, or add a sudoers drop-in |
| CSRF 403 on POST | missing `X-CSRF-Token` header; the app attaches it via `$.ajaxSetup` |
| 429 on login | rate limiter tripped; wait 15 min or clear `runtime/rate_limit/` |
| PHPStan new errors | keep level 5 clean; annotate globals with `/** @var ... */` |
| Vite build fails | rebuild from `src/` then re-copy `dist/` to the site |

*Welcome aboard. Neon green on charcoal. Scanlines always watching.*


## Phase 2 Additions (2026-08)

### New architecture elements

- `api/controllers/TaskController.php` — spend-task lifecycle (complete/pause/resume/stop), OAuth handlers
- `api/utils/GoogleCalendarService.php` — Google Calendar v3 wrapper (OAuth, token refresh, recurring events). Requires `PLUTUS_GOOGLE_CLIENT_ID` / `PLUTUS_GOOGLE_CLIENT_SECRET` env vars; degrades gracefully when absent
- `api/controllers/PeriodsController.php` — period selector lists (Phase 6)

### Schema notes

- `transactions` gained `projected_amount` + `status` (planned/spent); `amount` is nullable
- `tasks` gained spend-task columns: `projected_cost`, `actual_cost`, `spent_at`, `recurrence_type/days/time/count/completed`, `paused_at`, google calendar IDs; `type` enum includes `'spend'`
- `users` has `google_access_token` / `google_refresh_token` / `google_token_expires`

### Gotchas

- `app.js` is CRLF line endings — normalise before patching (see above)
- All site files www-data-owned; edit via /tmp patch script → `sudo cp` → `sudo -u www-data python3` → `sudo rm`
- `response()` util takes ONE argument (data only); set `http_response_code()` separately for error statuses
- Budget totals update only from `status='spent'` transactions — planned rows never touch budget position
- Google Calendar cannot be end-to-end tested until credentials exist; verify `google_auth` returns `GOOGLE_NOT_CONFIGURED` gracefully otherwise
- Staging rsync overwrites `db.php` — always re-point to `plutus_thoughts_staging` after sync


## Phase 4.0 Upgrades & Security (2026-08)

### Security Enhancements
- **Environment Secrets (`.env`)**: Database credentials and sensitive keys are now strictly loaded from `.env` (gitignored). `db.php` leverages `getenv()` with fallback capabilities to keep credentials out of version control.
- **Cron Hardening**: `cron.php` is now strictly locked to CLI execution (`php_sapi_name() === 'cli'`). It utilizes non-blocking atomic file locks (`flock(LOCK_EX | LOCK_NB)`) to prevent overlapping executions and wraps data mutations in PDO Database Transactions (`beginTransaction() / commit()`) to ensure atomicity.
- **Global XSS Sanitization**: A new global `window.escapeHtml()` utility is enforced across all dynamic JavaScript template literal interpolations (e.g. `${window.escapeHtml(t.name)}`) to prevent Cross-Site Scripting.

### Frontend Modularity (Without Bundlers)
To keep the frontend lean and free from Node/Webpack/Vite build steps, the monolithic `app.js` has been modularized natively:
- `assets/js/state.js` manages global state (`appState`), XSS utilities, debouncing, and Toast/Modal interactions.
- `assets/js/components/table.js` handles reusable, unified UI generation for data tables.
- Versioning query strings (e.g. `?v=4.1`) are tied to `sw.js` and `index.php` for robust service worker cache invalidation.

### Continuous Integration (CI/CD)
- Legacy standalone test scripts (e.g. `tests/rate_limiter_test.php`) were cleaned up.
- A **Playwright E2E** job was integrated into the `.github/workflows/ci.yml` pipeline, automatically installing dependencies and running browser tests against pull requests and main branch pushes.
