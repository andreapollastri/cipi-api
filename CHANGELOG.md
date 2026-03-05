# Changelog

All notable changes to this project will be documented in this file.

## [1.0.0] - 2026-03-05

### Added

- REST API for Cipi server control panel
  - Apps: list, show, create, edit, delete
  - Aliases: list, create, delete
  - SSL: install certificate
  - Jobs: show async job status with structured `result` field
- MCP server endpoint at `/mcp` (optional, requires `laravel/mcp` package)
- Swagger documentation at `/docs` with job result schemas
- Laravel Sanctum authentication with token abilities
- Artisan commands: `cipi:token-create`, `cipi:token-list`, `cipi:token-revoke`, `cipi:seed-api-user`
- Queue-based job execution for long-running Cipi operations
- Migration for `personal_access_tokens` table (Sanctum)
- Structured job result parsing: `GET /api/jobs/{id}` returns parsed `result` (app credentials, domain, SSH, database, deploy key, webhook, etc.) based on Cipi CLI output
- MCP routes load only when `laravel/mcp` is installed
