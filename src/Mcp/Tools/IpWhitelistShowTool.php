<?php

namespace CipiApi\Mcp\Tools;

use CipiApi\Services\CipiIpWhitelistService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tool;

#[Description('Show the API/MCP client IP whitelist (same as GET /api/ip-whitelist). Default * = allow all. Requires Cipi CLI ≥ 5.0.8.')]
#[IsReadOnly]
class IpWhitelistShowTool extends Tool
{
    public function __construct(
        protected CipiIpWhitelistService $whitelist,
    ) {}

    public function handle(Request $request): Response
    {
        return Response::text(json_encode($this->whitelist->status(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
