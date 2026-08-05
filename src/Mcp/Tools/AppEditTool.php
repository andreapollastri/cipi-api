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

#[Description('Edit an app (PHP, branch, repository, or primary domain). Validates app exists, PHP version, and domain format/uniqueness synchronously, dispatches async job. Returns job_id for polling.')]
class AppEditTool extends Tool
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

        $params = $this->validator->filterUnchangedAppEditFields($name, array_filter([
            'php' => $request->get('php'),
            'branch' => $request->get('branch'),
            'repository' => $request->get('repository'),
            'domain' => $request->get('domain'),
        ], fn ($v) => $v !== null && $v !== ''));
        if (empty($params)) {
            return Response::text('Error: Nothing to change. Provide a different php, branch, repository, or domain.');
        }
        if (isset($params['php'])) {
            if ($err = $this->validator->phpInstalledError($params['php'])) {
                return Response::text("Error: {$err}");
            }
        }
        if (isset($params['domain'])) {
            if ($err = $this->validator->domainError($params['domain'])) {
                return Response::text("Error: {$err}");
            }
            $usedBy = $this->validator->domainUsedBy($params['domain'], $name);
            if ($usedBy) {
                return Response::text("Error: Domain '{$params['domain']}' is already used by app '{$usedBy}'");
            }
        }

        $args = ['app edit', escapeshellarg($name)];
        foreach ($params as $k => $v) {
            $args[] = '--' . $k . '=' . escapeshellarg((string) $v);
        }

        $job = $this->jobs->dispatch('app-edit', implode(' ', $args), ['app' => $name] + $params);
        return Response::text("Job dispatched: {$job->id} (status: pending). Poll JobShow with id {$job->id} for result.");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('App name')->required(),
            'php' => $schema->string()->description('PHP version'),
            'branch' => $schema->string()->description('Branch'),
            'repository' => $schema->string()->description('Repository URL'),
            'domain' => $schema->string()->description('New primary domain (previous primary becomes an alias; promoting an existing alias works too). Requires Cipi 4.6.2+'),
        ];
    }
}
