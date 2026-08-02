<?php

namespace CipiApi\Http\Controllers;

use CipiApi\Services\CipiJobService;
use CipiApi\Services\CipiValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class WwwController extends Controller
{
    public function __construct(
        protected CipiJobService $jobs,
        protected CipiValidationService $validator,
    ) {}

    public function status(string $name): JsonResponse
    {
        if (! $this->validator->appExists($name)) {
            return response()->json(['error' => "App '{$name}' not found"], 404);
        }

        return response()->json(['data' => $this->validator->getWwwStatus($name)], 200);
    }

    public function add(string $name): JsonResponse
    {
        if (! $this->validator->appExists($name)) {
            return response()->json(['error' => "App '{$name}' not found"], 404);
        }

        $command = 'www add ' . escapeshellarg($name);
        $job = $this->jobs->dispatch('www-add', $command, ['app' => $name]);

        return response()->json(['job_id' => $job->id, 'status' => 'pending'], 202);
    }

    public function forceToRoot(string $name): JsonResponse
    {
        if (! $this->validator->appExists($name)) {
            return response()->json(['error' => "App '{$name}' not found"], 404);
        }

        $command = 'www force-to-root ' . escapeshellarg($name);
        $job = $this->jobs->dispatch('www-force-to-root', $command, ['app' => $name]);

        return response()->json(['job_id' => $job->id, 'status' => 'pending'], 202);
    }

    public function forceFromRoot(string $name): JsonResponse
    {
        if (! $this->validator->appExists($name)) {
            return response()->json(['error' => "App '{$name}' not found"], 404);
        }

        $command = 'www force-from-root ' . escapeshellarg($name);
        $job = $this->jobs->dispatch('www-force-from-root', $command, ['app' => $name]);

        return response()->json(['job_id' => $job->id, 'status' => 'pending'], 202);
    }

    public function clear(string $name): JsonResponse
    {
        if (! $this->validator->appExists($name)) {
            return response()->json(['error' => "App '{$name}' not found"], 404);
        }

        $command = 'www clear ' . escapeshellarg($name);
        $job = $this->jobs->dispatch('www-clear', $command, ['app' => $name]);

        return response()->json(['job_id' => $job->id, 'status' => 'pending'], 202);
    }
}
