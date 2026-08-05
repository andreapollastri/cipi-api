<?php

namespace CipiApi\Http\Controllers;

use CipiApi\Exceptions\MysqlDatabaseListingUnavailableException;
use CipiApi\Services\CipiJobService;
use CipiApi\Services\CipiServicesCliService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ServiceController extends Controller
{
    public function __construct(
        protected CipiJobService $jobs,
        protected CipiServicesCliService $servicesCli,
    ) {}

    public function list(Request $request): JsonResponse
    {
        $service = $request->query('service');
        $service = is_string($service) ? $service : null;

        try {
            return response()->json(['data' => $this->servicesCli->list($service)], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (MysqlDatabaseListingUnavailableException $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }

    public function restart(string $name): JsonResponse
    {
        if (! preg_match('/^[a-z0-9.-]+$/', $name)) {
            return response()->json(['error' => 'Invalid service name'], 422);
        }

        $job = $this->jobs->dispatch(
            'service-restart',
            'service restart ' . escapeshellarg($name),
            ['service' => $name],
        );

        return response()->json(['job_id' => $job->id, 'status' => 'pending'], 202);
    }
}