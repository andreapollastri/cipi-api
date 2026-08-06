# Cipi API

Laravel package that exposes a REST API, an MCP server, and Swagger documentation for the [Cipi](https://cipi.sh) server control panel.

## Requirements

- PHP 8.2+
- Laravel 12+

## Installation

```bash
composer require cipi/api
```

Publish the configuration and assets:

```bash
php artisan vendor:publish --tag=cipi-config
php artisan vendor:publish --tag=cipi-assets
php artisan migrate
```

Seed the API user and create a token:

```bash
php artisan cipi:seed-api-user
php artisan cipi:token-create
```

## Features

- **REST API** — CRUD for apps, aliases, www redirects, databases, SSL, and async jobs (`/api/*`), secured with Laravel Sanctum and token abilities. App create supports optional Git for **custom** apps (SFTP-only), matching Cipi 4.4.4+. Apps can also be taken offline and restored with **suspend / unsuspend** (HTTP 503 maintenance page), matching Cipi 4.5.8+. **HTTP Basic Auth** can be enabled, disabled, and inspected per app via `/api/apps/{name}/basicauth/*` (synchronous, wraps `cipi basicauth`). **App `.env`** (`GET|PUT /api/apps/{name}/env`, ability `apps-env`) merges key/value pairs without replacing the whole file. **Shared `auth.json`** (`/api/apps/{name}/auth`, ability `apps-auth`) is Composer/structured JSON — not HTTP Basic Auth. **Artisan** (`POST /api/apps/{name}/artisan`, ability `apps-artisan`) runs as an async job; poll `GET /api/jobs/{id}` for output. **Whitelisted app run** (`POST /api/apps/{name}/run`, `GET /api/run-commands`, ability `apps-run`) executes non-interactive binaries as the app user (composer, npm, ls, rm, git, … — no nano/vim/less/bash/tinker). **Deploy config** (`GET|PUT /api/apps/{name}/deploy-config`, ability `apps-deploy-config`) edits structured Deployer options (keep_releases, hooks, node_build, extra_artisan) and regenerates `deploy.php` — not a raw PHP upload. Env/auth/artisan/run/deploy-config need **Cipi ≥ 5.0.3**. **WWW / apex redirects** (`/api/apps/{name}/www/*`, ability `www-manage`) and **`POST …/ssl/force`** match [Cipi 4.8+](https://cipi.sh/docs/). **Multi-engine databases** (MariaDB + optional PostgreSQL) via `engine` on `/api/dbs*` and `GET /api/dbs/engines`. **App logs** (`GET /api/apps/{name}/logs`) return paginated nginx, PHP-FPM, and Laravel snapshots (requires `apps-view`). **Server status** (`GET /api/status`) returns the same data as `cipi status` as structured JSON (requires `status-view`).
- **IP whitelist** — optional client IP allowlist for `/api/*` and `/mcp` (`/etc/cipi/api-ip-whitelist`, default `*` = allow all). Manage with `cipi api ip-whitelist` or `GET|PUT|POST|DELETE /api/ip-whitelist` (abilities `ip-whitelist-view` / `ip-whitelist-manage`). Requires **Cipi ≥ 5.0.8**.
- **MCP Server** — Model Context Protocol endpoint at `/mcp` for AI-powered integrations.
- **Swagger Docs** — Interactive API reference at `/docs`, generated from `public/api-docs/openapi.json`. The spec covers apps (including env, auth.json, artisan), aliases, www, deploy, SSL, databases (`GET /api/dbs` / `/dbs/engines` via CLI; other `/api/dbs/*` actions use jobs), and job polling (including structured `result` types per job).
- **Artisan Commands** — `cipi:token-create`, `cipi:token-list`, `cipi:token-revoke`.

## MCP Integration

The MCP server is exposed at `/mcp` using [Streamable HTTP](https://modelcontextprotocol.io/) transport and is secured with the same Sanctum token used by the REST API. A token with the **`mcp-access`** ability is sufficient for **all** MCP tools — per-endpoint REST abilities (`apps-view`, `deploy-manage`, etc.) are not required on `/mcp`.

Generate a token if you haven't already:

```bash
php artisan cipi:token-create
```

Replace `https://your-server.com` and `YOUR_TOKEN` in the examples below with your actual Cipi host and token.

### VS Code

Create (or edit) `.vscode/mcp.json` in your workspace:

```json
{
  "inputs": [
    {
      "type": "promptString",
      "id": "cipi-token",
      "description": "Cipi API Token",
      "password": true
    }
  ],
  "servers": {
    "cipi-api": {
      "type": "http",
      "url": "https://your-server.com/mcp",
      "headers": {
        "Authorization": "Bearer ${input:cipi-token}"
      }
    }
  }
}
```

> Restart VS Code after adding the configuration. The token will be requested on first connection and securely stored.

### Cursor

Create (or edit) `.cursor/mcp.json` in your project root:

```json
{
  "mcpServers": {
    "cipi-api": {
      "url": "https://your-server.com/mcp",
      "headers": {
        "Authorization": "Bearer YOUR_TOKEN"
      }
    }
  }
}
```

> Restart Cursor after adding the configuration (Cursor v0.40+).

### Claude Code

Run the following command from your terminal:

```bash
claude mcp add --transport http cipi-api https://your-server.com/mcp \
  --header "Authorization: Bearer YOUR_TOKEN"
```

Verify the server is connected:

```bash
claude mcp list
```

### Available MCP Tools

Once connected, the following tools are available to the AI agent:

| Tool                | Description                                           |
| ------------------- | ----------------------------------------------------- |
| `AppList`           | List all apps with domains, PHP versions, and aliases |
| `AppShow`           | Show details of a specific app                        |
| `AppArtisan`        | Run Artisan on a Laravel app (sync; REST uses async jobs) |
| `AppRun`            | Whitelisted non-interactive cmd as app user (async job; Cipi ≥ 5.0.3) |
| `AppRunCommands`    | List allowed `AppRun` binaries                            |
| `AppDeployConfigShow` | Show structured deploy.php options                      |
| `AppDeployConfigUpdate` | Update deploy options (regenerates template)          |
| `AppEnvShow`        | List Laravel app `.env` keys (Cipi ≥ 5.0.3; secrets redacted) |
| `AppEnvUpdate`      | Merge/unset `.env` keys                               |
| `AppAuthJsonShow`   | Show shared `auth.json` (not HTTP Basic Auth)         |
| `AppAuthJsonCreate` | Create shared `auth.json`                             |
| `AppAuthJsonUpdate` | Replace shared `auth.json`                            |
| `AppAuthJsonDelete` | Delete shared `auth.json`                             |
| `AppCreate`         | Create a new app (`custom` for non-Laravel apps; optional Git for custom SFTP-only sites, Cipi 4.4.4+) |
| `AppEdit`           | Edit an existing app                                  |
| `AppDelete`         | Delete an app                                         |
| `AppDeploy`         | Deploy an app                                         |
| `AppDeployRollback` | Rollback the last deploy                              |
| `AppDeployUnlock`   | Unlock a stuck deploy                                 |
| `AppSuspend`        | Take an app offline (HTTP 503) without deleting it    |
| `AppUnsuspend`      | Bring a suspended app back online                     |
| `AppBasicAuthStatus`| Show HTTP Basic Auth status (enabled + usernames)     |
| `AppBasicAuthEnable`| Enable Nginx HTTP Basic Auth on an app                |
| `AppBasicAuthDisable` | Disable HTTP Basic Auth on an app                 |
| `AliasList`         | List aliases for an app                               |
| `AliasAdd`          | Add an alias to an app                                |
| `AliasRemove`       | Remove an alias from an app                           |
| `WwwStatus`         | Show www/apex redirect status (Cipi 4.8+)             |
| `WwwAdd`            | Add www/apex counterpart alias                        |
| `WwwForceToRoot`    | 301 redirect www → apex                               |
| `WwwForceFromRoot`  | 301 redirect apex → www                               |
| `WwwClear`          | Clear www canonical redirect                          |
| `DbEngines`         | List installed DB engines and default (Cipi 4.8+)     |
| `DbList`            | List databases (optional `engine` filter)             |
| `DbCreate`          | Create a database (`engine` = mariadb\|pgsql)         |
| `DbBackup`          | Create a compressed backup of a database              |
| `DbRestore`         | Restore a database from a backup file                 |
| `DbPassword`        | Regenerate database password                          |
| `SslInstall`        | Install an SSL certificate for an app                 |
| `SslForce`          | Re-apply HTTP → HTTPS redirect (no new issuance)      |
| `JobShow`           | Poll async job status, result, and CLI output         |
| `AppLogs`           | Read recent app logs (`cipi app logs` types)          |
| `ApiLogShow`        | Read Cipi API host Laravel logs                       |
| `ServerStatus`      | Server snapshot (same JSON as `GET /api/status`)       |
| `ServiceList`       | System service status (`cipi service list`)           |

## Configuration

This package is automatically installed and configured by `cipi api`. No manual setup is needed.

The `CIPI_APPS_JSON` env variable defaults to `/etc/cipi/apps.json`.

Token abilities for `cipi api token create` are defined in `config/cipi.php` (`token_abilities`) and exposed via `php artisan cipi:token-abilities`. The Cipi CLI checklist calls that command when the API package is installed.

`GET /api/dbs` runs **`sudo cipi db list`** on the host (synchronously), like the Cipi server CLI: vault and MariaDB access stay inside Cipi, not duplicated in PHP.

**Why other API actions worked but `db` failed:** Cipi configures **`/etc/sudoers.d/cipi-api`** so `www-data` may run **`NOPASSWD`** only for an explicit list of `cipi` subcommands (`app`, `deploy`, `alias`, `ssl`, …). Database commands were missing from that whitelist until **Cipi 4.4.17**, so `sudo` tried to ask for a password and failed without a TTY (`sudo: a terminal is required`). Update the server with **`cipi self-update`** (applies migration 4.4.17) or add the `cipi db …` lines to `cipi-api` sudoers manually (see Cipi `setup.sh`).

**`AppArtisan` / env / auth.json:** require **Cipi CLI ≥ 5.0.3** (`cipi self-update`) so `/etc/sudoers.d/cipi-api` allows `app artisan`, `app env`, and `auth create|edit|show|delete`, and so non-interactive env/auth flags exist. REST Artisan is async (`POST /api/apps/{name}/artisan` → poll job); MCP `AppArtisan` stays synchronous.

**`AppRun` / `AppDeployConfig*`:** require **Cipi CLI ≥ 5.0.3**. App run is non-interactive only. Deploy config is structured knobs (never raw `deploy.php` upload).

**`ServerStatus` / `ServiceList`:** `ServerStatus` returns structured JSON (same as `GET /api/status`), preferring `sudo cipi status` with a host-read fallback. `ServiceList` runs `sudo cipi service list` on the host. Both require `mcp-access` only on `/mcp`. Ensure `cipi-api` sudoers allows `status` and `service list` (see Cipi `setup.sh`).

## License

MIT
