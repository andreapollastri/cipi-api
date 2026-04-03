# Changelog

All notable changes to this project will be documented in this file.

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
