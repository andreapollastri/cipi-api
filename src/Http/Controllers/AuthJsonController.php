<?php

namespace CipiApi\Http\Controllers;

use CipiApi\Services\CipiAppAuthJsonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Composer / shared auth.json — not HTTP Basic Auth ({@see BasicAuthController}).
 */
class AuthJsonController extends Controller
{
    public function __construct(
        protected CipiAppAuthJsonService $authJson,
    ) {}

    public function show(string $name): JsonResponse
    {
        try {
            return response()->json(['data' => $this->authJson->show($name)], 200);
        } catch (\RuntimeException $e) {
            return $this->runtimeError($e);
        }
    }

    public function create(Request $request, string $name): JsonResponse
    {
        $validated = $request->validate([
            'force' => 'nullable|boolean',
        ]);

        try {
            $data = $this->authJson->create($name, (bool) ($validated['force'] ?? false));

            return response()->json(['data' => $data], 201);
        } catch (\RuntimeException $e) {
            return $this->runtimeError($e);
        }
    }

    public function update(Request $request, string $name): JsonResponse
    {
        $raw = trim((string) $request->getContent());
        if ($raw === '') {
            return response()->json(['error' => 'JSON body is required'], 422);
        }

        json_decode($raw);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json(['error' => 'Invalid JSON body'], 422);
        }

        try {
            return response()->json(['data' => $this->authJson->update($name, $raw)], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return $this->runtimeError($e);
        }
    }

    public function delete(string $name): JsonResponse
    {
        try {
            return response()->json(['data' => $this->authJson->delete($name)], 200);
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
        if (str_contains($msg, 'already exists')) {
            return response()->json(['error' => $msg], 409);
        }

        return response()->json(['error' => $msg], 503);
    }
}
