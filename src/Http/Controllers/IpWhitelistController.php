<?php

namespace CipiApi\Http\Controllers;

use CipiApi\Services\CipiIpWhitelistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class IpWhitelistController extends Controller
{
    public function __construct(
        protected CipiIpWhitelistService $whitelist,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $data = $this->whitelist->status();
        $data['client_ip'] = $this->clientIp($request);

        return response()->json(['data' => $data], 200);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entries' => 'required|array|min:1',
            'entries.*' => 'required|string|max:64',
            'ensure_client_ip' => 'sometimes|boolean',
        ]);

        $entries = array_values($validated['entries']);
        $ensureClient = $validated['ensure_client_ip'] ?? true;

        try {
            // Safety: when locking down, keep the caller (GUI server / operator) allowed.
            if ($ensureClient && ! in_array('*', $entries, true)) {
                $clientIp = $this->clientIp($request);
                if ($clientIp !== '' && ! in_array($clientIp, $entries, true)) {
                    $entries[] = $clientIp;
                }
            }

            $data = $this->whitelist->set($entries);
            $data['client_ip'] = $this->clientIp($request);

            return response()->json(['data' => $data], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function add(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ip' => 'required|string|max:64',
        ]);

        try {
            $data = $this->whitelist->add($validated['ip']);
            $data['client_ip'] = $this->clientIp($request);

            return response()->json(['data' => $data], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function remove(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ip' => 'required|string|max:64',
        ]);

        try {
            $data = $this->whitelist->remove($validated['ip']);
            $data['client_ip'] = $this->clientIp($request);

            return response()->json(['data' => $data], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function allowAll(Request $request): JsonResponse
    {
        try {
            $data = $this->whitelist->allowAll();
            $data['client_ip'] = $this->clientIp($request);

            return response()->json(['data' => $data], 200);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    protected function clientIp(Request $request): string
    {
        return $this->whitelist->normalizeClientIp((string) $request->ip());
    }
}
