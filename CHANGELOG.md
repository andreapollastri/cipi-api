# Changelog

All notable changes to this project will be documented in this file.

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
