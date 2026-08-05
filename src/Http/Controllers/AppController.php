<?php

namespace CipiApi\Http\Controllers;

use CipiApi\Models\CipiJob;
use CipiApi\Services\CipiJobService;
use CipiApi\Services\CipiValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class AppController extends Controller
{
    public function __construct(
        protected CipiJobService $jobs,
        protected CipiValidationService $validator,
    ) {}

    public function list(): JsonResponse
    {
        $apps = $this->validator->getApps();
        $data = [];
        foreach ($apps as $name => $app) {
            $data[] = [
                'app' => $name,
                'domain' => $app['domain'] ?? '',
                'php' => $app['php'] ?? '',
                'branch' => $app['branch'] ?? '',
                'repository' => $app['repository'] ?? '',
                'aliases' => $app['aliases'] ?? [],
                'engine' => $this->validator->getAppEngine($name),
                'octane' => $this->validator->getAppOctane($name),
                'octane_port' => $this->validator->getAppOctanePort($name),
                'www_redirect' => $this->validator->getWwwRedirect($name),
                'force_https' => $this->validator->isForceHttps($name),
                'suspended' => $this->validator->isSuspended($name),
                'basic_auth' => $this->validator->isBasicAuthEnabled($name),
                'created_at' => $app['created_at'] ?? '',
            ];
        }
        return response()->json(['data' => $data], 200);
    }

    public function show(string $name): JsonResponse
    {
        if (! $this->validator->appExists($name)) {
            return response()->json(['error' => "App '{$name}' not found"], 404);
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
        return response()->json(['data' => $app], 200);
    }

    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user' => 'required|string',
            'domain' => 'required|string',
            'repository' => [
                Rule::requiredIf(fn () => ! $request->boolean('custom')),
                'nullable',
                'string',
            ],
            'branch' => 'nullable|string|max:64',
            'php' => 'nullable|string',
            'custom' => 'nullable|boolean',
            'docroot' => 'nullable|string|max:128',
            'engine' => 'nullable|string',
            'octane' => 'nullable',
        ]);

        if (isset($validated['repository'])) {
            $validated['repository'] = trim((string) $validated['repository']);
            if ($validated['repository'] === '') {
                $validated['repository'] = null;
            }
        }
        if ($request->boolean('custom') && empty($validated['repository'])) {
            unset($validated['branch']);
        }

        if ($err = $this->validator->usernameError($validated['user'])) {
            return response()->json(['error' => $err], 422);
        }
        if ($err = $this->validator->domainError($validated['domain'])) {
            return response()->json(['error' => $err], 422);
        }
        if ($err = $this->validator->phpInstalledError($validated['php'] ?? null)) {
            return response()->json(['error' => $err], 422);
        }
        if ($err = $this->validator->engineError($validated['engine'] ?? null)) {
            return response()->json(['error' => $err], 422);
        }
        if ($err = $this->validator->octaneError($validated['octane'] ?? null)) {
            return response()->json(['error' => $err], 422);
        }
        if (! empty($validated['engine'])) {
            $validated['engine'] = $this->validator->normalizeEngine($validated['engine']);
        }
        $octane = $this->validator->normalizeOctane($validated['octane'] ?? null);
        unset($validated['octane']);
        if ($request->boolean('custom')) {
            unset($validated['engine']);
            if ($octane !== null) {
                return response()->json(['error' => 'Octane is only available for Laravel apps (not custom)'], 422);
            }
        }
        if (! empty($validated['docroot']) && ! preg_match('/^[a-zA-Z0-9_\-\/]+$/', $validated['docroot'])) {
            return response()->json(['error' => 'Invalid docroot format. Use alphanumeric characters, dashes, underscores, and slashes only.'], 422);
        }
        if ($this->validator->appExists($validated['user'])) {
            return response()->json(['error' => "App '{$validated['user']}' already exists"], 409);
        }
        $usedBy = $this->validator->domainUsedBy($validated['domain']);
        if ($usedBy) {
            return response()->json(['error' => "Domain '{$validated['domain']}' is already used by app '{$usedBy}'"], 409);
        }
        if ($this->hasPendingAppCreate($validated['user'])) {
            return response()->json(['error' => "App '{$validated['user']}' is already being created"], 409);
        }

        $isCustom = ! empty($validated['custom']);

        $args = ['app create'];
        if ($isCustom) {
            $args[] = '--custom';
        }
        if ($octane !== null) {
            $args[] = '--octane=' . escapeshellarg($octane);
            $validated['octane'] = $octane;
        }
        foreach ($validated as $k => $v) {
            if ($k === 'custom' || $k === 'octane') {
                continue;
            }
            if ($v !== null && $v !== '') {
                $args[] = '--' . $k . '=' . escapeshellarg((string) $v);
            }
        }

        $job = $this->jobs->dispatch('app-create', implode(' ', $args), $validated);
        return response()->json(['job_id' => $job->id, 'status' => 'pending'], 202);
    }

    public function edit(Request $request, string $name): JsonResponse
    {
        if (! $this->validator->appExists($name)) {
            return response()->json(['error' => "App '{$name}' not found"], 404);
        }

        $validated = $request->validate([
            'php' => 'nullable|string',
            'branch' => 'nullable|string|max:64',
            'repository' => 'nullable|string',
            'domain' => 'nullable|string',
        ]);

        $filtered = $this->validator->filterUnchangedAppEditFields($name, $validated);
        if (empty($filtered)) {
            return response()->json(['error' => 'Nothing to change. Provide a different php, branch, repository, or domain.'], 422);
        }

        if (isset($filtered['php'])) {
            if ($err = $this->validator->phpInstalledError($filtered['php'])) {
                return response()->json(['error' => $err], 422);
            }
        }
        if (isset($filtered['domain'])) {
            if ($err = $this->validator->domainError($filtered['domain'])) {
                return response()->json(['error' => $err], 422);
            }
            $usedBy = $this->validator->domainUsedBy($filtered['domain'], $name);
            if ($usedBy) {
                return response()->json(['error' => "Domain '{$filtered['domain']}' is already used by app '{$usedBy}'"], 409);
            }
        }

        $args = ['app edit', escapeshellarg($name)];
        foreach ($filtered as $k => $v) {
            $args[] = '--' . $k . '=' . escapeshellarg((string) $v);
        }

        $job = $this->jobs->dispatch('app-edit', implode(' ', $args), ['app' => $name] + $filtered);
        return response()->json(['job_id' => $job->id, 'status' => 'pending'], 202);
    }

    /**
     * Recreate the GitHub/GitLab webhook (optionally rotate CIPI_WEBHOOK_TOKEN).
     * Requires Cipi CLI ≥ 5.0.6.
     */
    public function webhookRecreate(Request $request, string $name): JsonResponse
    {
        if (! $this->validator->appExists($name)) {
            return response()->json(['error' => "App '{$name}' not found"], 404);
        }

        $validated = $request->validate([
            'rotate_secret' => 'sometimes|boolean',
        ]);

        $rotate = (bool) ($validated['rotate_secret'] ?? false);
        $command = 'app webhook recreate ' . escapeshellarg($name);
        if ($rotate) {
            $command .= ' --rotate-secret';
        }

        $job = $this->jobs->dispatch('app-webhook-recreate', $command, [
            'app' => $name,
            'rotate_secret' => $rotate,
        ]);

        return response()->json(['job_id' => $job->id, 'status' => 'pending'], 202);
    }

    public function delete(string $name): JsonResponse
    {
        if (! $this->validator->appExists($name)) {
            return response()->json(['error' => "App '{$name}' not found"], 404);
        }

        $command = 'app delete ' . escapeshellarg($name) . ' --force';
        $job = $this->jobs->dispatch('app-delete', $command, ['app' => $name]);
        return response()->json(['job_id' => $job->id, 'status' => 'pending'], 202);
    }

    public function suspend(string $name): JsonResponse
    {
        if (! $this->validator->appExists($name)) {
            return response()->json(['error' => "App '{$name}' not found"], 404);
        }
        if ($this->validator->isSuspended($name)) {
            return response()->json(['error' => "App '{$name}' is already suspended"], 409);
        }

        $command = 'app suspend ' . escapeshellarg($name);
        $job = $this->jobs->dispatch('app-suspend', $command, ['app' => $name]);
        return response()->json(['job_id' => $job->id, 'status' => 'pending'], 202);
    }

    public function unsuspend(string $name): JsonResponse
    {
        if (! $this->validator->appExists($name)) {
            return response()->json(['error' => "App '{$name}' not found"], 404);
        }
        if (! $this->validator->isSuspended($name)) {
            return response()->json(['error' => "App '{$name}' is not suspended"], 409);
        }

        $command = 'app unsuspend ' . escapeshellarg($name);
        $job = $this->jobs->dispatch('app-unsuspend', $command, ['app' => $name]);
        return response()->json(['job_id' => $job->id, 'status' => 'pending'], 202);
    }

    protected function hasPendingAppCreate(string $user): bool
    {
        return CipiJob::where('type', 'app-create')
            ->whereIn('status', ['pending', 'running'])
            ->where('params->user', $user)
            ->exists();
    }
}
