<?php

namespace CipiApi\Http\Controllers;

use CipiApi\Exceptions\MysqlDatabaseListingUnavailableException;
use CipiApi\Services\CipiSmtpCliService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SmtpController extends Controller
{
    public function __construct(
        protected CipiSmtpCliService $smtp,
    ) {}

    public function show(): JsonResponse
    {
        try {
            return response()->json(['data' => $this->smtp->status()], 200);
        } catch (MysqlDatabaseListingUnavailableException $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }

    public function update(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'host' => 'required|string|max:255',
                'port' => 'nullable|integer|min:1|max:65535',
                'user' => 'required|string|max:255',
                'password' => 'nullable|string|max:512',
                'from' => 'required|email|max:255',
                'to' => 'required|email|max:255',
                'tls' => 'sometimes|boolean',
                'enabled' => 'sometimes|boolean',
                'test' => 'sometimes|boolean',
            ]);

            $current = $this->smtp->status();
            $password = $validated['password'] ?? '';
            if ($password === '' && empty($current['configured'])) {
                return response()->json(['error' => 'password is required when configuring SMTP for the first time'], 422);
            }

            $config = [
                'host' => $validated['host'],
                'port' => $validated['port'] ?? 587,
                'user' => $validated['user'],
                'from' => $validated['from'],
                'to' => $validated['to'],
                'tls' => $validated['tls'] ?? true,
                'enabled' => $validated['enabled'] ?? true,
                'test' => $validated['test'] ?? true,
            ];
            if ($password !== '') {
                $config['password'] = $password;
            }

            $result = $this->smtp->configure($config);

            return response()->json(['data' => $result['status'], 'message' => $result['output']], 200);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (MysqlDatabaseListingUnavailableException $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function enable(): JsonResponse
    {
        return $this->simple('enable');
    }

    public function disable(): JsonResponse
    {
        return $this->simple('disable');
    }

    public function test(): JsonResponse
    {
        return $this->simple('test');
    }

    public function destroy(): JsonResponse
    {
        try {
            $this->smtp->delete();

            return response()->json(['data' => $this->smtp->status()], 200);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (MysqlDatabaseListingUnavailableException $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }

    protected function simple(string $action): JsonResponse
    {
        try {
            $result = $this->smtp->{$action}();
            $status = $this->smtp->status();

            return response()->json(['data' => $status, 'message' => $result['output'] ?? null], 200);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (MysqlDatabaseListingUnavailableException $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }
}
