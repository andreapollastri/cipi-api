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

#[Description('Create a new database with auto-generated credentials. Optional engine=mariadb|pgsql (Cipi 4.8+). Dispatches async job. Returns job_id for polling.')]
class DbCreateTool extends Tool
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

        if ($err = $this->validator->usernameError($name ?? '')) {
            return Response::text("Error: {$err}");
        }

        $engineRaw = $request->get('engine');
        $engine = is_string($engineRaw) && $engineRaw !== '' ? $engineRaw : null;
        if ($err = $this->validator->engineError($engine)) {
            return Response::text("Error: {$err}");
        }
        if ($engine !== null) {
            $engine = $this->validator->normalizeEngine($engine);
        }

        $params = ['name' => $name];
        $command = 'db create --name=' . escapeshellarg($name);
        if ($engine !== null) {
            $params['engine'] = $engine;
            $command .= ' --engine=' . escapeshellarg($engine);
        }
        $job = $this->jobs->dispatch('db-create', $command, $params);

        return Response::text("Job dispatched: {$job->id} (status: pending). Poll JobShow with id {$job->id} for result.");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Database name (3-32 lowercase alphanumeric)')->required(),
            'engine' => $schema->string()->description('Database engine: mariadb or pgsql (server default when omitted). Requires Cipi 4.8+.'),
        ];
    }
}
