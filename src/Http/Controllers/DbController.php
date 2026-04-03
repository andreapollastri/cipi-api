<?php

namespace CipiApi\Http\Controllers;

use CipiApi\Exceptions\MysqlDatabaseListingUnavailableException;
use CipiApi\Models\CipiJob;
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
    ) {}

    public function list(): JsonResponse
    {
        try {
            return response()->json(['data' => $this->dbListCli->list()], 200);
        } catch (MysqlDatabaseListingUnavailableException $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }

    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
        ]);

        $name = $validated['name'];
        if ($err = $this->validator->usernameError($name)) {
            return response()->json(['error' => $err], 422);
        }
        if ($this->hasPendingDbCreate($name)) {
            return response()->json(['error' => "Database '{$name}' is already being created"], 409);
        }

        $command = 'db create --name=' . escapeshellarg($name);
        $job = $this->jobs->dispatch('db-create', $command, ['name' => $name]);

        return response()->json(['job_id' => $job->id, 'status' => 'pending'], 202);
    }

    public function delete(string $name): JsonResponse
    {
        $command = 'db delete ' . escapeshellarg($name);
        $job = $this->jobs->dispatch('db-delete', $command, ['name' => $name]);

        return response()->json(['job_id' => $job->id, 'status' => 'pending'], 202);
    }

    public function backup(string $name): JsonResponse
    {
        $command = 'db backup ' . escapeshellarg($name);
        $job = $this->jobs->dispatch('db-backup', $command, ['name' => $name]);

        return response()->json(['job_id' => $job->id, 'status' => 'pending'], 202);
    }

    public function restore(Request $request, string $name): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|string|max:512',
        ]);

        $file = $validated['file'];
        if (! preg_match('/^[a-zA-Z0-9_\-\/\.]+\.sql\.gz$/', $file)) {
            return response()->json(['error' => 'Invalid backup file path. Must be a .sql.gz file with safe characters.'], 422);
        }

        $command = 'db restore ' . escapeshellarg($name) . ' ' . escapeshellarg($file);
        $job = $this->jobs->dispatch('db-restore', $command, ['name' => $name, 'file' => $file]);

        return response()->json(['job_id' => $job->id, 'status' => 'pending'], 202);
    }

    public function password(string $name): JsonResponse
    {
        $command = 'db password ' . escapeshellarg($name);
        $job = $this->jobs->dispatch('db-password', $command, ['name' => $name]);

        return response()->json(['job_id' => $job->id, 'status' => 'pending'], 202);
    }

    protected function hasPendingDbCreate(string $name): bool
    {
        return CipiJob::where('type', 'db-create')
            ->whereIn('status', ['pending', 'running'])
            ->where('params->name', $name)
            ->exists();
    }
}
