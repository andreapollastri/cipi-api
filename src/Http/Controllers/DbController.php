<?php

namespace CipiApi\Http\Controllers;

use CipiApi\Exceptions\MysqlDatabaseListingUnavailableException;
use CipiApi\Models\CipiJob;
use CipiApi\Services\CipiDatabaseEnginesCliService;
use CipiApi\Services\CipiDatabaseListCliService;
use CipiApi\Services\CipiJobService;
use CipiApi\Services\CipiValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DbController extends Controller
{
    public function __construct(
        protected CipiJobService $jobs,
        protected CipiValidationService $validator,
        protected CipiDatabaseListCliService $dbListCli,
        protected CipiDatabaseEnginesCliService $dbEnginesCli,
    ) {}

    public function engines(): JsonResponse
    {
        try {
            return response()->json(['data' => $this->dbEnginesCli->list()], 200);
        } catch (MysqlDatabaseListingUnavailableException $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }

    public function list(Request $request): JsonResponse
    {
        $engine = $request->query('engine');
        if (is_string($engine) && $engine !== '') {
            if ($err = $this->validator->engineError($engine)) {
                return response()->json(['error' => $err], 422);
            }
            $engine = $this->validator->normalizeEngine($engine);
        } else {
            $engine = null;
        }

        try {
            return response()->json(['data' => $this->dbListCli->list($engine)], 200);
        } catch (MysqlDatabaseListingUnavailableException $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }

    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'engine' => 'nullable|string',
        ]);

        $name = $validated['name'];
        if ($err = $this->validator->usernameError($name)) {
            return response()->json(['error' => $err], 422);
        }
        if ($err = $this->validator->engineError($validated['engine'] ?? null)) {
            return response()->json(['error' => $err], 422);
        }

        $engine = $this->validator->normalizeEngine($validated['engine'] ?? null);
        $params = ['name' => $name];
        if ($engine !== null) {
            $params['engine'] = $engine;
        }

        if ($this->hasPendingDbCreate($name, $engine)) {
            return response()->json(['error' => "Database '{$name}' is already being created"], 409);
        }

        $command = 'db create --name=' . escapeshellarg($name);
        if ($engine !== null) {
            $command .= ' --engine=' . escapeshellarg($engine);
        }
        $job = $this->jobs->dispatch('db-create', $command, $params);

        return response()->json(['job_id' => $job->id, 'status' => 'pending'], 202);
    }

    public function delete(Request $request, string $name): JsonResponse
    {
        $engine = $this->resolveEngineFromRequest($request);
        if ($engine instanceof JsonResponse) {
            return $engine;
        }

        $params = ['name' => $name];
        $command = 'db delete ' . escapeshellarg($name) . ' --force';
        if ($engine !== null) {
            $params['engine'] = $engine;
            $command .= ' --engine=' . escapeshellarg($engine);
        }
        $job = $this->jobs->dispatch('db-delete', $command, $params);

        return response()->json(['job_id' => $job->id, 'status' => 'pending'], 202);
    }

    public function backup(Request $request, string $name): JsonResponse
    {
        $engine = $this->resolveEngineFromRequest($request);
        if ($engine instanceof JsonResponse) {
            return $engine;
        }

        $params = ['name' => $name];
        $command = 'db backup ' . escapeshellarg($name);
        if ($engine !== null) {
            $params['engine'] = $engine;
            $command .= ' --engine=' . escapeshellarg($engine);
        }
        $job = $this->jobs->dispatch('db-backup', $command, $params);

        return response()->json(['job_id' => $job->id, 'status' => 'pending'], 202);
    }

    public function restore(Request $request, string $name): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|string|max:512',
            'engine' => 'nullable|string',
        ]);

        $file = $validated['file'];
        if (! preg_match('/^[a-zA-Z0-9_\-\/\.]+\.sql\.gz$/', $file)) {
            return response()->json(['error' => 'Invalid backup file path. Must be a .sql.gz file with safe characters.'], 422);
        }
        if ($err = $this->validator->engineError($validated['engine'] ?? null)) {
            return response()->json(['error' => $err], 422);
        }

        $engine = $this->validator->normalizeEngine($validated['engine'] ?? null);
        $params = ['name' => $name, 'file' => $file];
        $command = 'db restore ' . escapeshellarg($name) . ' ' . escapeshellarg($file) . ' --force';
        if ($engine !== null) {
            $params['engine'] = $engine;
            $command .= ' --engine=' . escapeshellarg($engine);
        }
        $job = $this->jobs->dispatch('db-restore', $command, $params);

        return response()->json(['job_id' => $job->id, 'status' => 'pending'], 202);
    }

    public function password(Request $request, string $name): JsonResponse
    {
        $engine = $this->resolveEngineFromRequest($request);
        if ($engine instanceof JsonResponse) {
            return $engine;
        }

        $params = ['name' => $name];
        $command = 'db password ' . escapeshellarg($name);
        if ($engine !== null) {
            $params['engine'] = $engine;
            $command .= ' --engine=' . escapeshellarg($engine);
        }
        $job = $this->jobs->dispatch('db-password', $command, $params);

        return response()->json(['job_id' => $job->id, 'status' => 'pending'], 202);
    }

    /**
     * Install a database engine (async). Requires Cipi CLI ≥ 5.0.6 sudoers.
     */
    public function installEngine(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'engine' => 'required|string',
        ]);

        if ($err = $this->validator->engineError($validated['engine'])) {
            return response()->json(['error' => $err], 422);
        }

        $engine = $this->validator->normalizeEngine($validated['engine']);
        $job = $this->jobs->dispatch(
            'db-install',
            'db install ' . escapeshellarg($engine),
            ['engine' => $engine],
        );

        return response()->json(['job_id' => $job->id, 'status' => 'pending'], 202);
    }

    /**
     * Set the server-wide default database engine (sync).
     */
    public function setDefault(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'engine' => 'required|string',
        ]);

        if ($err = $this->validator->engineError($validated['engine'])) {
            return response()->json(['error' => $err], 422);
        }

        $engine = $this->validator->normalizeEngine($validated['engine']);
        $cli = app(\CipiApi\Services\CipiCliService::class);
        $result = $cli->run('db default ' . escapeshellarg($engine));
        if ($result['exit_code'] !== 0) {
            $detail = trim($result['output'] ?? '');

            return response()->json([
                'error' => $detail !== '' ? $detail : 'Failed to set default database engine',
            ], 422);
        }

        try {
            return response()->json(['data' => $this->dbEnginesCli->list()], 200);
        } catch (MysqlDatabaseListingUnavailableException $e) {
            return response()->json([
                'data' => ['default' => $engine, 'engines' => []],
                'message' => 'Default engine updated',
            ], 200);
        }
    }

    /**
     * @return string|null|JsonResponse Normalized engine, null when omitted, or 422 response.
     */
    protected function resolveEngineFromRequest(Request $request): string|null|JsonResponse
    {
        $raw = $request->input('engine', $request->query('engine'));
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        if ($err = $this->validator->engineError($raw)) {
            return response()->json(['error' => $err], 422);
        }

        return $this->validator->normalizeEngine($raw);
    }

    protected function hasPendingDbCreate(string $name, ?string $engine): bool
    {
        $query = CipiJob::where('type', 'db-create')
            ->whereIn('status', ['pending', 'running'])
            ->where('params->name', $name);

        if ($engine !== null) {
            $query->where('params->engine', $engine);
        }

        return $query->exists();
    }
}
