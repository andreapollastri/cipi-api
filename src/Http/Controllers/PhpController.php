<?php

namespace CipiApi\Http\Controllers;

use CipiApi\Exceptions\MysqlDatabaseListingUnavailableException;
use CipiApi\Services\CipiJobService;
use CipiApi\Services\CipiPhpCliService;
use CipiApi\Services\CipiValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PhpController extends Controller
{
    public function __construct(
        protected CipiJobService $jobs,
        protected CipiValidationService $validator,
        protected CipiPhpCliService $phpCli,
    ) {}

    public function list(): JsonResponse
    {
        try {
            return response()->json(['data' => $this->phpCli->list()], 200);
        } catch (MysqlDatabaseListingUnavailableException $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }

    public function install(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'version' => 'required|string',
        ]);

        $version = $validated['version'];
        if ($err = $this->validator->phpVersionError($version)) {
            return response()->json(['error' => $err], 422);
        }
        if ($this->validator->isPhpInstalled($version)) {
            return response()->json(['error' => "PHP {$version} is already installed"], 409);
        }

        $job = $this->jobs->dispatch(
            'php-install',
            'php install ' . escapeshellarg($version),
            ['version' => $version],
        );

        return response()->json(['job_id' => $job->id, 'status' => 'pending'], 202);
    }

    public function remove(string $version): JsonResponse
    {
        // Allow removing legacy versions still on disk (CLI validate_php_version_known).
        if (! preg_match('/^\d+\.\d+$/', $version)) {
            return response()->json(['error' => "Invalid PHP version '{$version}'"], 422);
        }
        if (! $this->validator->isPhpInstalled($version)) {
            return response()->json(['error' => "PHP {$version} is not installed"], 404);
        }

        $job = $this->jobs->dispatch(
            'php-remove',
            'php remove ' . escapeshellarg($version) . ' --force',
            ['version' => $version],
        );

        return response()->json(['job_id' => $job->id, 'status' => 'pending'], 202);
    }
}
