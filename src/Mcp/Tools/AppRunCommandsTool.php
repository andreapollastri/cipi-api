<?php

namespace CipiApi\Mcp\Tools;

use CipiApi\Services\CipiAppRunService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List whitelisted binaries for AppRun / POST /api/apps/{name}/run. Non-interactive only (no nano, vim, less, bash, tinker, …).')]
class AppRunCommandsTool extends Tool
{
    public function __construct(
        protected CipiAppRunService $runner,
    ) {}

    public function handle(Request $request): Response
    {
        $list = implode(', ', $this->runner->allowedCommands());

        return Response::text(
            "Allowed app run commands (non-interactive):\n{$list}\n\n"
            . "Excluded: editors (nano/vim), pagers (less/more), shells (bash/sh), REPLs (tinker), ssh/sudo, and interactive flags (tail -f, git -i, …)."
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
