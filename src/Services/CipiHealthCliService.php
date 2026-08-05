<?php

namespace CipiApi\Services;

use CipiApi\Exceptions\MysqlDatabaseListingUnavailableException;

/**
 * App HTTP healthchecks via `sudo cipi health …` (Cipi CLI ≥ 5.0.7 for --json).
 */
class CipiHealthCliService
{
    public function __construct(
        protected CipiCliService $cli,
        protected CipiValidationService $validator,
    ) {}

    /**
     * @return list<array{app: string, url: string, expect: int, state: ?string, failcount: int}>
     */
    public function list(): array
    {
        $result = $this->cli->run('health list --json');
        if ($result['exit_code'] !== 0) {
            $detail = trim($result['output'] ?? '');
            throw new MysqlDatabaseListingUnavailableException(
                $detail !== '' ? $detail : 'cipi health list failed',
            );
        }

        $decoded = json_decode(trim($result['output'] ?? ''), true);
        if (! is_array($decoded)) {
            throw new MysqlDatabaseListingUnavailableException('cipi health list --json returned invalid JSON');
        }

        $checks = [];
        foreach ($decoded['checks'] ?? [] as $row) {
            if (! is_array($row) || empty($row['app'])) {
                continue;
            }
            $checks[] = [
                'app' => (string) $row['app'],
                'url' => (string) ($row['url'] ?? ''),
                'expect' => (int) ($row['expect'] ?? 200),
                'state' => isset($row['state']) && is_string($row['state']) && $row['state'] !== ''
                    ? $row['state']
                    : null,
                'failcount' => (int) ($row['failcount'] ?? 0),
            ];
        }

        return $checks;
    }

    /**
     * @return array{enabled: bool, url: ?string, expect: ?int, state: ?string, failcount: int}
     */
    public function show(string $app): array
    {
        foreach ($this->list() as $check) {
            if ($check['app'] === $app) {
                return [
                    'enabled' => true,
                    'url' => $check['url'],
                    'expect' => $check['expect'],
                    'state' => $check['state'],
                    'failcount' => $check['failcount'],
                ];
            }
        }

        // Fallback: read from apps.json projection fields when list empty / not yet cron'd
        $apps = $this->validator->getApps();
        $row = $apps[$app] ?? [];
        $url = $row['health_url'] ?? null;
        if (is_string($url) && $url !== '') {
            return [
                'enabled' => true,
                'url' => $url,
                'expect' => isset($row['health_expect']) ? (int) $row['health_expect'] : 200,
                'state' => null,
                'failcount' => 0,
            ];
        }

        return [
            'enabled' => false,
            'url' => null,
            'expect' => null,
            'state' => null,
            'failcount' => 0,
        ];
    }

    public function set(string $app, ?string $url = null, int $expect = 200): array
    {
        $command = 'health set ' . escapeshellarg($app) . ' --expect=' . escapeshellarg((string) $expect);
        if ($url !== null && $url !== '') {
            $command .= ' --url=' . escapeshellarg($url);
        }

        $result = $this->cli->run($command);
        if ($result['exit_code'] !== 0) {
            $detail = trim($result['output'] ?? '');
            throw new \RuntimeException($detail !== '' ? $detail : 'cipi health set failed');
        }

        return $this->show($app);
    }

    public function clear(string $app): void
    {
        $result = $this->cli->run('health unset ' . escapeshellarg($app));
        if ($result['exit_code'] !== 0) {
            $detail = trim($result['output'] ?? '');
            throw new \RuntimeException($detail !== '' ? $detail : 'cipi health unset failed');
        }
    }

    /**
     * @return array{app: string, url: string, expect: int, got: string, ok: bool}
     */
    public function check(string $app): array
    {
        $result = $this->cli->run('health check ' . escapeshellarg($app) . ' --json');
        $decoded = json_decode(trim($result['output'] ?? ''), true);
        if (! is_array($decoded)) {
            $detail = trim($result['output'] ?? '');
            throw new \RuntimeException($detail !== '' ? $detail : 'cipi health check failed');
        }

        return [
            'app' => (string) ($decoded['app'] ?? $app),
            'url' => (string) ($decoded['url'] ?? ''),
            'expect' => (int) ($decoded['expect'] ?? 200),
            'got' => (string) ($decoded['got'] ?? '000'),
            'ok' => (bool) ($decoded['ok'] ?? false),
        ];
    }
}
