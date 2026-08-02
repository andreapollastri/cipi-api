<?php

namespace CipiApi\Mcp\Tools;

use CipiApi\Mcp\Support\McpArgValidator;
use CipiApi\Services\CipiJobService;
use CipiApi\Services\CipiValidationService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tool;

#[Description('Restore a database from a backup file. Optional engine=mariadb|pgsql (Cipi 4.8+). Dispatches async job.')]
#[IsDestructive]
class DbRestoreTool extends Tool
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

        [$file, $error] = McpArgValidator::requiredString($request, 'file');
        if ($error !== null) {
            return $error;
        }

        if (! preg_match('/^[a-zA-Z0-9_\-\/\.]+\.sql\.gz$/', $file ?? '')) {
            return Response::text('Error: Invalid backup file path. Must be a .sql.gz file with safe characters.');
        }

        $engineRaw = $request->get('engine');
        $engine = is_string($engineRaw) && $engineRaw !== '' ? $engineRaw : null;
        if ($err = $this->validator->engineError($engine)) {
            return Response::text("Error: {$err}");
        }
        if ($engine !== null) {
            $engine = $this->validator->normalizeEngine($engine);
        }

        $params = ['name' => $name, 'file' => $file];
        $command = 'db restore ' . escapeshellarg($name) . ' ' . escapeshellarg($file) . ' --force';
        if ($engine !== null) {
            $params['engine'] = $engine;
            $command .= ' --engine=' . escapeshellarg($engine);
        }
        $job = $this->jobs->dispatch('db-restore', $command, $params);

        return Response::text("Job dispatched: {$job->id} (status: pending). Poll JobShow with id {$job->id} for result.");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Database name to restore')->required(),
            'file' => $schema->string()->description('Path to backup file (.sql.gz)')->required(),
            'engine' => $schema->string()->description('Database engine: mariadb or pgsql'),
        ];
    }
}
