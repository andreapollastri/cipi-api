<?php

namespace CipiApi\Http\Controllers;

use CipiApi\Services\CipiJobService;
use CipiApi\Services\CipiValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class SslController extends Controller
{
    public function __construct(
        protected CipiJobService $jobs,
        protected CipiValidationService $validator,
    ) {}

    public function install(string $name): JsonResponse
    {
        if (! $this->validator->appExists($name)) {
            return response()->json(['error' => "App '{$name}' not found"], 404);
        }

        $command = 'ssl install ' . escapeshellarg($name);
        $job = $this->jobs->dispatch('ssl-install', $command, ['app' => $name]);
        return response()->json(['job_id' => $job->id, 'status' => 'pending'], 202);
    }

    public function force(string $name): JsonResponse
    {
        if (! $this->validator->appExists($name)) {
            return response()->json(['error' => "App '{$name}' not found"], 404);
        }

        $command = 'ssl force ' . escapeshellarg($name);
        $job = $this->jobs->dispatch('ssl-force', $command, ['app' => $name]);

        return response()->json(['job_id' => $job->id, 'status' => 'pending'], 202);
    }
}
