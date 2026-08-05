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

#[Description('Create shared auth.json for an app (default {"users":[]}). Pass force=true to overwrite. Not HTTP Basic Auth.')]
class AppAuthJsonCreateTool extends Tool
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

        $force = filter_var($request->get('force', false), FILTER_VALIDATE_BOOLEAN);

        try {
            $data = $this->authJson->create($name, $force);
            $json = json_encode($data['content'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';

            return Response::text(McpProductionContent::formatSensitiveResponse(
                "auth.json created for '{$name}':\n\n{$json}"
            ));
        } catch (\RuntimeException $e) {
            return Response::text('Error: ' . $e->getMessage());
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('App name')->required(),
            'force' => $schema->boolean()->description('Overwrite if auth.json already exists'),
        ];
    }
}
