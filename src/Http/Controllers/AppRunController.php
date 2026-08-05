<?php

namespace CipiApi\Http\Controllers;

use CipiApi\Services\CipiAppRunService;
use CipiApi\Services\CipiJobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AppRunController extends Controller
{
    public function __construct(
        protected CipiAppRunService $runner,
        protected CipiJobService $jobs,
    ) {}

    public function commands(): JsonResponse
    {
        return response()->json([
            'data' => [
                'commands' => $this->runner->allowedCommands(),
                'notes' => [
                    'Non-interactive only: editors, pagers, shells, and REPLs are not allowed.',
                    'Runs as the app user under current/ (Laravel), htdocs/ (custom), or home.',
                    'Requires Cipi CLI >= 5.0.3.',
                ],
            ],
        ], 200);
    }

    public function run(Request $request, string $name): JsonResponse
    {
        try {
            $this->runner->assertApp($name);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        $validated = $request->validate([
            'command' => 'required|string|max:2000',
        ]);

        try {
            $this->runner->validateCommand($validated['command']);
            $cipi = $this->runner->buildCipiCommand($name, $validated['command']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $job = $this->jobs->dispatch('app-run', $cipi, [
            'app' => $name,
            'command' => trim($validated['command']),
        ]);

        return response()->json(['job_id' => $job->id, 'status' => 'pending'], 202);
    }
}
