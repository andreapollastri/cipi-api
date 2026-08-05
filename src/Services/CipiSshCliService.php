<?php

namespace CipiApi\Services;

use CipiApi\Exceptions\MysqlDatabaseListingUnavailableException;

/**
 * SSH keys for the cipi user via `sudo cipi ssh …` (Cipi CLI ≥ 5.0.6 for --json).
 */
class CipiSshCliService
{
    public function __construct(
        protected CipiCliService $cli,
    ) {}

    /**
     * @return list<array{id: int, type: string, comment: string, fingerprint: string, current_session: bool}>
     */
    public function list(): array
    {
        $result = $this->cli->run('ssh list --json');
        if ($result['exit_code'] !== 0) {
            $detail = trim($result['output'] ?? '');
            $msg = $detail !== '' ? $detail : ('cipi ssh list exited with code ' . $result['exit_code']);

            throw new MysqlDatabaseListingUnavailableException($msg);
        }

        $decoded = json_decode(trim($result['output'] ?? ''), true);
        if (! is_array($decoded)) {
            throw new MysqlDatabaseListingUnavailableException('cipi ssh list --json returned invalid JSON');
        }

        $keys = [];
        foreach ($decoded['keys'] ?? [] as $row) {
            if (! is_array($row) || ! isset($row['id'])) {
                continue;
            }
            $keys[] = [
                'id' => (int) $row['id'],
                'type' => (string) ($row['type'] ?? ''),
                'comment' => (string) ($row['comment'] ?? ''),
                'fingerprint' => (string) ($row['fingerprint'] ?? ''),
                'current_session' => (bool) ($row['current_session'] ?? false),
            ];
        }

        return $keys;
    }

    public function add(string $key): array
    {
        $result = $this->cli->run('ssh add ' . escapeshellarg($key));
        if ($result['exit_code'] !== 0) {
            $detail = trim($result['output'] ?? '');

            throw new \RuntimeException($detail !== '' ? $detail : 'cipi ssh add failed');
        }

        return ['output' => trim($result['output'] ?? '')];
    }

    public function remove(int $id): array
    {
        $result = $this->cli->run('ssh remove ' . escapeshellarg((string) $id));
        if ($result['exit_code'] !== 0) {
            $detail = trim($result['output'] ?? '');

            throw new \RuntimeException($detail !== '' ? $detail : 'cipi ssh remove failed');
        }

        return ['output' => trim($result['output'] ?? '')];
    }
}
