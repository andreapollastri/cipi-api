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

- **REST API** — CRUD for apps, aliases, databases, SSL, and async jobs (`/api/*`), secured with Laravel Sanctum and token abilities. App create supports optional Git for **custom** apps (SFTP-only), matching Cipi 4.4.4+.
- **MCP Server** — Model Context Protocol endpoint at `/mcp` for AI-powered integrations.
- **Swagger Docs** — Interactive API reference at `/docs`, generated from `public/api-docs/openapi.json`. The spec covers apps, aliases, deploy, SSL, databases (`GET /api/dbs` via `cipi db list`; other `/api/dbs/*` actions use jobs), and job polling (including structured `result` types per job).
- **Artisan Commands** — `cipi:token-create`, `cipi:token-list`, `cipi:token-revoke`.

## MCP Integration

The MCP server is exposed at `/mcp` using [Streamable HTTP](https://modelcontextprotocol.io/) transport and is secured with the same Sanctum token used by the REST API (the token must have the `mcp-access` ability).

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
| `AppCreate`         | Create a new app (`custom` for non-Laravel apps; optional Git for custom SFTP-only sites, Cipi 4.4.4+) |
| `AppEdit`           | Edit an existing app                                  |
| `AppDelete`         | Delete an app                                         |
| `AppDeploy`         | Deploy an app                                         |
| `AppDeployRollback` | Rollback the last deploy                              |
| `AppDeployUnlock`   | Unlock a stuck deploy                                 |
| `AliasList`         | List aliases for an app                               |
| `AliasAdd`          | Add an alias to an app                                |
| `AliasRemove`       | Remove an alias from an app                           |
| `DbList`            | List all databases with their sizes                   |
| `DbCreate`          | Create a new database with auto-generated credentials |
| `DbDelete`          | Permanently delete a database                         |
| `DbBackup`          | Create a compressed backup of a database              |
| `DbRestore`         | Restore a database from a backup file                 |
| `DbPassword`        | Regenerate database password and update `.env`        |
| `SslInstall`        | Install an SSL certificate for an app                 |

## Configuration

This package is automatically installed and configured by `cipi api`. No manual setup is needed.

The `CIPI_APPS_JSON` env variable defaults to `/etc/cipi/apps.json`.

`GET /api/dbs` runs **`sudo cipi db list`** on the host (synchronously), like the Cipi server CLI: vault and MariaDB access stay inside Cipi, not duplicated in PHP. The PHP user needs permission to run that command (typically passwordless `sudo` for `www-data`, same as for `cipi` jobs).

## License

MIT
