<?php

namespace CipiApi\Mcp\Tools;

use CipiApi\Mcp\Support\McpArgValidator;
use CipiApi\Services\CipiValidationService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tool;

#[Description('Show details of a specific app. Returns domain, PHP version, branch, aliases, etc.')]
#[IsReadOnly]
class AppShowTool extends Tool
{
    public function __construct(
        protected CipiValidationService $validator,
    ) {}

    public function handle(Request $request): Response
    {
        [$name, $error] = McpArgValidator::requiredString($request, 'name');
        if ($error !== null) {
            return $error;
        }

        if (! $this->validator->appExists($name)) {
            return Response::text("Error: App '{$name}' not found");
        }
        $apps = $this->validator->getApps();
        $app = $apps[$name];
        $app['app'] = $name;
        $app['suspended'] = $this->validator->isSuspended($name);
        $app['basic_auth'] = $this->validator->isBasicAuthEnabled($name);
        $app['engine'] = $this->validator->getAppEngine($name);
        $app['octane'] = $this->validator->getAppOctane($name);
        $app['octane_port'] = $this->validator->getAppOctanePort($name);
        $app['www_redirect'] = $this->validator->getWwwRedirect($name);
        $app['force_https'] = $this->validator->isForceHttps($name);
        return Response::text(json_encode($app, JSON_PRETTY_PRINT));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('App name')->required(),
        ];
    }
}
