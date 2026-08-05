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

#[Description('Create a new app. Validates domain/username/PHP synchronously and dispatches async job. Returns job_id for polling.')]
class AppCreateTool extends Tool
{
    public function __construct(
        protected CipiJobService $jobs,
        protected CipiValidationService $validator,
    ) {}

    public function handle(Request $request): Response
    {
        [$user, $error] = McpArgValidator::requiredString($request, 'user');
        if ($error !== null) {
            return $error;
        }
        [$domain, $error] = McpArgValidator::requiredString($request, 'domain');
        if ($error !== null) {
            return $error;
        }

        $repositoryRaw = $request->get('repository');
        $repository = is_string($repositoryRaw) ? trim($repositoryRaw) : $repositoryRaw;
        if ($repository === '') {
            $repository = null;
        }
        $custom = (bool) $request->get('custom', false);
        $hasRepo = $repository !== null && $repository !== '';
        $branch = $request->get('branch');
        if ($branch === null && $hasRepo) {
            $branch = 'main';
        }
        $php = $request->get('php', '8.4');
        $docroot = $request->get('docroot');
        $engineRaw = $request->get('engine');
        $engine = is_string($engineRaw) && $engineRaw !== '' ? $engineRaw : null;
        $octaneRaw = $request->get('octane');

        if ($err = $this->validator->usernameError($user ?? '')) {
            return Response::text("Error: {$err}");
        }
        if ($err = $this->validator->domainError($domain ?? '')) {
            return Response::text("Error: {$err}");
        }
        if ($err = $this->validator->phpInstalledError($php)) {
            return Response::text("Error: {$err}");
        }
        if ($err = $this->validator->engineError($engine)) {
            return Response::text("Error: {$err}");
        }
        if ($err = $this->validator->octaneError($octaneRaw)) {
            return Response::text("Error: {$err}");
        }
        if ($engine !== null) {
            $engine = $this->validator->normalizeEngine($engine);
        }
        $octane = $this->validator->normalizeOctane($octaneRaw);
        if ($custom) {
            $engine = null;
            if ($octane !== null) {
                return Response::text('Error: Octane is only available for Laravel apps (not custom)');
            }
        }
        if (! $custom && ! $hasRepo) {
            return Response::text('Error: repository is required for Laravel apps (set custom=true for SFTP-only apps without Git)');
        }
        if ($docroot && ! preg_match('/^[a-zA-Z0-9_\-\/]+$/', $docroot)) {
            return Response::text('Error: Invalid docroot format. Use alphanumeric characters, dashes, underscores, and slashes only.');
        }
        if ($this->validator->appExists($user)) {
            return Response::text("Error: App '{$user}' already exists");
        }
        $usedBy = $this->validator->domainUsedBy($domain);
        if ($usedBy) {
            return Response::text("Error: Domain '{$domain}' is already used by app '{$usedBy}'");
        }

        $params = compact('user', 'domain', 'repository', 'branch', 'php', 'custom', 'docroot', 'engine', 'octane');
        $args = ['app create'];
        if ($custom) {
            $args[] = '--custom';
        }
        if ($octane !== null) {
            $args[] = '--octane=' . escapeshellarg($octane);
        }
        foreach ($params as $k => $v) {
            if ($k === 'custom' || $k === 'octane') {
                continue;
            }
            if ($v !== null && $v !== '') {
                $args[] = '--' . $k . '=' . escapeshellarg((string) $v);
            }
        }

        $job = $this->jobs->dispatch('app-create', implode(' ', $args), $params);
        return Response::text("Job dispatched: {$job->id} (status: pending). Poll JobShow with id {$job->id} for result.");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'user' => $schema->string()->description('App username (slug, 3-32 lowercase alphanumeric)')->required(),
            'domain' => $schema->string()->description('Primary domain')->required(),
            'repository' => $schema->string()->description('Git repository URL (SSH). Required for Laravel apps; optional for custom apps (omit for SFTP-only / no Git, aligned with Cipi 4.4.4+)'),
            'branch' => $schema->string()->description('Git branch when a repository is set (default: main). Omitted when no repository (SFTP-only custom apps)'),
            'php' => $schema->string()->description('PHP version (default: 8.4)'),
            'custom' => $schema->boolean()->description('Create a custom (non-Laravel) app with classic deploy (no zero-downtime)'),
            'docroot' => $schema->string()->description('Document root path for custom apps (e.g. dist, www, public). Default: /'),
            'engine' => $schema->string()->description('Database engine for Laravel apps: mariadb (default) or pgsql. Requires Cipi 4.8+ and installed engine.'),
            'octane' => $schema->boolean()->description('Serve Laravel via Octane (FrankenPHP) instead of PHP-FPM. Requires Cipi 5.0+, laravel/octane in the repo. Not compatible with custom apps.'),
        ];
    }
}
