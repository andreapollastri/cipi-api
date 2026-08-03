<?php

namespace CipiApi\Services;

use CipiApi\Exceptions\AppsJsonUnreadableException;

/**
 * Structured snapshot matching `cipi status` sections (System, Resources, Services, PHP, Apps).
 * Prefers `sudo cipi status` (same source as the CLI); falls back to host reads as www-data.
 */
class CipiServerStatusService
{
    private const SERVICES = ['nginx', 'mariadb', 'supervisor', 'fail2ban'];

    public function __construct(
        protected CipiCliService $cli,
        protected CipiOutputParser $parser,
        protected CipiValidationService $validator,
    ) {}

    public function snapshot(): array
    {
        $fromCli = $this->snapshotFromCli();
        if ($fromCli !== null) {
            return $fromCli;
        }

        return $this->snapshotFromHost();
    }

    /**
     * @return array{system: array, resources: array, services: array, php: list<array>, apps: int}|null
     */
    protected function snapshotFromCli(): ?array
    {
        $result = $this->cli->run('status');
        if ($result['exit_code'] !== 0) {
            return null;
        }

        $parsed = $this->parser->parse('status', $result['output'], true);
        if (! is_array($parsed) || ! isset($parsed['system'])) {
            return null;
        }

        return $parsed;
    }

    /**
     * @return array{system: array, resources: array, services: array, php: list<array>, apps: int}
     */
    protected function snapshotFromHost(): array
    {
        return [
            'system' => $this->systemInfo(),
            'resources' => $this->resourcesInfo(),
            'services' => $this->servicesInfo(),
            'php' => $this->phpInfo(),
            'apps' => $this->appsCount(),
        ];
    }

    /**
     * @return array{ip: ?string, hostname: ?string, os: ?string, uptime: ?string, cipi: ?string}
     */
    protected function systemInfo(): array
    {
        return [
            'ip' => $this->publicIp(),
            'hostname' => $this->hostname(),
            'os' => $this->osVersion(),
            'uptime' => $this->uptime(),
            'cipi' => $this->cipiVersion(),
        ];
    }

    /**
     * @return array{cpu: array{usage_percent: int|null}, memory: array{used_mb: int, total_mb: int, usage_percent: int}|null, disk: array{display: string, used: string, total: string, usage_percent: int}|null}
     */
    protected function resourcesInfo(): array
    {
        return [
            'cpu' => ['usage_percent' => $this->cpuUsagePercent()],
            'memory' => $this->memoryStats(),
            'disk' => $this->diskStats(),
        ];
    }

    /**
     * @return array<string, 'running'|'stopped'>
     */
    protected function servicesInfo(): array
    {
        $services = self::SERVICES;
        // Match `cipi status`: include PostgreSQL only when the unit is installed (Cipi 4.8+).
        if ($this->serviceUnitExists('postgresql')) {
            $services[] = 'postgresql';
        }

        $status = [];
        foreach ($services as $service) {
            $status[$service] = $this->serviceIsRunning($service) ? 'running' : 'stopped';
        }

        return $status;
    }

    /**
     * @return list<array{version: string, status: 'running', pools: int}>
     */
    protected function phpInfo(): array
    {
        $running = [];
        foreach (config('cipi.php_versions', []) as $version) {
            if (! $this->serviceIsRunning("php{$version}-fpm")) {
                continue;
            }

            $running[] = [
                'version' => $version,
                'status' => 'running',
                'pools' => $this->phpPoolCount($version),
            ];
        }

        return $running;
    }

    protected function appsCount(): int
    {
        try {
            return count($this->validator->getApps());
        } catch (AppsJsonUnreadableException) {
            return 0;
        }
    }

    protected function publicIp(): ?string
    {
        $ip = trim((string) shell_exec('curl -s --max-time 5 https://checkip.amazonaws.com 2>/dev/null'));

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
    }

    protected function hostname(): ?string
    {
        $name = trim((string) shell_exec('hostname 2>/dev/null'));

        return $name !== '' ? $name : null;
    }

