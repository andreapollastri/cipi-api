<?php

namespace CipiApi\Services;

use CipiApi\Exceptions\AppsJsonUnreadableException;

class CipiValidationService
{
    public function getApps(): array
    {
        $path = config('cipi.apps_json', '/etc/cipi/apps.json');

        $json = $this->readAppsJson($path);
        if ($json === null) {
            throw new AppsJsonUnreadableException($path);
        }

        return json_decode($json, true) ?: [];
    }

    protected function readAppsJson(string $path): ?string
    {
        if (! file_exists($path)) {
            return null;
        }

        if (is_readable($path)) {
            $content = file_get_contents($path);
            return $content !== false ? $content : null;
        }

        return $this->readViaSudo($path);
    }

    protected function readViaSudo(string $path): ?string
    {
        $escaped = escapeshellarg($path);
        $output = [];
        exec("sudo /bin/cat {$escaped} 2>/dev/null", $output, $exitCode);

        return $exitCode === 0 ? implode("\n", $output) : null;
    }

    public function appExists(string $name): bool
    {
        return array_key_exists($name, $this->getApps());
    }

    public function isValidUsername(string $name): bool
    {
        return $this->usernameError($name) === null;
    }

    public function usernameError(string $name): ?string
    {
        if (strlen($name) < 3 || strlen($name) > 32) {
            return 'Username must be 3-32 characters long';
        }
        if (! preg_match('/^[a-z][a-z0-9]*$/', $name)) {
            return 'Username must start with a lowercase letter and contain only lowercase letters and numbers';
        }
        $reserved = config('cipi.reserved_usernames', []);
        if (in_array($name, $reserved, true)) {
            return "Username '{$name}' is reserved by the system";
        }
        return null;
    }

    public function isValidDomain(string $domain): bool
    {
        return $this->domainError($domain) === null;
    }

    public function domainError(string $domain): ?string
    {
        if (strlen($domain) === 0) {
            return 'Domain is required';
        }
        if (strlen($domain) > 253) {
            return 'Domain must be at most 253 characters';
        }
        if (! preg_match(
            '/^[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*\.[a-zA-Z]{2,}$/',
            $domain
        )) {
            return "Invalid domain format '{$domain}'. Must be a valid FQDN (e.g. app.example.com)";
        }
        return null;
    }

    public function isValidPhpVersion(?string $version): bool
    {
        return $this->phpVersionError($version) === null;
    }

    public function phpVersionError(?string $version): ?string
    {
        if ($version === null || $version === '') {
            return null;
        }
        $allowed = config('cipi.php_versions', []);
        if (! in_array($version, $allowed, true)) {
            return "Invalid PHP version '{$version}'. Allowed: " . implode(', ', $allowed);
        }
        return null;
    }

    /**
     * Returns the app name using this domain, or null if free.
     */
    public function domainUsedBy(string $domain, ?string $excludeApp = null): ?string
    {
        foreach ($this->getApps() as $appName => $app) {
            if ($excludeApp && $appName === $excludeApp) {
                continue;
            }
            if (($app['domain'] ?? '') === $domain) {
                return $appName;
            }
            if (in_array($domain, $app['aliases'] ?? [], true)) {
                return $appName;
            }
        }
        return null;
    }

    public function getAppAliases(string $name): array
    {
        $apps = $this->getApps();
        return $apps[$name]['aliases'] ?? [];
    }

    public function getAppDomain(string $name): ?string
    {
        $apps = $this->getApps();
        return $apps[$name]['domain'] ?? null;
    }

    public function isSuspended(string $name): bool
    {
        $apps = $this->getApps();
        $value = $apps[$name]['suspended'] ?? false;

        return $value === true || $value === 'true' || $value === 1 || $value === '1';
    }

    public function isBasicAuthEnabled(string $name): bool
    {
        $apps = $this->getApps();

        return ($apps[$name]['basic_auth'] ?? '') === 'true';
    }

    public function isCustomApp(string $name): bool
    {
        $apps = $this->getApps();
        $value = $apps[$name]['custom'] ?? false;

        return $value === true || $value === 'true' || $value === 1 || $value === '1';
    }

    public function isForceHttps(string $name): bool
    {
        $apps = $this->getApps();
        $value = $apps[$name]['force_https'] ?? false;

        return $value === true || $value === 'true' || $value === 1 || $value === '1';
    }

    /**
     * Database engine for a Laravel app (`mariadb` / `pgsql`), or null when unset (e.g. custom apps).
     */
    public function getAppEngine(string $name): ?string
    {
        $apps = $this->getApps();
        $engine = $apps[$name]['engine'] ?? null;
        if (! is_string($engine) || $engine === '') {
            return null;
        }

        return $this->normalizeEngine($engine) ?? $engine;
    }

    /**
     * WWW redirect mode from apps.json: `to-root`, `from-root`, or null when none.
     */
    public function getWwwRedirect(string $name): ?string
    {
        $apps = $this->getApps();
        $mode = $apps[$name]['www_redirect'] ?? null;
        if ($mode === 'to-root' || $mode === 'from-root') {
            return $mode;
        }

        return null;
    }

    /**
     * Structured www/apex status for an app (derived from domain + www_redirect).
     *
     * @return array{app: string, primary: string, apex: string, www: string, redirect: string|null}
     */
    public function getWwwStatus(string $name): array
    {
        $primary = $this->getAppDomain($name) ?? '';
        [$apex, $www] = $this->resolveWwwPair($primary);

        return [
            'app' => $name,
            'primary' => $primary,
            'apex' => $apex,
            'www' => $www,
            'redirect' => $this->getWwwRedirect($name),
        ];
    }

    /**
     * @return array{0: string, 1: string} [apex, www]
     */
    public function resolveWwwPair(string $primary): array
    {
        if (str_starts_with(strtolower($primary), 'www.')) {
            $www = $primary;
            $apex = substr($primary, 4);

            return [$apex, $www];
        }

        return [$primary, $primary !== '' ? 'www.' . $primary : ''];
    }

    public function engineError(?string $engine): ?string
    {
        if ($engine === null || $engine === '') {
            return null;
        }
        if ($this->normalizeEngine($engine) === null) {
            return "Invalid engine '{$engine}'. Allowed: mariadb, pgsql";
        }

        return null;
    }

    /**
     * Normalize CLI/API engine aliases to `mariadb` or `pgsql`.
     */
    public function normalizeEngine(?string $engine): ?string
    {
        if ($engine === null || $engine === '') {
            return null;
        }

        return match (strtolower(trim($engine))) {
            'mariadb', 'mysql' => 'mariadb',
            'pgsql', 'postgres', 'postgresql' => 'pgsql',
            default => null,
        };
    }
}
