<?php

namespace CipiApi\Http\Controllers;

use CipiApi\Services\CipiAppDeployConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Structured deploy recipe options (not raw deploy.php upload).
 */
class DeployConfigController extends Controller
{
    public function __construct(
        protected CipiAppDeployConfigService $deployConfig,
    ) {}

    public function show(string $name): JsonResponse
    {
        try {
            return response()->json(['data' => $this->deployConfig->show($name)], 200);
        } catch (\RuntimeException $e) {
            return $this->runtimeError($e);
        }
    }

    public function update(Request $request, string $name): JsonResponse
    {
        $validated = $request->validate([
            'keep_releases' => 'nullable|integer|min:1|max:20',
            'migrate' => 'nullable|boolean',
            'optimize' => 'nullable|boolean',
            'storage_link' => 'nullable|boolean',
            'queue_restart' => 'nullable|boolean',
            'horizon_terminate' => 'nullable|boolean',
            'predeploy_snapshot' => 'nullable|boolean',
            'node_build' => 'nullable|string|max:500',
            'extra_artisan' => 'nullable|array',
            'extra_artisan.*' => 'string|max:64',
        ]);

        try {
            return response()->json(['data' => $this->deployConfig->update($name, $validated)], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return $this->runtimeError($e);
        }
    }

    protected function runtimeError(\RuntimeException $e): JsonResponse
    {
        $msg = $e->getMessage();
        if (str_contains($msg, 'not found')) {
            return response()->json(['error' => $msg], 404);
        }
        if (str_contains($msg, 'custom app')) {
            return response()->json(['error' => $msg], 422);
        }

        return response()->json(['error' => $msg], 503);
    }
}
