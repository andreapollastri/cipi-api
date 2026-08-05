<?php

namespace CipiApi\Services;

/**
 * Manage Composer / shared `auth.json` via `sudo cipi auth …` on the host.
 * Distinct from HTTP Basic Auth (`cipi basicauth`). Requires Cipi CLI ≥ 5.0.3.
 */
class CipiAppAuthJsonService
{
    public function __construct(
        protected CipiCliService $cli,
        protected CipiValidationService $validator,
    ) {}

    /**
     * @return array{app: string, content: array|list}
     */
    public function show(string $app): array
    {
        $this->assertApp($app);

        $result = $this->cli->run('auth show ' . escapeshellarg($app) . ' --json');
        if ($result['exit_code'] !== 0) {
            $detail = trim($result['output'] ?? '');
            throw new \RuntimeException($detail !== '' ? $detail : 'cipi auth show failed');
        }

        return [
            'app' => $app,
            'content' => $this->decodeJson($result['output'] ?? ''),
        ];
    }

    /**
     * @return array{app: string, content: array|list}
     */
    public function create(string $app, bool $force = false): array
    {
        $this->assertApp($app);

        $cmd = 'auth create ' . escapeshellarg($app);
        if ($force) {
            $cmd .= ' --force';
        }

        $result = $this->cli->run($cmd);
        if ($result['exit_code'] !== 0) {
            $detail = trim($result['output'] ?? '');
            throw new \RuntimeException($detail !== '' ? $detail : 'cipi auth create failed');
        }

        return $this->show($app);
    }

    /**
     * @param  array|list|string  $content  Decoded array or raw JSON string (preserves `{}` vs `[]`)
     * @return array{app: string, content: array|list}
     */
    public function update(string $app, array|string $content): array
    {
        $this->assertApp($app);

        if (is_string($content)) {
            $encoded = trim($content);
            if ($encoded === '') {
                throw new \InvalidArgumentException('Invalid JSON content');
            }
            $decoded = json_decode($encoded);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('Invalid JSON content');
            }
            if (! is_array($decoded) && ! is_object($decoded)) {
                throw new \InvalidArgumentException('auth.json must be a JSON object or array');
            }
            $encoded = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } else {
            $encoded = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
        if ($encoded === false) {
            throw new \InvalidArgumentException('Invalid JSON content');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'cipi-auth-');
        if ($tmp === false) {
            throw new \RuntimeException('Could not create temp file for auth.json');
        }

        try {
            if (file_put_contents($tmp, $encoded) === false) {
                throw new \RuntimeException('Could not write temp auth.json');
            }
            chmod($tmp, 0640);

            $result = $this->cli->run(
                'auth edit ' . escapeshellarg($app) . ' --file=' . escapeshellarg($tmp)
            );
            if ($result['exit_code'] !== 0) {
                $detail = trim($result['output'] ?? '');
                throw new \RuntimeException($detail !== '' ? $detail : 'cipi auth edit failed');
            }
        } finally {
            @unlink($tmp);
        }

        return $this->show($app);
    }

    /**
     * @return array{app: string, deleted: bool}
     */
    public function delete(string $app): array
    {
        $this->assertApp($app);

        $result = $this->cli->run('auth delete ' . escapeshellarg($app) . ' --force');
        if ($result['exit_code'] !== 0) {
            $detail = trim($result['output'] ?? '');
            throw new \RuntimeException($detail !== '' ? $detail : 'cipi auth delete failed');
        }

        return ['app' => $app, 'deleted' => true];
    }

    protected function assertApp(string $app): void
    {
        if (! $this->validator->appExists($app)) {
            throw new \RuntimeException("App '{$app}' not found");
        }
    }

    /**
     * @return array|list
     */
    protected function decodeJson(string $output): array
    {
        $plain = preg_replace('/\x1b\[[0-9;]*m/', '', $output) ?? $output;
        $plain = trim($plain);
        if (! preg_match('/[\{\[].*[\}\]]/s', $plain, $m)) {
            throw new \RuntimeException('Could not parse auth.json from cipi output');
        }

        $decoded = json_decode($m[0], true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('Invalid auth.json from cipi');
        }

        return $decoded;
    }
}
