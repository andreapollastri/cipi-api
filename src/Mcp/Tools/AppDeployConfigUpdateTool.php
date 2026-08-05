<?php

namespace CipiApi\Mcp\Tools;

use CipiApi\Mcp\Support\McpArgValidator;
use CipiApi\Services\CipiAppDeployConfigService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Update structured deploy.php options (JSON string of knobs). Regenerates deploy.php from template — safe alternative to editing PHP. Requires Cipi >= 5.0.3.')]
class AppDeployConfigUpdateTool extends Tool
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

        [$optionsRaw, $error] = McpArgValidator::requiredString($request, 'options');
        if ($error !== null) {
            return $error;
        }

        $options = json_decode($optionsRaw, true);
        if (! is_array($options)) {
            return Response::text('Error: options must be a JSON object, e.g. {"keep_releases":3,"migrate":false}');
        }

        try {
            $data = $this->deployConfig->update($name, $options);
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';

            return Response::text("Deploy config updated for '{$name}':\n\n{$json}");
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
            'options' => $schema->string()
                ->description('JSON object: keep_releases, migrate, optimize, storage_link, queue_restart, horizon_terminate, predeploy_snapshot, node_build, extra_artisan')
                ->required(),
        ];
    }
}
