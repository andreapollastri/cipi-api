<?php

namespace CipiApi\Services;

/**
 * Manage Laravel app `.env` key/value pairs via `sudo cipi app env …` on the host.
 * Requires Cipi CLI ≥ 5.0.3 (non-interactive --show/--set/--unset/--json).
 */
class CipiAppEnvService
{
    public function __construct(
        protected CipiCliService $cli,
        protected CipiValidationService $validator,
    ) {}

    /**
     * @return array{app: string, vars: array<string, string>}
     */
    public function show(string $app): array
    {
        $this->assertLaravelApp($app);

        $result = $this->cli->run('app env ' . escapeshellarg($app) . ' --show --json');
        if ($result['exit_code'] !== 0) {
            $detail = trim($result['output'] ?? '');
            throw new \RuntimeException($detail !== '' ? $detail : 'cipi app env --show failed');
        }

        return [
            'app' => $app,
            'vars' => $this->decodeJsonObject($result['output'] ?? ''),
        ];
    }

    /**
     * @param  array<string, string>  $set
     * @param  list<string>  $unset
     * @return array{app: string, vars: array<string, string>}
     */
    public function update(string $app, array $set = [], array $unset = []): array
    {
        $this->assertLaravelApp($app);

        if ($set === [] && $unset === []) {
            throw new \InvalidArgumentException('Provide at least one of set or unset');
        }

        foreach (array_keys($set) as $key) {
            $this->assertEnvKey((string) $key);
            if (! is_scalar($set[$key]) && $set[$key] !== null) {
                throw new \InvalidArgumentException("Env value for '{$key}' must be a string");
            }
        }
        foreach ($unset as $key) {
            $this->assertEnvKey((string) $key);
        }

        $parts = ['app env', escapeshellarg($app)];
        foreach ($set as $key => $value) {
            $parts[] = '--set=' . escapeshellarg($key . '=' . (string) $value);
        }
        foreach ($unset as $key) {
            $parts[] = '--unset=' . escapeshellarg((string) $key);
        }
        $parts[] = '--json';

        $result = $this->cli->run(implode(' ', $parts));
        if ($result['exit_code'] !== 0) {
            $detail = trim($result['output'] ?? '');
            throw new \RuntimeException($detail !== '' ? $detail : 'cipi app env update failed');
        }

        return [
            'app' => $app,
            'vars' => $this->decodeJsonObject($result['output'] ?? ''),
        ];
    }

    public function assertEnvKey(string $key): void
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
            throw new \InvalidArgumentException("Invalid env key: {$key}");
        }
    }

    protected function assertLaravelApp(string $app): void
    {
        if (! $this->validator->appExists($app)) {
            throw new \RuntimeException("App '{$app}' not found");
        }
        if ($this->validator->isCustomApp($app)) {
            throw new \RuntimeException("App '{$app}' is a custom app and has no .env");
        }
    }

    /**
     * @return array<string, string>
     */
    protected function decodeJsonObject(string $output): array
    {
        $plain = preg_replace('/\x1b\[[0-9;]*m/', '', $output) ?? $output;
        if (! preg_match('/\{.*\}/s', $plain, $m)) {
            throw new \RuntimeException('Could not parse env JSON from cipi output');
        }

        $decoded = json_decode($m[0], true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('Invalid env JSON from cipi');
        }

        $vars = [];
        foreach ($decoded as $key => $value) {
            if (! is_string($key)) {
                continue;
            }
            $vars[$key] = is_scalar($value) || $value === null ? (string) $value : json_encode($value);
        }

        return $vars;
    }
}