    protected function osVersion(): ?string
    {
        $lsb = trim((string) shell_exec('lsb_release -ds 2>/dev/null'));
        if ($lsb !== '') {
            return $lsb;
        }

        $release = @file_get_contents('/etc/os-release');
        if ($release !== false && preg_match('/^PRETTY_NAME="(.+)"$/m', $release, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    protected function uptime(): ?string
    {
        $uptime = trim((string) shell_exec('uptime -p 2>/dev/null'));

        return $uptime !== '' ? $uptime : null;
    }

    protected function cipiVersion(): ?string
    {
        $output = trim((string) shell_exec('/usr/local/bin/cipi version 2>/dev/null'));
        if ($output !== '' && preg_match('/v(\S+)/', $output, $m)) {
            return $m[1];
        }

        $file = @file_get_contents('/etc/cipi/version');

        return $file !== false ? trim($file) : null;
    }

    protected function cpuUsagePercent(): ?int
    {
        $cpu = trim((string) shell_exec(
            'top -bn1 2>/dev/null | grep -E "Cpu|CPU" | head -1 | awk \'{print $2}\' | cut -d. -f1',
        ));

        if ($cpu === '' || ! ctype_digit($cpu)) {
            return null;
        }

        return (int) $cpu;
    }

    /**
     * @return array{used_mb: int, total_mb: int, usage_percent: int}|null
     */
    protected function memoryStats(): ?array
    {
        $line = trim((string) shell_exec("free -m 2>/dev/null | awk '/^Mem:/{print $2, $3}'"));
        if ($line === '' || ! preg_match('/^(\d+)\s+(\d+)$/', $line, $m)) {
            return null;
        }

        $totalMb = (int) $m[1];
        $usedMb = (int) $m[2];
        if ($totalMb <= 0) {
            return null;
        }

        return [
            'used_mb' => $usedMb,
            'total_mb' => $totalMb,
            'usage_percent' => (int) ($usedMb * 100 / $totalMb),
        ];
    }

    /**
     * @return array{display: string, used: string, total: string, usage_percent: int}|null
     */
    protected function diskStats(): ?array
    {
        $display = trim((string) shell_exec(
            "df -h / 2>/dev/null | awk 'NR==2{print $3\"/\"$2\" (\"$5\")\"}'",
        ));
        if ($display === '') {
            return null;
        }

        $parts = trim((string) shell_exec(
            "df -h / 2>/dev/null | awk 'NR==2{print $3, $2, $5}'",
        ));
        if ($parts === '' || ! preg_match('/^(\S+)\s+(\S+)\s+(\d+)%/', $parts, $m)) {
            return ['display' => $display, 'used' => '', 'total' => '', 'usage_percent' => 0];
        }

        return [
            'display' => $display,
            'used' => $m[1],
            'total' => $m[2],
            'usage_percent' => (int) $m[3],
        ];
    }

    protected function serviceIsRunning(string $unit): bool
    {
        $state = trim((string) shell_exec('systemctl is-active ' . escapeshellarg($unit) . ' 2>/dev/null'));

        return $state === 'active';
    }

    protected function serviceUnitExists(string $unit): bool
    {
        $output = [];
        $code = 1;
        @exec('systemctl list-unit-files --quiet ' . escapeshellarg($unit . '.service') . ' 2>/dev/null', $output, $code);

        return $code === 0;
    }

    protected function phpPoolCount(string $version): int
    {
        $dir = "/etc/php/{$version}/fpm/pool.d";

        if ($this->pathWithinOpenBasedir($dir)) {
            if (@is_dir($dir) && @is_readable($dir)) {
                $files = @glob($dir . '/*.conf');

                return is_array($files) ? count($files) : 0;
            }
        }

        $count = trim((string) shell_exec(
            'ls ' . escapeshellarg($dir) . '/*.conf 2>/dev/null | wc -l',
        ));

        return $count !== '' && ctype_digit($count) ? (int) $count : 0;
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
}
