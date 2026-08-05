<?php

namespace CipiApi\Services;

use CipiApi\Exceptions\MysqlDatabaseListingUnavailableException;

/**
 * System services via `sudo cipi service list --json` (Cipi CLI ≥ 5.0.6).
 */
class CipiServicesCliService
{
    public function __construct(
        protected CipiCliService $cli,
    ) {}

    /**
     * @return list<array{name: string, status: string, since: string|null}>
     */
    public function list(?string $service = null): array
    {
        // Always `service list --json` (one trailing arg) — sudo-rs only allows `*` as the final token.
        $result = $this->cli->run('service list --json');
        if ($result['exit_code'] !== 0) {
            $detail = trim($result['output'] ?? '');
            $msg = $detail !== '' ? $detail : ('cipi service list exited with code ' . $result['exit_code']);

            throw new MysqlDatabaseListingUnavailableException($msg);
        }

        $decoded = json_decode(trim($result['output'] ?? ''), true);
        if (! is_array($decoded)) {
            throw new MysqlDatabaseListingUnavailableException('cipi service list --json returned invalid JSON');
        }

        if ($service !== null && $service !== '') {
            if (! preg_match('/^[a-z0-9.-]+$/', $service)) {
                throw new \InvalidArgumentException('Invalid service name');
            }
        }

        $services = [];
        foreach ($decoded['services'] ?? [] as $row) {
            if (! is_array($row) || ! isset($row['name'])) {
                continue;
            }
            $name = (string) $row['name'];
            if ($service !== null && $service !== '' && $name !== $service) {
                continue;
            }
            $services[] = [
                'name' => $name,
                'status' => (string) ($row['status'] ?? 'unknown'),
                'since' => isset($row['since']) && is_string($row['since']) && $row['since'] !== ''
                    ? $row['since']
                    : null,
            ];
        }

        return $services;
    }
}
