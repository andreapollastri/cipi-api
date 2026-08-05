<?php

namespace CipiApi\Mcp\Tools;

use CipiApi\Mcp\Support\McpArgValidator;
use CipiApi\Services\CipiAppDeployConfigService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Show structured deploy.php options for a Laravel app (keep_releases, migrate/optimize hooks, node_build, extra_artisan). Not raw PHP. Requires Cipi >= 5.0.3.')]
class AppDeployConfigShowTool extends Tool
{
    public function __construct(
        protected CipiAppDeployConfigService $deployConfig,
    ) {}

    public function handle(Request $request): Response
    {
        [$name, $error] = McpArgValidator::requiredString($request, 'name');
        if ($error !== null) {
            return $error;
        }

        try {
            $data = $this->deployConfig->show($name);
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';

            return Response::text("Deploy config for '{$name}':\n\n{$json}");
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
