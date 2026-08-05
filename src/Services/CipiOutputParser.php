<?php

namespace CipiApi\Services;

class CipiOutputParser
{
    public function parse(string $type, string $output, bool $success = true): ?array
    {
        $plain = $this->stripAnsi($output);

        $result = match ($type) {
            'app-create' => $this->parseAppCreate($plain),
            'app-edit' => $this->parseAppEdit($plain),
            'app-webhook-recreate' => $this->parseAppWebhookRecreate($plain),
            'app-delete' => $this->parseAppDelete($plain),
            'app-suspend' => $this->parseAppSuspend($plain),
            'app-unsuspend' => $this->parseAppUnsuspend($plain),
            'app-deploy' => $this->parseAppDeploy($plain),
            'app-deploy-rollback' => $this->parseAppDeployRollback($plain),
            'app-deploy-unlock' => $this->parseAppDeployUnlock($plain),
            'app-artisan' => $this->parseAppArtisan($plain, $success),
            'app-run' => $this->parseAppRun($plain, $success),
            'alias-create' => $this->parseAliasCreate($plain),
            'alias-delete' => $this->parseAliasDelete($plain),
            'www-add' => $this->parseWwwAdd($plain),
            'www-force-to-root' => $this->parseWwwForce($plain, 'to-root'),
            'www-force-from-root' => $this->parseWwwForce($plain, 'from-root'),
            'www-clear' => $this->parseWwwClear($plain),
            'ssl-install' => $this->parseSslInstall($plain),
            'ssl-force' => $this->parseSslForce($plain),
            'db-create' => $this->parseDbCreate($plain),
            'db-list' => $this->parseDbList($plain),
            'db-engines' => $this->parseDbEngines($plain),
            'db-delete' => $this->parseDbDelete($plain),
            'db-backup' => $this->parseDbBackup($plain),
            'db-restore' => $this->parseDbRestore($plain),
            'db-password' => $this->parseDbPassword($plain),
            'db-install' => $this->parseDbInstall($plain),
            'php-install' => $this->parsePhpInstall($plain),
            'php-remove' => $this->parsePhpRemove($plain),
            'service-restart' => $this->parseServiceRestart($plain),
            'basicauth-enable' => $this->parseBasicAuthEnable($plain),
            'basicauth-disable' => $this->parseBasicAuthDisable($plain),
            'basicauth-status' => $this->parseBasicAuthStatus($plain),
            'status' => $this->parseStatus($plain),
            default => null,
        };

        if (! $success && $result === null) {
            $result = ['error' => $this->extractErrorMessage($plain)];
        }

        return $result;
    }

    protected function extractErrorMessage(string $text): ?string
    {
        if (preg_match('/\[ERROR\]\s*(.+?)(?:\n|$)/', $text, $m)) {
            return trim($m[1]);
        }
        $lines = array_filter(explode("\n", $text), fn ($l) => trim($l) !== '');
        $last = end($lines);
        return $last ? trim($last) : null;
    }

    public function stripAnsi(string $text): string
    {
        return preg_replace('/\x1b\[[0-9;]*m/', '', $text);
    }

