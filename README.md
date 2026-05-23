# Translation Management Service API

A high-performance REST API for storing, searching and exporting localized
translations, built with **Laravel 13**, **PHP 8.3** and **MySQL 8**.

Designed for scale: it serves filtered, page-paginated reads and a
locale-wide JSON export from a **100k+ row** dataset while staying inside a
**< 200 ms** budget for normal endpoints and **< 500 ms** for export.

---

## Table of Contents

1. [Features](#features)
2. [Tech Stack](#tech-stack)
3. [Architecture](#architecture)
4. [Database Schema & Indexing](#database-schema--indexing)
5. [Caching Strategy](#caching-strategy)
6. [Performance](#performance)
7. [Security](#security)
8. [Requirements](#requirements)
9. [Setup](#setup)
10. [API Reference](#api-reference)
11. [Swagger / OpenAPI](#swagger--openapi)
12. [Testing](#testing)
13. [Seeding 100k+ Records](#seeding-100k-records)
14. [Design Tradeoffs](#design-tradeoffs)

---

## Features

- **Translations** stored per locale, with unlimited new locales at runtime.
- **Contextual tags** (`mobile`, `desktop`, `web`, …) via a normalized pivot.
- **CRUD** plus combined **search** by `locale`, `key`, `content` and `tags`.
- **JSON export**: a flat `{"key":"value"}` map per locale for frontend i18n.
- **Token authentication** with Laravel Sanctum.
- **Page-number pagination**, **FULLTEXT search**, **versioned export cache**.
- **OpenAPI 3** documentation with a testable Swagger UI.
- **Uniform response envelope**: every success and error shares one
  `{status, code, message, data}` shape.
- **~99% test coverage** (Pest: unit, feature and performance tests).

---

## Tech Stack

| Concern        | Choice                                    |
| -------------- | ----------------------------------------- |
| Framework      | Laravel 13                                |
| Language       | PHP 8.3 (`declare(strict_types=1)`)       |
| Database       | MySQL 8 (InnoDB, FULLTEXT)                |
| Auth           | Laravel Sanctum (personal access tokens)  |
| API docs       | L5-Swagger (OpenAPI 3, PHP 8 attributes)  |
| Tests          | Pest                                      |
| Cache          | Laravel Cache (`database` driver default) |
| Code style     | PSR-12 (enforced by Laravel Pint)         |

No external CRUD generators or translation packages are used.

---

## Architecture

A layered, service-oriented design with strict separation of concerns:

```
Route → Middleware (auth:sanctum, throttle)
      → Controller (thin, HTTP only)
      → Form Request (validation + authorization)
      → Service (business logic, transactions, cache)
      → Query Filter pipeline / Eloquent
      → API Resource (output shaping)
      → ApiResponse ({status, code, message, data} envelope)
```

- **Thin controllers**: translate HTTP to/from services; zero business logic.
- **Service layer**: `TranslationService`, `TranslationExportService` and
  `AuthService` own all rules, transactions and cache orchestration;
  `JsonResponseService` builds the response envelope.
- **Query Filter pipeline**: each search dimension is a small, stateless
  `Filter` class; adding one is a new class + one map entry (open/closed).
- **Observer-driven cache invalidation**: `TranslationObserver` keeps the
  export cache fresh from every write path.
- **Uniform responses**: the `ApiResponse` facade wraps every controller
  response, and the exception handler routes every API error, through one
  `{status, code, message, data}` envelope. No stack traces leak when
  `APP_DEBUG=false`.

### Folder layout

```
app/
├── Console/Commands/SeedTranslationsCommand.php   # translations:seed
├── Facades/ApiResponse.php                        # response-envelope facade
├── Filters/                                       # Filter contract + classes
│   ├── Contracts/Filter.php
│   ├── Translation/{Locale,Key,Content,Tags}Filter.php
│   └── TranslationFilter.php                      # the filter pipeline
├── Http/
│   ├── Controllers/Api/V1/{Auth,Translation}Controller.php
│   ├── Requests/                                  # Form Requests
│   └── Resources/                                 # API Resources
├── Models/{User,Locale,Translation,Tag}.php
├── Observers/TranslationObserver.php              # export cache invalidation
├── OpenApi/ApiDoc.php                             # OpenAPI metadata + schemas
├── Providers/
│   ├── AppServiceProvider.php                     # rate limiters, strict mode
│   └── JsonResponseServiceProvider.php            # binds JsonResponseService
├── Services/
│   ├── Auth/AuthService.php
│   ├── Translation/{TranslationService,TranslationExportService}.php
│   └── JsonResponseService.php                    # response envelope builder
└── Support/CacheKeys.php                          # cache key scheme
```

> **Why no Repository layer?** Eloquent already is the data-access
> abstraction. With a single persistence backend, repositories would add
> ceremony without payoff. The Service + Query Filter split delivers the same
> testability; see the [tradeoffs](#design-tradeoffs).

---

## Database Schema & Indexing

Four domain tables (plus the framework's `users` / `personal_access_tokens`):

### `locales`
`id`, `code` *(unique)*, `name`, `is_active` *(indexed)*, timestamps.
A first-class table, **not** an enum, so new languages are added at runtime
with no migration, while `UNIQUE(code)` and foreign keys prevent typos.

### `tags`
`id`, `name` *(unique)*, timestamps.

### `translations`
`id`, `locale_id` *(FK)*, `key` *(varchar 191)*, `content` *(text)*, timestamps.

Indexes, tuned for 100k+ rows:

| Index | Purpose |
| ----- | ------- |
| `UNIQUE(locale_id, key)` | Enforces one value per key per locale **and** serves the export query `WHERE locale_id = ? ORDER BY key` as an index-ordered scan. The `locale_id` FK reuses its leading column, so no separate index is needed. |
| `INDEX(key)` | Cross-locale key search and prefix matches (`LIKE 'home%'`). |
| `FULLTEXT(content)` | `MATCH … AGAINST` content search instead of a `LIKE '%…%'` full table scan. |

### `tag_translation` (pivot)
`PRIMARY(translation_id, tag_id)` + `INDEX(tag_id, translation_id)` for the
reverse "translations carrying tag X" lookup. A normalized pivot (not a JSON
column) is what makes multi-tag filtering indexable.

All tables are **InnoDB** (forced via `config/database.php`) for foreign keys,
transactions and FULLTEXT.

---

## Caching Strategy

The export endpoint must *always* return fresh data, so caching uses
**versioned keys**, never a naive forever-cache:

1. Each locale has a version counter: `translations:export:version:locale:{id}`.
2. The export payload is stored under a key embedding that version:
   `translations:export:payload:locale:{id}:v{version}`.
3. On **any** create/update/delete, `TranslationObserver` increments the
   counter. The next export computes a *new* key, misses, and rebuilds;
   stale payloads are simply orphaned and TTL-expire.
4. A locale reassignment invalidates **both** the old and new locale.

The result: zero stale reads, no cache flush, and it works on **any** cache
driver (file / database / Redis), with no Redis-only tag support assumed.
An **ETag** (locale + version hash) lets clients revalidate with `304`.

---

## Performance

Measured locally against a **100,000-row** dataset (`php artisan serve`,
Xdebug disabled, MySQL 8; single-locale export ≈ 20k rows / 1.6 MB):

| Endpoint                          | Time    | Budget   |
| --------------------------------- | ------- | -------- |
| `GET /translations` (50, page=1)  | ~110 ms | < 200 ms |
| `GET /translations` + filters     | ~130 ms | < 200 ms |
| `GET /translations/export` (cold) | ~76 ms  | < 500 ms |
| `GET /translations/export` (warm) | ~80 ms  | < 500 ms |

Key optimizations:

- **Page-number pagination** with a strict `per_page` cap (1–200) bounds
  every page; deep pages still go through the `id`-ordered index scan.
- **FULLTEXT search**: index-backed `MATCH … AGAINST`, not `LIKE '%…%'`.
- **Selective columns**: only the columns needed are selected; export reads
  just `key, content` through the base query builder (no Eloquent hydration).
- **Eager loading**: `locale` and `tags` are loaded with constrained column
  lists, eliminating N+1 (a dedicated test asserts a constant query count).
- **Single-pass export build**: `pluck('content', 'key')` materialises the
  flat map in one query-builder pass — no per-row hydration overhead.
- **Versioned export cache**: warm hits skip the query entirely.
- **`Model::preventLazyLoading()`** in non-production turns any accidental
  N+1 into a hard failure during development and CI.

> Run with Xdebug enabled and figures roughly triple; Xdebug is a debugger,
> not a runtime. Disable it (`XDEBUG_MODE=off`) for any performance check.

---

## Security

- **Sanctum** bearer tokens; every translation route sits behind
  `auth:sanctum`.
- **Rate limiting**: `login` 5/min per IP (anti brute-force), `api` 60/min
  per user, `export` 20/min per user (heaviest endpoint).
- **Form Request validation** on every input; unknown fields are ignored,
  mass assignment is constrained to explicit fillable attributes.
- **Generic auth failures**: login never reveals whether an email exists.
- **Hidden fields**: `password` / `remember_token` are never serialized.
- **Safe exceptions**: uniform JSON errors, correct status codes, no stack
  traces when `APP_DEBUG=false`.

---

## Requirements

- PHP **8.3+** with extensions: `pdo_mysql`, `mbstring`, `intl`, `openssl`,
  `tokenizer`, `xml`, `ctype`, `json`, `bcmath`
- Composer 2
- MySQL **8.0+**

---

## Setup

```bash
# 1. Install dependencies
composer install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Configure the database in .env
#    DB_DATABASE=translation_management
#    DB_USERNAME=root
#    DB_PASSWORD=

# 4. Create the database, then migrate + seed
mysql -u root -e "CREATE DATABASE translation_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate --seed

# 5. Generate the API docs and start the server
php artisan l5-swagger:generate
php artisan serve
```

The API is now at `http://127.0.0.1:8000/api/v1`.

**Default credentials** (created by the seeder):

```
email:    admin@example.com
password: password
```

### Environment notes

| Variable                     | Purpose |
| ----------------------------- | ------- |
| `DB_ENGINE`                   | Defaults to `InnoDB`; forces InnoDB even on MySQL installs that default to MyISAM (e.g. WAMP). |
| `CACHE_STORE`                 | `database` by default; `redis` works without code changes. |
| `L5_SWAGGER_GENERATE_ALWAYS`  | `true` in dev regenerates the spec on every docs request. |

---

## API Reference

Base URL: `/api/v1`. All responses are JSON.

| Method      | Endpoint                  | Description                       | Auth |
| ----------- | ------------------------- | --------------------------------- | ---- |
| `POST`      | `/auth/login`             | Obtain a bearer token             | No   |
| `POST`      | `/auth/logout`            | Revoke the current token          | ✔    |
| `GET`       | `/auth/me`                | Current authenticated user        | ✔    |
| `GET`       | `/translations`           | List & search (page-paginated)    | ✔    |
| `POST`      | `/translations`           | Create a translation              | ✔    |
| `GET`       | `/translations/{id}`      | Show a translation                | ✔    |
| `PUT/PATCH` | `/translations/{id}`      | Update (partial) a translation    | ✔    |
| `DELETE`    | `/translations/{id}`      | Delete a translation              | ✔    |
| `GET`       | `/translations/export`    | Flat JSON map for a locale        | ✔    |

Search filters on `GET /translations` (all optional, combined with AND):
`?locale=en` · `?key=homepage` · `?content=welcome` ·
`?tags[]=mobile&tags[]=web` · `?per_page=50` · `?page=2`

### Response format

Every JSON response uses one consistent envelope:

```json
{ "status": "success | failed", "code": 200, "message": "...", "data": null }
```

- **Success**: the payload is in `data` (a single object, or
  `{ data, meta }` for paginated lists — `meta` carries
  `current_page`, `next_page`, `prev_page`, `last_page`, `per_page`, `total`).
- **Errors** (`401`, `403`, `404`, `422`, `429`, `500`) use the same shape with
  `status: "failed"`; `422` validation errors place the field-keyed messages
  under `data`.
- Built by `JsonResponseService`, called through the `ApiResponse` facade, and
  applied to every controller response **and** every exception.
- **One exception:** `GET /translations/export` returns a *raw* flat
  `{"key":"value"}` map (no envelope); its contract is direct consumption by
  frontend i18n libraries.

### Authentication

```bash
# 1. Log in. The token is returned at data.token
curl -X POST http://127.0.0.1:8000/api/v1/auth/login \
  -H "Accept: application/json" \
  -d "email=admin@example.com&password=password"
# => {"status":"success","code":200,"message":"Authenticated successfully.",
#     "data":{"user":{...},"token":"1|...","token_type":"Bearer"}}

# 2. Send it as a bearer token on every protected request
curl http://127.0.0.1:8000/api/v1/auth/me \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <TOKEN>"
```

### Examples

```bash
TOKEN="<your-token>"
API="http://127.0.0.1:8000/api/v1"

# Create
curl -X POST "$API/translations" \
  -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"locale":"en","key":"homepage.title","content":"Welcome","tags":["web","mobile"]}'

# Search: English keys under "homepage" tagged "web"
curl "$API/translations?locale=en&key=homepage&tags[]=web" \
  -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"

# Update (partial)
curl -X PATCH "$API/translations/1" \
  -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"content":"Welcome back"}'

# Export a locale (flat JSON for Vue.js / vue-i18n)
curl "$API/translations/export?locale=en" \
  -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"
# => {"auth.login":"Log in","homepage.title":"Welcome", ...}
```

### Error responses

All errors use the standard envelope (`status: "failed"`):

| Status | Meaning             | Body |
| ------ | ------------------- | ---- |
| `401`  | Unauthenticated     | `{"status":"failed","code":401,"message":"Unauthenticated.","data":null}` |
| `404`  | Not found           | `{"status":"failed","code":404,"message":"Resource not found.","data":null}` |
| `422`  | Validation failed   | `{"status":"failed","code":422,"message":"…","data":{"field":["…"]}}` |
| `429`  | Rate limit exceeded | `{"status":"failed","code":429,"message":"Too Many Attempts.","data":null}` |

---

## Swagger / OpenAPI

Interactive, testable documentation:

```
http://127.0.0.1:8000/api/documentation
```

The raw OpenAPI 3 spec is served at `/docs`. Click **Authorize** in the UI,
paste a bearer token, and every endpoint is callable from the browser.
Regenerate after annotation changes with `php artisan l5-swagger:generate`.

### Postman collection

A ready-to-import Postman collection is included. Download it from the running
app; the route forces a file download:

```
GET /postman-collection
```

It is pre-wired with a `base_url` variable, collection-level bearer auth, a
**Login** request that auto-saves the token, and a **Create** request that
captures the new id, so every request works immediately. The raw file also
lives at `public/downloads/postman_collection.json`.

---

## Testing

The suite uses **Pest** with **72 tests** and **~99% coverage** (unit,
feature and performance-aware tests).

```bash
# Create the test database (once)
mysql -u root -e "CREATE DATABASE translation_management_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Run everything
php artisan test

# With a coverage report (requires Xdebug or PCOV)
php artisan test --coverage --min=95
```

> **MySQL is required for tests**: the suite exercises InnoDB FULLTEXT
> search, which SQLite cannot emulate. The test database name is set in
> `phpunit.xml` (`translation_management_test`).
>
> FULLTEXT tests use the `DatabaseMigrations` trait rather than
> `RefreshDatabase`, because InnoDB's full-text index is not reliably
> searchable from inside the still-open transaction `RefreshDatabase` keeps.

Coverage spans authentication, CRUD, validation, search (every filter),
export (correctness, freshness, ETag/304), rate limiting, the seeders, the
scalability command, and N+1 / query-count performance guards.

---

## Seeding 100k+ Records

A dedicated Artisan command generates a large dataset for scalability testing:

```bash
# Seed locales + tags first (php artisan db:seed), then:
php artisan translations:seed                   # 100,000 rows (default)
php artisan translations:seed --count=250000     # custom volume
php artisan translations:seed --count=100000 --chunk=5000
```

It uses **chunked bulk inserts** via the query builder, with no Eloquent
hydration and no per-row model events, and streams the *actual* inserted IDs
when attaching tags, so it stays correct regardless of auto-increment gaps.
Export caches are invalidated once at the end.

---

## Design Tradeoffs

| Decision | Rationale |
| -------- | --------- |
| **No Repository layer** | Eloquent is the data abstraction; with one backend, repositories add ceremony without payoff. Service + Query Filter keeps testability without overengineering. |
| **No DTO layer** | Form Requests already produce validated, typed arrays. Services consume `validated()` directly; a DTO would only re-wrap the same data. |
| **Response envelope via a facade** | One `{status, code, message, data}` shape for the whole API, predictable for clients. `JsonResponseService` is container-bound and exposed as the `ApiResponse` facade, so it stays mockable and swappable rather than a static helper. |
| **Native FULLTEXT over Scout** | Laravel Scout needs an external engine (Meilisearch/Algolia). MySQL FULLTEXT meets the performance budget with zero extra infrastructure. |
| **Versioned cache keys over tag-based cache** | Works on every cache driver, not just Redis; the assessment shouldn't assume Redis. |
| **Page-number pagination with hard `per_page` cap** | Simple, predictable client contract (`?page=N` is what most consumers expect). The 1–200 cap keeps every page bounded, and tests guard the response budget at 100k+ rows. |
| **`locale` code in the API, `locale_id` internally** | The public API stays human-friendly (`?locale=en`); the integer FK is resolved once in the Form Request. |
| **Tags auto-created on write** | A friendlier API for a small, controlled tag vocabulary; `firstOrCreate` keeps it idempotent. |
| **Search merged into `GET /translations`** | One filterable, paginated collection endpoint instead of a near-duplicate `/search`; less surface, no duplicated logic. |

---
