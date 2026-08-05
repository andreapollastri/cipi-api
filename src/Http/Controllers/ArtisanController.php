<?php

namespace CipiApi\Http\Controllers;

use CipiApi\Services\CipiAppArtisanService;
use CipiApi\Services\CipiJobService;
use CipiApi\Services\CipiValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ArtisanController extends Controller
{
    public function __construct(
        protected CipiAppArtisanService $artisan,
        protected CipiJobService $jobs,
        protected CipiValidationService $validator,
    ) {}

    public function run(Request $request, string $name): JsonResponse
    {
        if (! $this->validator->appExists($name)) {
            return response()->json(['error' => "App '{$name}' not found"], 404);
        }
        if ($this->validator->isCustomApp($name)) {
            return response()->json(['error' => "App '{$name}' is a custom app and has no Artisan"], 422);
        }

        $validated = $request->validate([
            'command' => 'required|string|max:2000',
        ]);

        try {
            $this->artisan->validateArtisanCommand($validated['command']);
            $command = $this->artisan->buildCipiCommand($name, $validated['command']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $job = $this->jobs->dispatch('app-artisan', $command, [
            'app' => $name,
            'command' => trim($validated['command']),
        ]);

        return response()->json(['job_id' => $job->id, 'status' => 'pending'], 202);
    }
}