    protected function extractLabel(string $text, string $label): ?string
    {
        if (preg_match('/' . preg_quote($label, '/') . '\s*([^\n]+)/', $text, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    protected function parseAppCreate(string $text): ?array
    {
        $app = null;
        if (preg_match('/APP\s+CREATED:\s*(\S+)/', $text, $m)) {
            $app = trim($m[1]);
        }
        if (! $app) {
            return null;
        }

        $domain = $this->extractLabel($text, 'Domain:');
        $php = $this->extractLabel($text, 'PHP:');
        $home = $this->extractLabel($text, 'Home:');

        $sshUser = $sshPassword = null;
        if (preg_match('/SSH\s+(\S+)\s*\/\s*(\S+)/', $text, $m)) {
            $sshUser = $m[1];
            $sshPassword = $m[2];
        }

        $dbUser = $dbPassword = null;
        if (preg_match('/Database\s+(\S+)\s*\/\s*(\S+)/', $text, $m)) {
            $dbUser = $m[1];
            $dbPassword = $m[2];
        }

        $deployKey = null;
        if (preg_match('/(ssh-(?:ed25519|rsa)\s+[A-Za-z0-9+\/=]+\s+\S+)/', $text, $m)) {
            $deployKey = trim($m[1]);
        }

        $webhook = $this->extractLabel($text, 'Webhook');
        if (! $webhook && $domain) {
            $webhook = "https://{$domain}/cipi/webhook";
        }

        $token = $this->extractLabel($text, 'Token');

        return array_filter([
            'app' => $app,
            'domain' => $domain,
            'php' => $php,
            'home' => $home,
            'ssh' => ($sshUser || $sshPassword) ? [
                'user' => $sshUser,
                'password' => $sshPassword,
            ] : null,
            'database' => ($dbUser || $dbPassword) ? [
                'user' => $dbUser,
                'password' => $dbPassword,
            ] : null,
            'deploy_key' => $deployKey,
            'webhook' => $webhook,
            'webhook_token' => $token,
        ], fn ($v) => $v !== null);
    }

    protected function parseAppEdit(string $text): ?array
    {
        $changes = [];
        if (preg_match_all('/✓\s+(.+?)(?:\n|$)/', $text, $matches)) {
            foreach ($matches[1] as $m) {
                $m = trim($m);
                if (preg_match('/^(\w+)\s*→\s*(.+)$/', $m, $c)) {
                    $changes[strtolower(trim($c[1]))] = trim($c[2]);
                } elseif ($m === 'Repository updated') {
                    $changes['repository'] = 'updated';
                }
            }
        }
        if (str_contains($text, 'Nothing changed')) {
            return ['changes' => [], 'message' => 'Nothing changed'];
        }
        return ! empty($changes) ? ['changes' => $changes] : null;
    }

    protected function parseAppWebhookRecreate(string $text): ?array
    {
        $url = $this->extractLabel($text, 'WEBHOOK_URL:');
        $token = $this->extractLabel($text, 'WEBHOOK_TOKEN:');
        $id = $this->extractLabel($text, 'WEBHOOK_ID:');
        $rotated = $this->extractLabel($text, 'WEBHOOK_ROTATED:');

        if ($url === null && $token === null && ! str_contains($text, 'Webhook recreated')) {
            return null;
        }

        return array_filter([
            'webhook_url' => $url,
            'webhook_token' => $token,
            'webhook_id' => $id,
            'rotated' => $rotated === 'true' || $rotated === '1',
            'recreated' => true,
        ], fn ($v) => $v !== null);
    }

    protected function parseDbInstall(string $text): ?array
    {
        if (preg_match('/(MariaDB|PostgreSQL)\s+installed/i', $text, $m)) {
            return ['engine' => str_contains(strtolower($m[1]), 'postgre') ? 'pgsql' : 'mariadb', 'installed' => true];
        }
        if (preg_match('/already installed/i', $text)) {
            return ['installed' => true, 'message' => 'Already installed'];
        }

        return null;
    }

    protected function parsePhpInstall(string $text): ?array
    {
        if (preg_match('/PHP\s+(\d+\.\d+)\s+installed/i', $text, $m)) {
            return ['version' => $m[1], 'installed' => true];
        }
        if (preg_match('/PHP\s+(\d+\.\d+)\s+already installed/i', $text, $m)) {
            return ['version' => $m[1], 'installed' => true, 'message' => 'Already installed'];
        }

        return null;
    }

    protected function parsePhpRemove(string $text): ?array
    {
        if (preg_match('/PHP\s+(\d+\.\d+)\s+removed/i', $text, $m)) {
            return ['version' => $m[1], 'removed' => true];
        }

        return null;
    }

    protected function parseServiceRestart(string $text): ?array
    {
        $restarted = [];
        if (preg_match_all('/✓\s+(\S+)\s+(?:restarted|reloaded)/i', $text, $m)) {
            $restarted = $m[1];
        }
        if ($restarted === [] && preg_match('/(\S+)\s+(?:restarted|reloaded)/i', $text, $m)) {
            $restarted = [$m[1]];
        }

        return $restarted !== [] ? ['restarted' => array_values($restarted)] : null;
    }

    protected function parseAppDelete(string $text): ?array
    {
        if (preg_match("/'([^']+)'\s+deleted/", $text, $m)) {
            return ['app' => $m[1], 'deleted' => true];
        }
        return null;
    }

    protected function parseAppSuspend(string $text): ?array
    {
        if (preg_match('/unsuspend/i', $text)) {
            return null;
        }
        if (preg_match('/suspend(?:ed)?/i', $text)) {
            $app = null;
            if (preg_match("/'([^']+)'\s+suspended/i", $text, $m)) {
                $app = $m[1];
            }
            return array_filter([
                'app' => $app,
                'suspended' => true,
            ], fn ($v) => $v !== null);
        }
        return null;
    }

    protected function parseAppUnsuspend(string $text): ?array
    {
        if (preg_match('/unsuspend(?:ed)?/i', $text) || preg_match('/restored/i', $text)) {
            $app = null;
            if (preg_match("/'([^']+)'\s+(?:unsuspended|restored)/i", $text, $m)) {
                $app = $m[1];
            }
            return array_filter([
                'app' => $app,
                'suspended' => false,
            ], fn ($v) => $v !== null);
        }
        return null;
    }

    protected function parseAppArtisan(string $text, bool $success): array
    {
        return [
            'success' => $success,
            'output_preview' => mb_substr(trim($text), 0, 500),
        ];
    }

    protected function parseAppRun(string $text, bool $success): array
    {
        return [
            'success' => $success,
            'output_preview' => mb_substr(trim($text), 0, 500),
        ];
    }

    protected function parseAppDeploy(string $text): ?array
    {
        if (preg_match('/deployed\s+successfully/i', $text)) {
            $app = null;
            if (preg_match("/'([^']+)'\s+deployed/i", $text, $m)) {
                $app = $m[1];
            }

            return array_filter([
                'app' => $app,
                'deployed' => true,
            ], fn ($v) => $v !== null);
        }

        return null;
    }

    protected function parseAppDeployRollback(string $text): ?array
    {
        if (preg_match('/rollback\s+completed/i', $text) || preg_match('/rolled\s+back/i', $text)) {
            $app = null;
            if (preg_match("/'([^']+)'\s+rolled\s+back/i", $text, $m)) {
                $app = $m[1];
            }

            return array_filter([
                'app' => $app,
                'rolled_back' => true,
            ], fn ($v) => $v !== null);
        }

        return null;
    }

    protected function parseAppDeployUnlock(string $text): ?array
    {
        if (preg_match('/deploy\s+unlocked/i', $text) || preg_match('/unlock\s+completed/i', $text)) {
            $app = null;
            if (preg_match("/'([^']+)'\s+(?:deploy\s+)?unlocked/i", $text, $m)) {
                $app = $m[1];
            }

            return array_filter([
                'app' => $app,
                'unlocked' => true,
            ], fn ($v) => $v !== null);
        }

        return null;
    }

    protected function parseAliasCreate(string $text): ?array
    {
        if (preg_match("/'([^']+)'\s+added\s+to\s+'([^']+)'/", $text, $m)) {
            return ['alias' => $m[1], 'app' => $m[2]];
        }
        return null;
    }

    protected function parseAliasDelete(string $text): ?array
    {
        if (preg_match("/'([^']+)'\s+removed\s+from\s+'([^']+)'/", $text, $m)) {
            return ['alias' => $m[1], 'app' => $m[2]];
        }
        return null;
    }

    protected function parseWwwAdd(string $text): ?array
    {
        if (preg_match("/'([^']+)'\s+added\s+to\s+'([^']+)'/", $text, $m)) {
            return ['alias' => $m[1], 'app' => $m[2]];
        }
        if (preg_match("/'([^']+)'\s+is already configured for\s+'([^']+)'/", $text, $m)) {
            return ['alias' => $m[1], 'app' => $m[2], 'already_configured' => true];
        }

        return null;
    }

    protected function parseWwwForce(string $text, string $mode): ?array
    {
        if (preg_match('/WWW\s+redirect:\s*(\S+)\s*→\s*(\S+)/u', $text, $m)
            || preg_match('/WWW\s+redirect:\s*(\S+)\s*->\s*(\S+)/', $text, $m)) {
            return [
                'redirect' => $mode,
                'from' => trim($m[1]),
                'to' => trim($m[2]),
            ];
        }

        return null;
    }

    protected function parseWwwClear(string $text): ?array
    {
        if (preg_match("/WWW\s+redirect\s+cleared\s+for\s+'([^']+)'/i", $text, $m)) {
            return ['app' => $m[1], 'redirect' => null, 'cleared' => true];
        }
        if (preg_match("/No www redirect configured for\s+'([^']+)'/i", $text, $m)) {
            return ['app' => $m[1], 'redirect' => null, 'cleared' => false];
        }

        return null;
    }

    protected function parseSslInstall(string $text): ?array
    {
        if (preg_match('/SSL\s+installed\s+for\s+(\S+)/', $text, $m)) {
            return ['domain' => trim($m[1]), 'installed' => true, 'force_https' => true];
        }
        return null;
    }

    protected function parseSslForce(string $text): ?array
    {
        if (preg_match('/HTTP\s*→\s*HTTPS\s+redirect\s+enabled\s+for\s+(\S+)/u', $text, $m)
            || preg_match('/HTTP\s*->\s*HTTPS\s+redirect\s+enabled\s+for\s+(\S+)/', $text, $m)
            || preg_match('/redirect\s+enabled\s+for\s+(\S+)/i', $text, $m)) {
            return ['domain' => trim($m[1]), 'force_https' => true];
        }

        return null;
    }

    protected function parseDbCreate(string $text): ?array
    {
        $engine = $this->extractLabel($text, 'Engine:');
        $name = $this->extractLabel($text, 'Database:') ?? $this->extractLabel($text, 'Name:');
        $user = $this->extractLabel($text, 'Username:') ?? $this->extractLabel($text, 'User:');
        $password = $this->extractLabel($text, 'Password:');
        $url = $this->extractLabel($text, 'URL:');

        // Cipi 4.8 one-liner: Engine: x  Database: y  User: z  Password: p
        if ((! $name || ! $user) && preg_match(
            '/Engine:\s*(\S+)\s+Database:\s*(\S+)\s+User:\s*(\S+)\s+Password:\s*(\S+)/',
            $text,
            $m
        )) {
            $engine = $m[1];
            $name = $m[2];
            $user = $m[3];
            $password = $m[4];
        }

        if (! $name && ! $user) {
            return null;
        }

        return array_filter([
            'engine' => $engine,
            'database' => $name,
            'user' => $user,
            'password' => $password,
            'url' => $url,
        ], fn ($v) => $v !== null);
    }

    protected function parseDbList(string $text): ?array
    {
        $databases = [];
        $lines = explode("\n", $text);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '─') || str_starts_with($line, '=') || str_starts_with($line, '━')) {
                continue;
            }
            if (preg_match('/^(ENGINE|DATABASE|Databases|Name)\b/i', $line) || (str_contains($line, 'DATABASE') && str_contains($line, 'ENGINE'))) {
                continue;
            }
            // Cipi 4.8+: ENGINE DATABASE USER SIZE
            if (preg_match('/^(mariadb|pgsql)\s+(\S+)\s+(\S+)\s+(.+)$/i', $line, $m)) {
                $databases[] = [
                    'engine' => strtolower(trim($m[1])),
                    'name' => trim($m[2]),
                    'user' => trim($m[3]),
                    'size' => trim($m[4]),
                ];
                continue;
            }
            // Legacy: NAME SIZE
            if (preg_match('/^(\S+)\s+(.+)$/', $line, $m)) {
                $databases[] = ['name' => trim($m[1]), 'size' => trim($m[2])];
            } elseif (preg_match('/^(\S+)$/', $line, $m)) {
                $databases[] = ['name' => trim($m[1])];
            }
        }

        return ['databases' => $databases];
    }

