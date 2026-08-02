<?php

namespace CipiApi\Mcp\Tools;

use CipiApi\Exceptions\MysqlDatabaseListingUnavailableException;
use CipiApi\Services\CipiDatabaseEnginesCliService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tool;

#[Description('List installed database engines (MariaDB / PostgreSQL), ports, and server default. Requires Cipi 4.8+.')]
#[IsReadOnly]
class DbEnginesTool extends Tool
{
    public function __construct(
        protected CipiDatabaseEnginesCliService $enginesCli,
    ) {}

    public function handle(Request $request): Response
    {
        try {
            $data = $this->enginesCli->list();
        } catch (MysqlDatabaseListingUnavailableException $e) {
            return Response::text('Error: ' . $e->getMessage());
        }

        return Response::text(json_encode($data, JSON_PRETTY_PRINT));
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
