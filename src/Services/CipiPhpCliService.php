<?php

namespace CipiApi\Services;

use CipiApi\Exceptions\MysqlDatabaseListingUnavailableException;

/**
 * PHP inventory via `sudo cipi php list --json` (Cipi CLI ≥ 5.0.6).
 */
class CipiPhpCliService
{
    public function __construct(
        protected CipiCliService $cli,
    ) {}

    /**
     * @return array{default: string|null, installable: list<string>, versions: list<array{version: string, status: string, apps: int, default: bool}>}
     */
    public function list(): array
    {
        $result = $this->cli->run('php list --json');
        if ($result['exit_code'] !== 0) {
            $detail = trim($result['output'] ?? '');
            $msg = $detail !== '' ? $detail : ('cipi php list exited with code ' . $result['exit_code']);

            throw new MysqlDatabaseListingUnavailableException($msg);
        }

        $decoded = json_decode(trim($result['output'] ?? ''), true);
        if (! is_array($decoded)) {
            throw new MysqlDatabaseListingUnavailableException('cipi php list --json returned invalid JSON');
        }

        $versions = [];
        foreach ($decoded['versions'] ?? [] as $row) {
            if (! is_array($row) || ! isset($row['version'])) {
                continue;
            }
            $versions[] = [
                'version' => (string) $row['version'],
                'status' => (string) ($row['status'] ?? 'unknown'),
                'apps' => (int) ($row['apps'] ?? 0),
                'default' => (bool) ($row['default'] ?? false),
            ];
        }

        $installable = $decoded['installable'] ?? config('cipi.php_versions', []);
        if (! is_array($installable)) {
            $installable = config('cipi.php_versions', []);
        }

        return [
            'default' => isset($decoded['default']) && is_string($decoded['default']) && $decoded['default'] !== ''
                ? $decoded['default']
                : null,
            'installable' => array_values(array_map('strval', $installable)),
            'versions' => $versions,
        ];
    }
}