    protected function parseDbEngines(string $text): ?array
    {
        $engines = [];
        $default = null;
        $lines = explode("\n", $text);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || preg_match('/^ENGINE\b/i', $line) || str_starts_with($line, 'Install:') || str_starts_with($line, 'Default:')) {
                continue;
            }
            if (preg_match('/^(mariadb|pgsql)\s+(\S+)\s+(\S+)(?:\s+(\*))?/i', $line, $m)) {
                $isDefault = ($m[4] ?? '') === '*';
                $port = is_numeric($m[3]) ? (int) $m[3] : null;
                $engines[] = [
                    'engine' => strtolower($m[1]),
                    'status' => $m[2] === '—' ? 'not_installed' : strtolower($m[2]),
                    'port' => $port,
                    'default' => $isDefault,
                ];
                if ($isDefault) {
                    $default = strtolower($m[1]);
                }
            }
        }

        if ($engines === []) {
            return null;
        }

        return [
            'default' => $default,
            'engines' => $engines,
        ];
    }

    protected function parseDbDelete(string $text): ?array
    {
        if (preg_match("/'([^']+)'\s+deleted\s*\(([^)]+)\)/i", $text, $m)) {
            return ['database' => $m[1], 'engine' => trim($m[2]), 'deleted' => true];
        }
        if (preg_match("/'([^']+)'\s+deleted/i", $text, $m)) {
            return ['database' => $m[1], 'deleted' => true];
        }
        if (preg_match('/deleted\s+(?:database\s+)?(\S+)/i', $text, $m)) {
            return ['database' => trim($m[1]), 'deleted' => true];
        }
        return null;
    }

    protected function parseDbBackup(string $text): ?array
    {
        $file = $this->extractLabel($text, 'Backup:') ?? $this->extractLabel($text, 'File:');
        if (! $file && preg_match('/([\/\w\-\.]+\.sql\.gz)/', $text, $m)) {
            $file = trim($m[1]);
        }

        if ($file) {
            return ['file' => $file];
        }

        if (preg_match('/backup\s+completed/i', $text)) {
            return ['backed_up' => true];
        }

        return null;
    }

    protected function parseDbRestore(string $text): ?array
    {
        if (preg_match('/restore[d]?\s+(?:completed|successfully)/i', $text)) {
            $name = null;
            if (preg_match("/'([^']+)'\s+restored/i", $text, $m)) {
                $name = $m[1];
            }
            return array_filter([
                'database' => $name,
                'restored' => true,
            ], fn ($v) => $v !== null);
        }
        return null;
    }

    protected function parseBasicAuthEnable(string $text): ?array
    {
        if (! preg_match('/Basic auth.*enabled/i', $text)) {
            return null;
        }

        $user = $this->extractLabel($text, 'User');
        $password = $this->extractLabel($text, 'Password');
        $users = $user ? [$user] : [];

        return array_filter([
            'enabled' => true,
            'user' => $user,
            'password' => $password,
            'users' => $users,
        ], fn ($v) => $v !== null && $v !== []);
    }

    protected function parseBasicAuthDisable(string $text): ?array
    {
        if (preg_match('/Basic auth.*disabled/i', $text)) {
            return ['enabled' => false, 'users' => []];
        }

        return null;
    }

    protected function parseBasicAuthStatus(string $text): ?array
    {
        $enabled = null;
        if (preg_match('/Status\s+enabled/i', $text)) {
            $enabled = true;
        } elseif (preg_match('/Status\s+disabled/i', $text)) {
            $enabled = false;
        }

        $users = [];
        if (preg_match('/Users\s+([^\n]+)/', $text, $m)) {
            $users = array_values(array_filter(array_map('trim', explode(',', trim($m[1])))));
        }

        if ($enabled === null && empty($users)) {
            return null;
        }

        return [
            'enabled' => $enabled ?? false,
            'users' => $users,
        ];
    }

    protected function parseStatus(string $text): ?array
    {
        $cipi = $this->extractLabel($text, 'Cipi');
        $system = [
            'ip' => $this->extractLabel($text, 'IP'),
            'hostname' => $this->extractLabel($text, 'Hostname'),
            'os' => $this->extractLabel($text, 'OS'),
            'uptime' => $this->extractLabel($text, 'Uptime'),
            'cipi' => $cipi !== null ? ltrim($cipi, 'vV') : null,
        ];

        if ($system['ip'] === null && $system['hostname'] === null && $system['os'] === null) {
            return null;
        }

        $cpuPercent = null;
        if (preg_match('/CPU\s+(\d+)%/', $text, $m)) {
            $cpuPercent = (int) $m[1];
        }

        $memory = null;
        if (preg_match('/Memory\s+(\d+)MB\s*\/\s*(\d+)MB\s*\((\d+)%\)/', $text, $m)) {
            $memory = [
                'used_mb' => (int) $m[1],
                'total_mb' => (int) $m[2],
                'usage_percent' => (int) $m[3],
            ];
        }

        $disk = null;
        if (preg_match('/Disk\s+(\S+)\/(\S+)\s*\((\d+)%\)/', $text, $m)) {
            $disk = [
                'display' => "{$m[1]}/{$m[2]} ({$m[3]}%)",
                'used' => $m[1],
                'total' => $m[2],
                'usage_percent' => (int) $m[3],
            ];
        }

        $services = [];
        if (preg_match('/Services\s*\n(.*?)(?:\n\n|\nPHP|\nApps:)/s', $text, $section)) {
            foreach (explode("\n", $section[1]) as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                if (preg_match('/^([a-z0-9.-]+)\s+\S*\s*(running|stopped)$/i', $line, $m)) {
                    $services[$m[1]] = strtolower($m[2]);
                }
            }
        }

        $php = [];
        if (preg_match_all('/PHP\s+([\d.]+)\s+\S*\s*running\s*\((\d+)\s+pools\)/i', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $php[] = [
                    'version' => $m[1],
                    'status' => 'running',
                    'pools' => (int) $m[2],
                ];
            }
        }

        $apps = 0;
        if (preg_match('/Apps:\s*(\d+)/', $text, $m)) {
            $apps = (int) $m[1];
        }

        return [
            'system' => $system,
            'resources' => [
                'cpu' => ['usage_percent' => $cpuPercent],
                'memory' => $memory,
                'disk' => $disk,
            ],
            'services' => $services,
            'php' => $php,
            'apps' => $apps,
        ];
    }

    protected function parseDbPassword(string $text): ?array
    {
        $password = $this->extractLabel($text, 'Password:') ?? $this->extractLabel($text, 'New password:');
        $user = $this->extractLabel($text, 'Username:') ?? $this->extractLabel($text, 'User:');
        $engine = null;

        // Cipi 4.8: New password for 'user' (engine): pass
        if (preg_match("/New password for\s+'([^']+)'\s*\(([^)]+)\):\s*(\S+)/i", $text, $m)) {
            $user = $m[1];
            $engine = trim($m[2]);
            $password = $m[3];
        }

        if ($password) {
            return array_filter([
                'engine' => $engine,
                'user' => $user,
                'password' => $password,
            ], fn ($v) => $v !== null);
        }

        if (preg_match('/password\s+(?:updated|changed|regenerated)/i', $text)) {
            return ['password_changed' => true];
        }

        return null;
    }
}
