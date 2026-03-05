<?php

namespace CipiApi\Http\Controllers;

use CipiApi\Models\CipiJob;
use CipiApi\Services\CipiOutputParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class JobController extends Controller
{
    public function __construct(
        protected CipiOutputParser $parser,
    ) {}

    public function show(string $id): JsonResponse
    {
        $job = CipiJob::find($id);
        if (! $job) {
            return response()->json(['error' => 'Job not found'], 404);
        }

        $data = [
            'id' => $job->id,
            'type' => $job->type,
            'status' => $job->status,
            'created_at' => $job->created_at?->toIso8601String(),
            'updated_at' => $job->updated_at?->toIso8601String(),
        ];

        if (in_array($job->status, ['completed', 'failed'])) {
            $data['exit_code'] = $job->exit_code;
            $data['result'] = $this->parser->parse(
                $job->type,
                $job->output ?? '',
                $job->status === 'completed',
            );
            $data['output'] = $job->output;
        }

        return response()->json(['data' => $data], 200);
    }
}
