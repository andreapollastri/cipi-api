<?php

namespace CipiApi\Mcp\Tools;

use CipiApi\Mcp\Support\McpArgValidator;
use CipiApi\Mcp\Support\McpProductionContent;
use CipiApi\Services\CipiAppAuthJsonService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Show shared auth.json for an app (Composer credentials / structured JSON). Not HTTP Basic Auth. Secrets are redacted.')]
class AppAuthJsonShowTool extends Tool
{
    public function __construct(
        protected CipiAppAuthJsonService $authJson,
    ) {}

    public function handle(Request $request): Response
    {
        [$name, $error] = McpArgValidator::requiredString($request, 'name');
        if ($error !== null) {
            return $error;
        }

        try {
            $data = $this->authJson->show($name);
            $json = json_encode($data['content'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';

            return Response::text(McpProductionContent::formatSensitiveResponse(
                "auth.json for '{$name}':\n\n{$json}"
            ));
        } catch (\RuntimeException $e) {
            return Response::text('Error: ' . $e->getMessage());
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('App name')->required(),
        ];
    }
}
