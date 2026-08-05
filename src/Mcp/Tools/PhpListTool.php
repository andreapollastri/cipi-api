<?php

namespace CipiApi\Mcp\Tools;

use CipiApi\Exceptions\MysqlDatabaseListingUnavailableException;
use CipiApi\Services\CipiPhpCliService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tool;

#[Description('List installed PHP versions on the server (same as GET /api/php). Requires Cipi CLI ≥ 5.0.6.')]
#[IsReadOnly]
class PhpListTool extends Tool
{
    public function __construct(
        protected CipiPhpCliService $phpCli,
    ) {}

    public function handle(Request $request): Response
    {
        try {
            return Response::text(json_encode($this->phpCli->list(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');
        } catch (MysqlDatabaseListingUnavailableException $e) {
            return Response::text('Error: ' . $e->getMessage());
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
