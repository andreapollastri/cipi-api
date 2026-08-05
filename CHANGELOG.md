# Changelog

All notable changes to this project will be documented in this file.

## [1.15.0] - 2026-08-05

Server management, SMTP/healthchecks, IP whitelist, and safer app edit. Requires **Cipi CLI ≥ 5.0.6** (`cipi self-update`) for webhook recreate, PHP/SSH/service JSON listings, SMTP `--json`, `cipi api ip-whitelist`, and updated API sudoers.

### Added

- **`POST /api/apps/{name}/webhook/recreate`** — recreate GitHub/GitLab webhook; body `{ "rotate_secret": true }` also rotates `CIPI_WEBHOOK_TOKEN`. Async job (`app-webhook-recreate`). Ability `apps-edit`.
- **PHP** — `GET /api/php`, `POST /api/php/install`, `DELETE /api/php/{version}` (abilities `php-view` / `php-manage`).
- **DB engines** — `POST /api/dbs/engines/install`, `PUT /api/dbs/engines/default` (ability `dbs-manage`).
- **SSH keys** — `GET|POST /api/ssh/keys`, `DELETE /api/ssh/keys/{n}` (abilities `ssh-view` / `ssh-manage`).
- **Services** — `GET /api/services`, `POST /api/services/{name}/restart` (abilities `services-view` / `services-manage`).
- **SMTP** — `GET|PUT|DELETE /api/smtp`, `POST /api/smtp/enable|disable|test` (abilities `smtp-view` / `smtp-manage`). Password never returned on GET.
- **Healthchecks** — `GET /api/health`, `GET|PUT|DELETE /api/apps/{name}/health`, `POST /api/apps/{name}/health/check` (abilities `health-view` / `health-manage`).
- **IP whitelist middleware** — `cipi.ip` on `api/*` and `/mcp`. Reads `/etc/cipi/api-ip-whitelist` (`CIPI_API_IP_WHITELIST`). Missing file or `*` = allow all; otherwise IPv4/IPv6/CIDR. Rejected clients get `403` `{ "error": "IP not allowed", "ip": "…" }`.
- **IP whitelist REST** — `GET /api/ip-whitelist`, `PUT /api/ip-whitelist` (`entries`, optional `ensure_client_ip`), `POST /api/ip-whitelist` (`ip`), `DELETE /api/ip-whitelist` (`ip`), `POST /api/ip-whitelist/allow-all`. Abilities `ip-whitelist-view` / `ip-whitelist-manage`. PUT auto-adds the caller IP when restricting (unless `ensure_client_ip: false`).
- **MCP** — `AppWebhookRecreate`, `PhpList`, `IpWhitelistShow`.

### Changed

- **`PUT /api/apps/{name}`** — only forwards fields that differ from the current app (prevents no-op webhook/deploy-key recreation); PHP must be installed on the host (422 otherwise).
- **PHP allowlist** — installable versions tightened to `8.3`, `8.4`, `8.5` (matches Cipi CLI / Deployer 8).
- **OpenAPI** — `info.version` **1.15.0**.

## [1.14.0] - 2026-08-05

App `.env` / `auth.json` / Artisan, whitelisted `app run`, and structured deploy config. Requires **Cipi CLI ≥ 5.0.3** (`cipi self-update`) for non-interactive flags and sudoers whitelist (`app env`, `app artisan`, `app run`, `auth *`, deploy-config).

### Added

