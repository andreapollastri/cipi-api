<?php

namespace CipiApi\Mcp\Tools;

use CipiApi\Exceptions\MysqlDatabaseListingUnavailableException;
use CipiApi\Services\CipiDatabaseListCliService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List all MySQL/MariaDB databases via `cipi db list` on the server (same as GET /api/dbs).')]
class DbListTool extends Tool
{
    public function __construct(
        protected CipiDatabaseListCliService $dbListCli,
    ) {}

    public function handle(Request $request): Response
    {
        if (! $request->user()?->tokenCan('dbs-view')) {
            return Response::text('Permission denied: dbs-view required');
        }

        try {
            $rows = $this->dbListCli->list();
        } catch (MysqlDatabaseListingUnavailableException $e) {
            return Response::text('Error: ' . $e->getMessage());
        }

        if ($rows === []) {
            return Response::text('No databases found (excluding system schemas).');
        }

        $lines = array_map(
            fn (array $r) => ($r['name'] ?? '') . ' — ' . ($r['size'] ?? ''),
            $rows
        );

        return Response::text(implode("\n", $lines));
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
