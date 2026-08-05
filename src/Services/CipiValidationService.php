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
     * Whether a PHP version appears installed on this host (binary or FPM tree).
     *
     * Must stay open_basedir-safe: the API FPM pool only allows the app root,
     * /tmp, /etc/cipi, /proc, /usr/local/bin — not /usr/bin or /etc/php.
     * Bare is_file()/is_dir() on those paths emit E_WARNING which Laravel
     * converts to ErrorException → HTTP 500 "Server Error" on
     * POST /api/php/install and app create/edit PHP checks.
     */
    public function isPhpInstalled(string $version): bool
    {
        if (! preg_match('/^\d+\.\d+$/', $version)) {
            return false;
        }

        $bin = "/usr/bin/php{$version}";
        $dir = "/etc/php/{$version}";

        if ($this->pathWithinOpenBasedir($bin) && @is_file($bin)) {
            return true;
        }
        if ($this->pathWithinOpenBasedir($dir) && @is_dir($dir)) {
            return true;
        }

        // Shell test is not subject to PHP open_basedir (API FPM pool).
        $output = [];
        $code = 1;
        @exec(
            'test -e ' . escapeshellarg($bin) . ' -o -d ' . escapeshellarg($dir),
            $output,
            $code,
        );

        return $code === 0;
    }

    /**
     * Whether PHP may stat/read {@see $path} without triggering open_basedir errors.
     */
    protected function pathWithinOpenBasedir(string $path): bool
    {
        $basedir = ini_get('open_basedir');
        if (! is_string($basedir) || $basedir === '') {
            return true;
        }

        $path = str_replace('\\', '/', $path);
        foreach (explode(PATH_SEPARATOR, $basedir) as $allowed) {
            $allowed = rtrim(str_replace('\\', '/', $allowed), '/');
            if ($allowed === '') {
                continue;
            }
            if ($path === $allowed || str_starts_with($path, $allowed . '/')) {
                return true;
            }
        }

        return false;
    }

    public function phpInstalledError(?string $version): ?string
    {
        if ($version === null || $version === '') {
            return null;
        }
        if ($err = $this->phpVersionError($version)) {
            return $err;
        }
        if (! $this->isPhpInstalled($version)) {
            return "PHP {$version} is not installed on this server. Install it first (cipi php install {$version}).";
        }

        return null;
    }

    /**
     * Drop edit fields that match the current app state (avoids no-op CLI side effects).
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, string>
     */
    public function filterUnchangedAppEditFields(string $name, array $fields): array
    {
        $apps = $this->getApps();
        $current = $apps[$name] ?? [];
        $out = [];

        foreach ($fields as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $value = is_string($value) ? trim($value) : (string) $value;
            if ($value === '') {
                continue;
            }
            $cur = trim((string) ($current[$key] ?? ''));
            if ($value === $cur) {
                continue;
            }
            $out[$key] = $value;
        }

        return $out;
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

    /**
     * Octane server for an app (`frankenphp`), or null when the app uses PHP-FPM.
     */
    public function getAppOctane(string $name): ?string
    {
        $apps = $this->getApps();
        $octane = $apps[$name]['octane'] ?? null;
        if (! is_string($octane) || $octane === '') {
            return null;
        }

        return $this->normalizeOctane($octane) ?? $octane;
    }

    public function getAppOctanePort(string $name): ?int
    {
        $apps = $this->getApps();
        $port = $apps[$name]['octane_port'] ?? null;
        if ($port === null || $port === '') {
            return null;
        }
        if (! is_numeric($port)) {
            return null;
        }

        return (int) $port;
    }

    /**
     * Validate optional Octane flag/server. Accepts bool-ish true or `frankenphp`.
     */
    public function octaneError(mixed $octane): ?string
    {
        if ($octane === null || $octane === '' || $octane === false || $octane === 0 || $octane === '0') {
            return null;
        }
        if ($this->normalizeOctane($octane) === null) {
            $label = is_scalar($octane) ? (string) $octane : gettype($octane);

            return "Invalid octane '{$label}'. Allowed: true, frankenphp";
        }

        return null;
    }

    /**
     * Normalize API/CLI Octane values to `frankenphp`, or null when disabled.
     */
    public function normalizeOctane(mixed $octane): ?string
    {
        if ($octane === null || $octane === '' || $octane === false || $octane === 0 || $octane === '0' || $octane === 'false' || $octane === 'off' || $octane === 'no') {
            return null;
        }
        if ($octane === true || $octane === 1 || $octane === '1' || $octane === 'true' || $octane === 'on' || $octane === 'yes') {
            return 'frankenphp';
        }
        if (! is_string($octane)) {
            return null;
        }

        return match (strtolower(trim($octane))) {
            'frankenphp' => 'frankenphp',
            default => null,
        };
    }
}
