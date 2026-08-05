<?php

namespace CipiApi\Http\Controllers;

use CipiApi\Services\CipiAppEnvService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class EnvController extends Controller
{
    public function __construct(
        protected CipiAppEnvService $env,
    ) {}

    public function show(string $name): JsonResponse
    {
        try {
            return response()->json(['data' => $this->env->show($name)], 200);
        } catch (\RuntimeException $e) {
            return $this->runtimeError($e);
        }
    }

    public function update(Request $request, string $name): JsonResponse
    {
        $validated = $request->validate([
            'set' => 'nullable|array',
            'set.*' => 'nullable|string',
            'unset' => 'nullable|array',
            'unset.*' => 'string',
        ]);

        $set = $validated['set'] ?? [];
        $unset = $validated['unset'] ?? [];

        try {
            return response()->json(['data' => $this->env->update($name, $set, $unset)], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return $this->runtimeError($e);
        }
    }

    protected function runtimeError(\RuntimeException $e): JsonResponse
    {
        $msg = $e->getMessage();
        if (str_contains($msg, 'not found') || str_contains($msg, 'custom app')) {
            $status = str_contains($msg, 'custom app') ? 422 : 404;

            return response()->json(['error' => $msg], $status);
        }

        return response()->json(['error' => $msg], 503);
    }
}
