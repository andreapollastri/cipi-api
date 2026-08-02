<?php

namespace CipiApi\Services;

use CipiApi\Exceptions\MysqlDatabaseListingUnavailableException;

/**
 * Lists installed DB engines via `sudo cipi db engines` (Cipi 4.8+).
 */
class CipiDatabaseEnginesCliService
{
    public function __construct(
        protected CipiCliService $cli,
        protected CipiOutputParser $parser,
    ) {}

    /**
     * @return array{default: string|null, engines: list<array{engine: string, status: string, port: int|null, default: bool}>}
     */
    public function list(): array
    {
        $result = $this->cli->run('db engines');
        if ($result['exit_code'] !== 0) {
            $detail = trim($result['output'] ?? '');
            $msg = $detail !== '' ? $detail : ('cipi db engines exited with code ' . $result['exit_code']);

            throw new MysqlDatabaseListingUnavailableException($msg);
        }

        $parsed = $this->parser->parse('db-engines', $result['output'], true);

        return [
            'default' => is_array($parsed) ? ($parsed['default'] ?? null) : null,
            'engines' => is_array($parsed) && is_array($parsed['engines'] ?? null) ? $parsed['engines'] : [],
        ];
    }
}
