<?php

namespace CipiApi\Mcp\Tools;

use CipiApi\Mcp\Support\McpArgValidator;
use CipiApi\Mcp\Support\McpProductionContent;
use CipiApi\Services\CipiAppEnvService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Show Laravel app .env variables as JSON (cipi app env --show --json). Custom apps unsupported. Secrets are redacted.')]
class AppEnvShowTool extends Tool
{
    public function __construct(
        protected CipiAppEnvService $env,
    ) {}

    public function handle(Request $request): Response
    {
        [$name, $error] = McpArgValidator::requiredString($request, 'name');
        if ($error !== null) {
            return $error;
        }

        try {
            $data = $this->env->show($name);
            $json = json_encode($data['vars'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';

            return Response::text(McpProductionContent::formatSensitiveResponse(
                ".env for '{$name}':\n\n{$json}"
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
