# Changelog

All notable changes to this project will be documented in this file.

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
