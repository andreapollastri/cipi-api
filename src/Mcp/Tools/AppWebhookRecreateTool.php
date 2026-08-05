<?php

namespace CipiApi\Mcp\Tools;

use CipiApi\Mcp\Support\McpArgValidator;
use CipiApi\Services\CipiJobService;
use CipiApi\Services\CipiValidationService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Recreate the GitHub/GitLab webhook for an app. Optionally rotate CIPI_WEBHOOK_TOKEN in apps.json and shared/.env. Requires Cipi CLI ≥ 5.0.6. Returns job_id for polling.')]
class AppWebhookRecreateTool extends Tool
{
    public function __construct(
        protected CipiJobService $jobs,
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

        $rotate = (bool) $request->get('rotate_secret', false);
        $command = 'app webhook recreate ' . escapeshellarg($name);
        if ($rotate) {
            $command .= ' --rotate-secret';
        }

        $job = $this->jobs->dispatch('app-webhook-recreate', $command, [
            'app' => $name,
            'rotate_secret' => $rotate,
        ]);

        return Response::text("Job dispatched: {$job->id} (status: pending). Poll JobShow with id {$job->id} for result.");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('App name')->required(),
            'rotate_secret' => $schema->boolean()->description('Rotate CIPI_WEBHOOK_TOKEN and update provider webhook secret'),
        ];
    }
}