- **`.env` API** — `GET|PUT /api/apps/{name}/env` (ability `apps-env`): list variables; merge with `{ "set": {…}, "unset": […] }`. Sync. MCP: `AppEnvShow`, `AppEnvUpdate` (secrets redacted).
- **`auth.json` API** — `GET|POST|PUT|DELETE /api/apps/{name}/auth` (ability `apps-auth`): shared Composer/structured JSON under `shared/auth.json`. Distinct from HTTP Basic Auth (`apps-basicauth`). MCP: `AppAuthJson*`.
- **Artisan REST** — `POST /api/apps/{name}/artisan` with `{ "command": "…" }` → `202` job (`app-artisan`); poll `GET /api/jobs/{id}` for `output` / `exit_code`. Ability `apps-artisan`. MCP `AppArtisan` remains synchronous.
- **`POST /api/apps/{name}/run`** — body `{ "command": "composer install --no-dev" }` → `202` job (`app-run`); poll `GET /api/jobs/{id}` for output. Ability `apps-run`.
- **`GET /api/run-commands`** — list allowed binaries (composer, npm/npx/yarn/pnpm, ls/ll, cat/head/tail, mkdir/cp/mv/rm/…, tar/zip, git, php, node, find, …).
- **Guards** — no editors/pagers/shells/REPLs (`nano`, `vim`, `less`, `bash`, `tinker`, …); no interactive flags (`tail -f`, `git -i`, `php -a`, `node -e`, …); no shell metacharacters / `..` path traversal.
- **`GET|PUT /api/apps/{name}/deploy-config`** — structured Deployer recipe options (`keep_releases`, migrate/optimize/storage_link/queue_restart/horizon_terminate, `node_build`, `predeploy_snapshot`, `extra_artisan`). Regenerates `deploy.php` from template (not a raw PHP upload). Ability `apps-deploy-config`.
- **Token abilities** — `apps-env`, `apps-auth`, `apps-artisan`, `apps-run`, `apps-deploy-config` in `config/cipi.php`.
- **CLI whitelist** — `app env`, `auth create|edit|show|delete` in `CipiCliService::ALLOWED_COMMANDS`.
- **MCP** — `AppRun`, `AppRunCommands`, `AppDeployConfigShow`, `AppDeployConfigUpdate`.

### Changed

- **OpenAPI** — `info.version` **1.14.0**; paths/schemas for env, auth.json, artisan, app-run, deploy-config; job types `app-artisan`, `app-run`.

## [1.13.0] - 2026-08-03

Optional Laravel Octane (FrankenPHP) on app create — requires Cipi **5.0+**.

### Added

- **App create `octane`** — `POST /api/apps` accepts `octane: true` or `octane: "frankenphp"` (Laravel only; rejected with `custom`). Dispatches `cipi app create --octane=frankenphp`. MCP `AppCreate` updated.
- App list/show expose **`octane`** and **`octane_port`** from `apps-public.json`

### Changed

- **OpenAPI** — `info.version` bumped to **1.13.0**; `AppCreateRequest.octane`, list/show octane fields

## [1.12.1] - 2026-08-03

Home page noindex and PostgreSQL in server status when installed.

### Added

- **Home robots** — welcome page meta `noindex, nofollow`, `X-Robots-Tag` on `/`, and `/robots.txt` (`User-agent: *` / `Disallow: /`)

### Fixed

- **`GET /api/status`** — host-read fallback includes **`postgresql`** when the systemd unit is installed (same as `cipi status` on Cipi 4.8+); CLI-parsed snapshots already surfaced it when present

### Changed

- **OpenAPI** — `info.version` bumped to **1.12.1**; status `services` example includes `postgresql`

## [1.12.0] - 2026-08-02

Cipi 4.8 support: www/apex redirects, force HTTPS, and multi-engine databases (MariaDB + PostgreSQL).

### Added

