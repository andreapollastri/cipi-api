<?php

namespace CipiApi\Mcp\Tools;

use CipiApi\Mcp\Support\McpArgValidator;
use CipiApi\Services\CipiAppRunService;
use CipiApi\Services\CipiJobService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Run a whitelisted non-interactive command as the app user (composer, npm, ls, rm, git, …). Async job — poll JobShow. No editors/shells/REPLs. Requires Cipi >= 5.0.3.')]
class AppRunTool extends Tool
{
    public function __construct(
        protected CipiAppRunService $runner,
        protected CipiJobService $jobs,
    ) {}

    public function handle(Request $request): Response
    {
        [$name, $error] = McpArgValidator::requiredString($request, 'name');
        if ($error !== null) {
            return $error;
        }

        [$command, $error] = McpArgValidator::requiredString($request, 'command');
        if ($error !== null) {
            return $error;
        }

        try {
            $this->runner->assertApp($name);
            $this->runner->validateCommand($command);
            $cipi = $this->runner->buildCipiCommand($name, $command);
        } catch (\InvalidArgumentException $e) {
            return Response::text('Error: ' . $e->getMessage());
        } catch (\RuntimeException $e) {
            return Response::text('Error: ' . $e->getMessage());
        }

        $job = $this->jobs->dispatch('app-run', $cipi, [
            'app' => $name,
            'command' => trim($command),
        ]);

        return Response::text(
            "Job dispatched: {$job->id} (status: pending). Poll JobShow with id {$job->id} for output. Allowed binaries: "
            . implode(', ', $this->runner->allowedCommands())
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('App name')->required(),
            'command' => $schema->string()
                ->description('Whitelisted command + args, e.g. "composer install --no-dev", "ls -la", "npm run build"')
                ->required(),
        ];
    }
}
