<?php

namespace CipiApi\Services;

use CipiApi\Exceptions\MysqlDatabaseListingUnavailableException;

/**
 * Server SMTP config via `sudo cipi smtp …` (Cipi CLI ≥ 5.0.7).
 */
class CipiSmtpCliService
{
    public function __construct(
        protected CipiCliService $cli,
    ) {}

    /**
     * @return array{configured: bool, enabled: bool, host: ?string, port: ?string, user: ?string, from: ?string, to: ?string, tls: bool|null}
     */
    public function status(): array
    {
        $result = $this->cli->run('smtp status --json');
        if ($result['exit_code'] !== 0) {
            $detail = trim($result['output'] ?? '');
            throw new MysqlDatabaseListingUnavailableException(
                $detail !== '' ? $detail : 'cipi smtp status failed',
            );
        }

        $decoded = json_decode(trim($result['output'] ?? ''), true);
        if (! is_array($decoded)) {
            throw new MysqlDatabaseListingUnavailableException('cipi smtp status --json returned invalid JSON');
        }

        return [
            'configured' => (bool) ($decoded['configured'] ?? false),
            'enabled' => (bool) ($decoded['enabled'] ?? false),
            'host' => isset($decoded['host']) && is_string($decoded['host']) ? $decoded['host'] : null,
            'port' => isset($decoded['port']) ? (string) $decoded['port'] : null,
            'user' => isset($decoded['user']) && is_string($decoded['user']) ? $decoded['user'] : null,
            'from' => isset($decoded['from']) && is_string($decoded['from']) ? $decoded['from'] : null,
            'to' => isset($decoded['to']) && is_string($decoded['to']) ? $decoded['to'] : null,
            'tls' => array_key_exists('tls', $decoded) ? (bool) $decoded['tls'] : null,
        ];
    }

    /**
     * @param  array{host: string, port?: string|int, user: string, password: string, from: string, to: string, tls?: bool, enabled?: bool, test?: bool}  $config
     */
    public function configure(array $config): array
    {
        $parts = [
            'smtp configure',
            '--host=' . escapeshellarg($config['host']),
            '--port=' . escapeshellarg((string) ($config['port'] ?? '587')),
            '--user=' . escapeshellarg($config['user']),
            '--from=' . escapeshellarg($config['from']),
            '--to=' . escapeshellarg($config['to']),
            '--tls=' . escapeshellarg(! empty($config['tls']) ? 'on' : 'off'),
            '--enabled=' . escapeshellarg(($config['enabled'] ?? true) ? 'on' : 'off'),
        ];
        if (! empty($config['password'])) {
            $parts[] = '--password=' . escapeshellarg($config['password']);
        }
        if (($config['test'] ?? true) === false) {
            $parts[] = '--no-test';
        }

        $result = $this->cli->run(implode(' ', $parts));
        if ($result['exit_code'] !== 0) {
            $detail = trim($result['output'] ?? '');
            throw new \RuntimeException($detail !== '' ? $detail : 'cipi smtp configure failed');
        }

        return [
            'output' => trim($result['output'] ?? ''),
            'status' => $this->status(),
        ];
    }

    public function enable(): array
    {
        return $this->runSimple('smtp enable');
    }

    public function disable(): array
    {
        return $this->runSimple('smtp disable');
    }

    public function test(): array
    {
        return $this->runSimple('smtp test');
    }

    public function delete(): array
    {
        return $this->runSimple('smtp delete --force');
    }

    protected function runSimple(string $command): array
    {
        $result = $this->cli->run($command);
        if ($result['exit_code'] !== 0) {
            $detail = trim($result['output'] ?? '');
            throw new \RuntimeException($detail !== '' ? $detail : "{$command} failed");
        }

        return ['output' => trim($result['output'] ?? '')];
    }
}