- **WWW redirects** — REST endpoints under `/api/apps/{name}/www` wrapping [`cipi www`](https://cipi.sh/docs/infrastructure#www):
  - `GET …/www` — sync status (`primary`, `apex`, `www`, `redirect`)
  - `POST …/www/add` — add counterpart host alias
  - `POST …/www/force-to-root` / `force-from-root` — canonical 301 redirects
  - `POST …/www/clear` — clear redirect state
  - Token ability **`www-manage`**; MCP tools `WwwStatus`, `WwwAdd`, `WwwForceToRoot`, `WwwForceFromRoot`, `WwwClear`
- **`POST /api/apps/{name}/ssl/force`** — re-apply HTTP → HTTPS redirect without new issuance (`cipi ssl force`). MCP tool `SslForce`
- **Multi-engine databases** — optional `engine` (`mariadb`|`pgsql`) on create/list/delete/backup/restore/password; **`GET /api/dbs/engines`** lists installed engines and default. MCP tool `DbEngines`
- **App create `engine`** — Laravel apps can select DB engine at create time (`POST /api/apps`, MCP `AppCreate`)
- App list/show expose **`engine`**, **`www_redirect`**, and **`force_https`** from `apps-public.json` / apps metadata

### Changed

- **`CipiCliService`** — allows `www *`, `ssl force`, and `db engines`
- **`CipiOutputParser`** — parses Cipi 4.8 `db list` / `db engines` / www / ssl force output (keeps legacy db list shape)
- **`db delete` / `db restore`** jobs pass `--force` for non-interactive API use
- **OpenAPI** — `info.version` bumped to **1.12.0**

## [1.11.12] - 2026-07-01

Reliable paginated app logs under `open_basedir` and accurate app suspend status.

### Fixed

- **Laravel log paths** — always read `shared/storage/logs/*.log` for non-custom apps instead of gating on a sudo directory probe that could fail while log files exist.
- **Paginated log parsing** — scan `===CIPI_LOG_FILE:` markers linearly so stack traces cannot break regex-based block splitting; parse file paths with `strrpos` on the line-count suffix.
- **`open_basedir`** — paginated app logs are fetched via `sudo cipi app logs read` (CLI runs as root) because cipi-api PHP cannot read `/home/*` directly; falls back to `cipi-read-app-logs` when CLI output is empty.
- **`isSuspended()`** — treats JSON string `"true"` / `"1"` as suspended (`apps.json` can store booleans as strings).
- **`GET /api/apps` / `GET /api/apps/{name}`** — `suspended` reflects live status via `isSuspended()` instead of a stale `apps.json` value.

### Changed

- **`GET /api/apps/{name}/logs`** — CLI-first paginated reads (`cipi app logs read`); optional `warnings` when no output; sudo fallback via `cipi-read-app-logs` or inline bash.
- **`availableTypes()`** — always exposes `laravel` for managed apps.
- **`CipiCliService`** — allows `app logs read` for the log snapshot command.
- **`CipiLogReader`** — refactored paginated tail (`tailPaginatedViaSudo`, `parsePaginatedOutput`, local read helpers).
- **OpenAPI** — `info.version` bumped to **1.11.12**.

## [1.11.11] - 2026-06-30

PHP 8 compatibility for paginated app logs.

### Fixed

- **`GET /api/apps/{name}/logs`** — parenthesized ternary/`?:` chain when splitting redacted log lines (PHP 8+ fatal: `Unparenthesized a ? b : c ?: d`).

### Changed

- **OpenAPI** — `info.version` bumped to **1.11.11**.

## [1.11.10] - 2026-06-30

No changes

## [1.11.9] - 2026-06-30

Paginated app logs over REST.

### Added

- **`GET /api/apps/{name}/logs`** — synchronous, paginated log snapshots for nginx, PHP-FPM, Laravel (when present), worker, and deploy logs. Query params: `type` (default `all`), `page` (default `1`, most recent first), `per_page` (default `50`, max `1000`). Requires `apps-view` ability. Matches `cipi app logs` paths; log text is redacted via `McpProductionContent`.
- **`CipiLogReader::tailViaSudoPaginated()`** — page-based reads from the end of each log file via sudo.
- **`CipiAppLogsService::readPaginated()`** and **`availableTypes()`** — structured JSON payload for REST.

### Changed

- **OpenAPI** — `info.version` bumped to **1.11.9**; documents `/apps/{name}/logs`.

## [1.11.8] - 2026-06-10

Server status via `cipi status` CLI (with host fallback).

### Changed

- **`CipiServerStatusService`** — prefers `sudo cipi status` and parses CLI output into the same structured JSON as before; falls back to direct host reads as `www-data` when sudo is unavailable (same pattern as `CipiDatabaseListCliService`).
- **`CipiOutputParser`** — adds `status` parser for `cipi status` output (system, resources, services, PHP pools, app count).
- **OpenAPI** — `info.version` bumped to **1.11.8**; `/status` documents the CLI-first path and sudo requirement.

### Fixed

- **`GET /api/status` / `ServerStatus` MCP tool** — no longer crash on `open_basedir` when counting PHP pools; CLI path avoids the restriction entirely, and the host fallback skips `is_dir()` outside the allowlist.

## [1.11.7] - 2026-06-10

Canonical token ability list for `cipi api token create`.

### Added

- **`config/cipi.php` → `token_abilities`** — canonical ability list for REST routes (includes `status-view`, `apps-suspend`, `apps-basicauth`, and all other route abilities).
- **`php artisan cipi:token-abilities`** — prints `ability|description` lines; `cipi api token create` reads this list (requires matching Cipi CLI update in `lib/api.sh`).

### Changed

- **OpenAPI** — `info.version` bumped to **1.11.7**.

## [1.11.6] - 2026-06-10

Structured server status over REST and MCP.

### Added

- **`GET /api/status`** — synchronous snapshot matching `cipi status` (System, Resources, Services, PHP, Apps count) as structured JSON. Requires `status-view` token ability. Reads host metrics as `www-data` (no sudo).
- **`status-view` token ability** — gates the status endpoint.
- **`CipiServerStatusService`** — collects the same metrics as `cipi status` (`free`, `df`, `top`, `systemctl`, PHP pools, apps.json count).

### Changed

- **`ServerStatus` MCP tool** — returns the same structured JSON as `GET /api/status` (still requires only `mcp-access`).
- **OpenAPI** — `info.version` bumped to **1.11.6**; documents `/status`.

## [1.11.5] - 2026-06-10

Safer MCP responses when exposing production logs or command output to AI clients.

### Added

- **`McpProductionContent`** — server-side redaction for common secrets (`password`, `Bearer`, `DB_PASSWORD`, SSH/DB credentials in CLI output, etc.) and a high-risk pattern detector (API tokens, payment-card-like numbers, DB connection strings) that prepends a targeted alert only when a match is found.

### Changed

- **MCP log tools** (`AppLogs`, `ApiLogShow`) — every response is prefixed with a mandatory production-content warning (_You are about to send production logs to the model. They may include personal data or secrets._); log text is redacted before delivery.
- **MCP sensitive output** (`JobShow` CLI `output`, `AppArtisan`) — redaction and conditional high-risk alert; structured job `result` (e.g. app-create credentials) is left intact.
- **Log defaults** — `CipiLogReader::DEFAULT_LINES` lowered from 100 to **50** (max remains 1000).
- **OpenAPI** — `info.version` bumped to **1.11.5**.

## [1.11.4] - 2026-06-10

Graceful MCP errors when required tool arguments are missing.

### Fixed

- **MCP argument validation** — new `McpArgValidator` applied across MCP tools; missing required parameters return `Error: … is required` instead of `TypeError` in `production.ERROR` (e.g. probe calls to `JobShow`, `AliasAdd`, `AppLogs`).

### Changed

- **OpenAPI** — `info.version` bumped to **1.11.4**.

## [1.11.3] - 2026-06-10

Fix MCP clients (e.g. Cursor) only discovering the first 15 tools.

### Fixed

- **`CipiServer`** — raises `defaultPaginationLength` to 50 so `tools/list` returns all registered tools in one page. Laravel MCP defaults to 15; clients that do not paginate `nextCursor` were missing `AliasAdd`, database tools, `JobShow`, `AppLogs`, etc. `tools/call` still worked for omitted tools.

### Changed

- **OpenAPI** — `info.version` bumped to **1.11.3**.

## [1.11.2] - 2026-06-10

Drop the unused `logs-view` token ability from the documented ability set.

### Removed

- **`logs-view` token ability** — never used on REST routes; `ApiLogShow` is available with `mcp-access` only (same as all other MCP tools).

### Changed

- **OpenAPI** — `info.version` bumped to **1.11.2**.

## [1.11.1] - 2026-06-10

Simplified MCP authorization: one ability for all tools.

### Changed

- **MCP authorization** — all MCP tools are gated only by the `mcp-access` token ability. Per-tool REST abilities (`apps-view`, `deploy-manage`, etc.) are no longer checked on `/mcp`.
- **OpenAPI** — `info.version` bumped to **1.11.1**; MCP description updated accordingly.

## [1.11.0] - 2026-06-10

MCP job polling, log reading, Artisan, and server monitoring tools.

### Added

- **MCP tools**
  - `JobShow` — poll background job status, parsed `result`, and CLI `output` (same data as `GET /api/jobs/{id}`).
  - `AppLogs` — read recent app log snapshots by type (`all`, `nginx`, `php`, `worker`, `deploy`, `laravel`), matching [`cipi app logs`](https://cipi.sh/docs/apps#cli-app-logs). Requires `apps-view`.
  - `ApiLogShow` — read recent Laravel logs for the Cipi API host app (`storage/logs/`).
  - `AppArtisan` — run Artisan on a Laravel app synchronously (same as [`cipi app artisan`](https://cipi.sh/docs/apps#cli-app-artisan)). Custom apps and `tinker` are rejected.
  - `ServerStatus` — server snapshot via `cipi status` (CPU, RAM, disk, services). Gated only by `mcp-access`.
  - `ServiceList` — system service status via `cipi service list [service]`. Gated only by `mcp-access`.
- **Services** — `CipiJobStatusService`, `CipiLogReader`, `CipiAppLogsService`, `CipiApiLogService`, `CipiAppArtisanService`, and `CipiServerMonitorService`.

### Changed

- Async MCP tools now suggest polling via `JobShow` instead of the REST endpoint.
- `JobController` delegates formatting to `CipiJobStatusService` (output truncated at 50k chars).
- **`CipiCliService`** — `app artisan`, `status`, and `service list` added to `ALLOWED_COMMANDS`.
- **`CipiValidationService`** — adds `isCustomApp(name)` helper reading the `custom` flag from `apps.json`.
- **OpenAPI** — `info.version` bumped to **1.11.0**; MCP description mentions job, log, Artisan, and server status tools.

## [1.10.0] - 2026-06-09

HTTP Basic Auth management for apps, wiring the REST API and MCP server to Cipi `cipi basicauth` commands (Nginx gatekeeper — unrelated to `cipi auth` / `auth.json`).

### Added

- **Basic auth endpoints**
  - `GET /api/apps/{name}/basicauth` — returns `{ enabled, users }` synchronously via `cipi basicauth status`. Requires `apps-basicauth`.
  - `POST /api/apps/{name}/basicauth/enable` — enables HTTP Basic Auth with optional `user` and `password` (auto-generated when omitted; returned once in the response). Runs `cipi basicauth enable` synchronously. Requires `apps-basicauth`.
  - `POST /api/apps/{name}/basicauth/disable` — removes basic auth and restores the normal vhost. Returns **409** when not enabled. Runs `cipi basicauth disable` synchronously. Requires `apps-basicauth`.
- **`basic_auth` flag** — `GET /api/apps` now exposes a boolean `basic_auth` field per app (from `apps.json`).
- **MCP tools** — `AppBasicAuthStatus`, `AppBasicAuthEnable`, and `AppBasicAuthDisable`, secured with `apps-basicauth`.
- **Token ability** — new `apps-basicauth` ability gates the REST routes and MCP tools.
- **OpenAPI** — `info.version` bumped to **1.10.0**; new paths and `BasicAuth*` schemas; `basic_auth` on app list schema.

### Changed

- **`CipiCliService`** — `basicauth enable`, `basicauth disable`, and `basicauth status` added to `ALLOWED_COMMANDS`.
- **`CipiValidationService`** — adds `isBasicAuthEnabled(name)` helper reading the `basic_auth` flag from `apps.json`.

## [1.9.0] - 2026-06-09

Primary domain change via app edit, aligned with [Cipi 4.6.2](https://github.com/cipi-sh/cipi/releases/tag/4.6.2) (`cipi app edit <app> --domain=<new>`).

### Added

- **`domain` on app edit** — `PUT /api/apps/{name}` accepts an optional `domain` field to rename the app's primary domain. Validates format synchronously and returns **409** if the domain is already used by another app (aliases of the current app are allowed, so promoting an alias to primary works). Dispatches `cipi app edit --domain=` as an `app-edit` async job. Composable with existing `php`, `branch`, and `repository` fields.
- **MCP `AppEdit` tool** — adds optional `domain` parameter with the same validation.
- **OpenAPI** — `info.version` bumped to **1.9.0**; `AppEditRequest.domain` schema, updated endpoint description, **409** response on edit, and `domain` in the `JobResultAppEdit` example.

## [1.8.1] - 2026-06-02

App suspend / unsuspend support, wiring the REST API and MCP server to the Cipi 4.5.8 lifecycle commands so apps can be taken offline (HTTP 503) without being deleted.

### Added

- **Suspend / unsuspend endpoints**
  - `POST /api/apps/{name}/suspend` — replaces the app's Nginx vhost with a generic static suspension page served as **HTTP 503** (HTTPS included). Validates the app exists synchronously and returns **409** if it is already suspended. Dispatches an `app-suspend` async job. Requires the new `apps-suspend` ability.
  - `POST /api/apps/{name}/unsuspend` — restores the normal vhost to bring the app back online. Validates the app exists synchronously and returns **409** if it is not currently suspended. Dispatches an `app-unsuspend` async job. Requires `apps-suspend`.
  - Both wrap the Cipi `cipi app suspend <app>` / `cipi app unsuspend <app>` commands from [Cipi 4.5.8](https://github.com/cipi-sh/cipi/releases/tag/4.5.8); suspension state lives in `apps.json` (`suspended`) and survives vhost regeneration.
- **`suspended` flag** — `GET /api/apps` and `GET /api/apps/{name}` now expose a boolean `suspended` field per app.
- **MCP tools** — `AppSuspend` and `AppUnsuspend`, secured with the `apps-suspend` ability and mirroring the REST validation.
- **Token ability** — new `apps-suspend` ability gates both the REST routes and the MCP tools.
- **Job result parsing** — `CipiOutputParser` parses `app-suspend` / `app-unsuspend` CLI output into structured `{ app, suspended }` results.
- **OpenAPI** — `info.version` bumped to **1.8.1**; new paths and `JobResultAppSuspend` / `JobResultAppUnsuspend` schemas; `app-suspend` / `app-unsuspend` added to the job-type enum; `suspended` added to the app list/show schemas.

### Changed

- **`CipiCliService`** — `app suspend` and `app unsuspend` added to the `ALLOWED_COMMANDS` whitelist.
- **`CipiValidationService`** — adds `isSuspended(name)` helper reading the `suspended` flag from `apps.json`.

## [1.6.10] - 2026-04-27

### Fixed

- **Auth redirect for MCP:** `Authenticate::redirectUsing` in `CipiApiServiceProvider` now includes `/mcp` (and `mcp/*`) alongside `api/*`, so unauthenticated MCP traffic always uses the JSON **401** path instead of a browser redirect. This matches token-only auth for MCP and avoids edge cases where a non-JSON `Accept` header could still hit the web redirect branch (the same class of issue as the missing `login` route fixed in **1.6.2**).

## [1.6.9] - 2026-04-03

### Changed

- **Welcome page (`/`):** Restyled to match Laravel’s default framework error-page layout (inline Tailwind-style utilities, two-column header: **Cipi** | **Easy Laravel Deployments**, **API Swagger** link below). Supports `prefers-color-scheme: dark` alongside the light theme.
- **OpenAPI** `info.version` **1.6.9**.

## [1.6.8] - 2026-04-03

### Changed

- **Welcome page (`/`):** Minimal landing with the title **Cipi - Easy Laravel Deployments** and a single link to **API Swagger** (`/docs`); removed marketing copy, theme toggle, and external links.
- **OpenAPI** `info.version` **1.6.8**.

## [1.6.7] - 2026-04-03

### Added

- **`CipiDatabaseListCliService`:** runs **`sudo cipi db list`** via `CipiCliService`, parses output with `CipiOutputParser` for `GET /api/dbs` and MCP `DbList`.

### Changed

- **`GET /api/dbs`:** Lists databases through the **Cipi server CLI** (synchronous), not a background job and not a direct MySQL connection from Laravel—secrets stay inside Cipi.

### Removed

- **`CipiMysqlDatabaseListService`**, **`CipiServerSecretsService`**, and related **`config/cipi.php`** keys (`mysql_list`, vault paths, `cipi_mysql_list` connection registration) introduced in intermediate iterations.

## [1.6.6] - 2026-04-03

### Fixed

- **`GET /api/dbs` credentials:** The list endpoint no longer reuses the app `mysql` connection by default. A dedicated connection **`cipi_mysql_list`** is registered from `config/cipi.php` → **`mysql_list`**, with **`CIPI_MYSQL_LIST_PASSWORD`** (and optional host/user/socket) so MariaDB can be reached when `.env` has `root` and an empty `DB_PASSWORD` (which caused `Access denied … using password: NO` over `127.0.0.1`).

### Changed

- **`CIPI_MYSQL_LIST_CONNECTION`** default is now **`cipi_mysql_list`** (was `mysql`). Set `CIPI_MYSQL_LIST_CONNECTION=mysql` only if that connection already has a valid password and `SHOW DATABASES` rights.
- **OpenAPI** `info.version` **1.6.6**.

## [1.6.5] - 2026-04-03

### Fixed

- **`GET /api/dbs` / `cipi-cli db list`:** Listing no longer uses the `db list` background job or `sudo cipi`, which could fail (exit code, sudo, CLI environment) even when MySQL was healthy. The endpoint now reads database names and approximate sizes from MySQL/MariaDB using the Laravel DB connection (`CIPI_MYSQL_LIST_CONNECTION`, default `mysql`), matching how `GET /api/apps` reads `apps.json`.

### Added

- **`CipiMysqlDatabaseListService`** and config keys `mysql_list_connection`, `mysql_system_databases` in `config/cipi.php`.
- **`MysqlDatabaseListingUnavailableException`:** returned as JSON **503** when the configured connection is not mysql/mariadb or `SHOW DATABASES` fails.

### Changed

- **MCP `DbList`:** Returns the list inline (no job polling).
- **`CipiOutputParser::parseDbList`:** Completed jobs with an empty list now return `databases: []` instead of `null`.
- **OpenAPI:** `GET /api/dbs` documents **200** + `DbListResponse`, **503**, and `info.version` **1.6.5**.

## [1.6.4] - 2026-04-03

### Fixed

- **Database CLI jobs:** `CipiCliService` now allows all `db` command prefixes (`db list`, `db create`, `db delete`, `db backup`, `db restore`, `db password`). They were missing from the sudo whitelist, so queued jobs failed immediately with “Command not allowed” and clients (including `cipi-cli db list`) saw `job failed` even when Cipi on the server was working.

### Added

- **`DisallowedCipiCommandException`:** thrown when application code tries to queue a Cipi CLI command whose prefix is not in the whitelist, so misconfiguration surfaces at dispatch time instead of as a perpetually failing job.
- **`CipiJobService::dispatch()`** validates the command string with `CipiCliService::commandIsPermitted()` before creating the job record and dispatching `RunCipiCommand`.

### Changed

- **`CipiCliService`:** `ALLOWED_COMMANDS` is a documented `public const`; deploy matching uses the prefix `deploy ` (with a trailing space) so it does not collide with other commands starting with `deploy`.
- **`CipiApiServiceProvider`:** registers a renderable handler so `DisallowedCipiCommandException` returns JSON `500` with an `error` message for `api/*` and JSON requests.
- **OpenAPI / Swagger:** `public/api-docs/openapi.json` `info.version` set to **1.6.4** (aligned with this release).

## [1.6.3] - 2026-04-03

### Changed

- **OpenAPI / Swagger:** `public/api-docs/openapi.json` (served at `/docs`) now documents the database REST API (`/dbs`, backup, restore, password), request bodies, path parameters, and job `type` / `JobResultDb*` shapes for `GET /api/jobs/{id}`. Spec `info.version` set to **1.6.3**.
- **README:** Swagger section notes what the OpenAPI spec covers (including databases and job `result` types).

## [1.6.2] - 2026-04-03

### Fixed

- **Auth redirect without web login:** `CipiApiServiceProvider` registers `Authenticate::redirectUsing` so installs without a named `login` route no longer throw `Route [login] not defined` when Sanctum rejects a request. Unauthenticated `api/*` and JSON requests get a normal 401 flow; browser requests fall back to `/` (welcome) when no `login` route exists.

## [1.6.1] - 2026-03-20

### Changed

- **Custom apps without Git (SFTP-only):** `POST /api/apps` and MCP `AppCreate` align with [Cipi 4.4.4+](https://github.com/cipi-sh/cipi): for `custom: true`, `repository` is optional. Omit `repository` and `branch` to provision a custom app for SFTP upload to `~/htdocs` with no Git deploy. Laravel (non-custom) apps still require `repository`.
- OpenAPI: `AppCreateRequest` documents `custom` and `docroot`; `repository` is no longer globally required in the schema (runtime validation enforces it for Laravel apps).

## [1.6.0] - 2026-03-19

### Added

- Custom app support: `POST /api/apps` now accepts `custom` (boolean) and `docroot` (string) parameters to create non-Laravel apps with classic deploy (no zero-downtime)
- Database management APIs:
  - `GET /api/dbs` — list all databases (`dbs-view`)
  - `POST /api/dbs` — create a database (`dbs-create`)
  - `DELETE /api/dbs/{name}` — delete a database (`dbs-delete`)
  - `POST /api/dbs/{name}/backup` — backup a database (`dbs-manage`)
  - `POST /api/dbs/{name}/restore` — restore from backup (`dbs-manage`)
  - `POST /api/dbs/{name}/password` — regenerate password (`dbs-manage`)
- MCP tools: `DbList`, `DbCreate`, `DbDelete`, `DbBackup`, `DbRestore`, `DbPassword`
- Output parsers for all database CLI commands
- New token abilities: `dbs-view`, `dbs-create`, `dbs-delete`, `dbs-manage`

## [1.5.2] - 2026-03-16

### Changed

- Package renamed to `cipi/api` (from `andreapollastri/cipi-api`)
- README: updated installation command and MCP tools table formatting

## [1.5.1] - 2026-03-16

### Added

- Laravel 13 support

## [1.0.0] - 2026-03-06

### Added

- REST API for Cipi server control panel
  - Apps: list, show, create, edit, delete
  - Aliases: list, create, delete
  - Deploy: deploy, rollback, unlock
  - SSL: install certificate
  - Jobs: show async job status with structured `result` field
- MCP server endpoint at `/mcp` (Streamable HTTP, optional, requires `laravel/mcp` package)
  - Tools: `AppList`, `AppShow`, `AppCreate`, `AppEdit`, `AppDelete`, `AppDeploy`, `AppDeployRollback`, `AppDeployUnlock`, `AliasList`, `AliasAdd`, `AliasRemove`, `SslInstall`
  - Setup instructions for VS Code, Cursor, and Claude Code
- Swagger documentation at `/docs` with job result schemas
- Laravel Sanctum authentication with token abilities (`apps-view`, `apps-create`, `apps-edit`, `apps-delete`, `aliases-view`, `aliases-create`, `aliases-delete`, `deploy-manage`, `ssl-manage`, `mcp-access`)
- Artisan commands: `cipi:token-create`, `cipi:token-list`, `cipi:token-revoke`, `cipi:seed-api-user`
- Queue-based job execution for long-running Cipi operations
- Migration for `personal_access_tokens` table (Sanctum)
- Structured job result parsing: `GET /api/jobs/{id}` returns parsed `result` (app credentials, domain, SSH, database, deploy key, webhook, deploy status, rollback status, unlock status, etc.) based on Cipi CLI output
- MCP routes load only when `laravel/mcp` is installed
