<?php

namespace CipiApi\Http\Controllers;

use CipiApi\Exceptions\MysqlDatabaseListingUnavailableException;
use CipiApi\Services\CipiHealthCliService;
use CipiApi\Services\CipiValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class HealthController extends Controller
{
    public function __construct(
        protected CipiHealthCliService $health,
        protected CipiValidationService $validator,
    ) {}

    public function list(): JsonResponse
    {
        try {
            return response()->json(['data' => $this->health->list()], 200);
        } catch (MysqlDatabaseListingUnavailableException $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }

    public function show(string $name): JsonResponse
    {
        if (! $this->validator->appExists($name)) {
            return response()->json(['error' => "App '{$name}' not found"], 404);
        }

        try {
            return response()->json(['data' => $this->health->show($name)], 200);
        } catch (MysqlDatabaseListingUnavailableException $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }

    public function update(Request $request, string $name): JsonResponse
    {
        if (! $this->validator->appExists($name)) {
            return response()->json(['error' => "App '{$name}' not found"], 404);
        }

        $validated = $request->validate([
            'url' => 'nullable|string|max:512',
            'expect' => 'nullable|integer|min:100|max:599',
        ]);

        $url = isset($validated['url']) ? trim((string) $validated['url']) : null;
        if ($url === '') {
            $url = null;
        }
        if ($url !== null && ! preg_match('#^https?://#i', $url)) {
            return response()->json(['error' => 'url must start with http:// or https://'], 422);
        }

        try {
            $data = $this->health->set($name, $url, (int) ($validated['expect'] ?? 200));

            return response()->json(['data' => $data], 200);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function destroy(string $name): JsonResponse
    {
        if (! $this->validator->appExists($name)) {
            return response()->json(['error' => "App '{$name}' not found"], 404);
        }

        try {
            $this->health->clear($name);

            return response()->json(['data' => $this->health->show($name)], 200);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function check(string $name): JsonResponse
    {
        if (! $this->validator->appExists($name)) {
            return response()->json(['error' => "App '{$name}' not found"], 404);
        }

        try {
            $data = $this->health->check($name);

            return response()->json(['data' => $data], 200);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}