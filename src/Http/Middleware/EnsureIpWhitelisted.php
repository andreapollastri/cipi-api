<?php

namespace CipiApi\Http\Middleware;

use CipiApi\Services\CipiIpWhitelistService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce /etc/cipi/api-ip-whitelist for API + MCP requests.
 * Default (*) / missing file = allow all.
 */
class EnsureIpWhitelisted
{
    public function __construct(
        protected CipiIpWhitelistService $whitelist,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $ip = $this->whitelist->normalizeClientIp((string) $request->ip());

        if ($ip === '' || ! $this->whitelist->allows($ip)) {
            return response()->json([
                'error' => 'IP not allowed',
                'ip' => $ip !== '' ? $ip : null,
            ], 403);
        }

        return $next($request);
    }
}
