<?php

namespace CipiApi\Services;

use CipiApi\Exceptions\MysqlDatabaseListingUnavailableException;

/**
 * Lists databases by running `sudo cipi db list` on the host — same path as the Cipi server CLI
 * (vault, MariaDB credentials, and output format are handled by Cipi, not duplicated in PHP).
 */
class CipiDatabaseListCliService
{
    public function __construct(
        protected CipiCliService $cli,
        protected CipiOutputParser $parser,
    ) {}

    /**
     * @return list<array{name: string, size?: string}>
     */
    public function list(): array
    {
        $result = $this->cli->run('db list');
        if ($result['exit_code'] !== 0) {
            $detail = trim($result['output'] ?? '');
            $msg = $detail !== '' ? $detail : ('cipi db list exited with code ' . $result['exit_code']);

            throw new MysqlDatabaseListingUnavailableException($msg);
        }

        $parsed = $this->parser->parse('db-list', $result['output'], true);
        $databases = is_array($parsed) ? ($parsed['databases'] ?? []) : [];

        return is_array($databases) ? $databases : [];
    }
}
