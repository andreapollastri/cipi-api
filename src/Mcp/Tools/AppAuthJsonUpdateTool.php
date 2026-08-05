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

#[Description('Replace shared auth.json content for an app. Pass content as a JSON string. Not HTTP Basic Auth. Secrets are redacted.')]
class AppAuthJsonUpdateTool extends Tool
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

        [$contentRaw, $error] = McpArgValidator::requiredString($request, 'content');
        if ($error !== null) {
            return $error;
        }

        $decoded = json_decode($contentRaw, true);
        if (! is_array($decoded)) {
            return Response::text('Error: content must be a valid JSON object or array');
        }

        try {
            $data = $this->authJson->update($name, $decoded);
            $json = json_encode($data['content'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';

            return Response::text(McpProductionContent::formatSensitiveResponse(
                "auth.json updated for '{$name}':\n\n{$json}"
            ));
        } catch (\InvalidArgumentException $e) {
            return Response::text('Error: ' . $e->getMessage());
        } catch (\RuntimeException $e) {
            return Response::text('Error: ' . $e->getMessage());
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('App name')->required(),
            'content' => $schema->string()->description('Full auth.json as a JSON string')->required(),
        ];
    }
}
