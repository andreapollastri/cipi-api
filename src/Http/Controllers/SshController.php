<?php

namespace CipiApi\Http\Controllers;

use CipiApi\Exceptions\MysqlDatabaseListingUnavailableException;
use CipiApi\Services\CipiSshCliService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SshController extends Controller
{
    public function __construct(
        protected CipiSshCliService $sshCli,
    ) {}

    public function list(): JsonResponse
    {
        try {
            return response()->json(['data' => $this->sshCli->list()], 200);
        } catch (MysqlDatabaseListingUnavailableException $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }

    public function add(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => 'required|string|max:8192',
        ]);

        $key = trim($validated['key']);
        if (! preg_match('/^(ssh-(rsa|ed25519)|ecdsa-sha2-\S+) /', $key)) {
            return response()->json(['error' => 'Invalid key format. Must start with ssh-rsa, ssh-ed25519, or ecdsa-sha2-*'], 422);
        }

        try {
            $result = $this->sshCli->add($key);

            return response()->json(['data' => $result], 200);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function remove(int $n): JsonResponse
    {
        if ($n < 1) {
            return response()->json(['error' => 'Key id must be a positive integer'], 422);
        }

        try {
            $result = $this->sshCli->remove($n);

            return response()->json(['data' => $result], 200);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
