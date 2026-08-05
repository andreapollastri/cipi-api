<?php

namespace CipiApi\Services;

/**
 * Structured deploy.php options via `sudo cipi app deploy-config` (Cipi ≥ 5.0.3).
 * Not a raw PHP file editor — knobs live in apps.json and regenerate the template.
 */
class CipiAppDeployConfigService
{
    public function __construct(
        protected CipiCliService $cli,
        protected CipiValidationService $validator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function show(string $app): array
    {
        $this->assertLaravelApp($app);

        $result = $this->cli->run('app deploy-config ' . escapeshellarg($app) . ' --json');
        if ($result['exit_code'] !== 0) {
            $detail = trim($result['output'] ?? '');
            throw new \RuntimeException($detail !== '' ? $detail : 'cipi app deploy-config failed');
        }

        return $this->decodeJsonObject($result['output'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function update(string $app, array $options): array
    {
        $this->assertLaravelApp($app);

        $flags = $this->buildFlags($options);
        if ($flags === []) {
            throw new \InvalidArgumentException('Nothing to change');
        }

        $cmd = 'app deploy-config ' . escapeshellarg($app) . ' ' . implode(' ', $flags) . ' --json';
        $result = $this->cli->run($cmd);
        if ($result['exit_code'] !== 0) {
            $detail = trim($result['output'] ?? '');
            throw new \RuntimeException($detail !== '' ? $detail : 'cipi app deploy-config update failed');
        }

        return $this->decodeJsonObject($result['output'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<string>
     */
    public function buildFlags(array $options): array
    {
        $flags = [];

        if (array_key_exists('keep_releases', $options) && $options['keep_releases'] !== null) {
            $kr = (int) $options['keep_releases'];
            if ($kr < 1 || $kr > 20) {
                throw new \InvalidArgumentException('keep_releases must be between 1 and 20');
            }
            $flags[] = '--keep-releases=' . escapeshellarg((string) $kr);
        }

        foreach ([
            'migrate' => 'migrate',
            'optimize' => 'optimize',
            'storage_link' => 'storage-link',
            'queue_restart' => 'queue-restart',
            'horizon_terminate' => 'horizon-terminate',
            'predeploy_snapshot' => 'predeploy-snapshot',
        ] as $key => $flag) {
            if (! array_key_exists($key, $options) || $options[$key] === null) {
                continue;
            }
            $flags[] = $options[$key] ? '--' . $flag : '--no-' . $flag;
        }

        if (array_key_exists('node_build', $options)) {
            $nb = $options['node_build'];
            if ($nb === null || $nb === '') {
                $flags[] = '--no-node-build';
            } else {
                if (! is_string($nb)) {
                    throw new \InvalidArgumentException('node_build must be a string or null');
                }
                $flags[] = '--node-build=' . escapeshellarg($nb);
            }
        }

        if (array_key_exists('extra_artisan', $options)) {
            $extra = $options['extra_artisan'];
            if ($extra === null || $extra === []) {
                $flags[] = '--clear-extra-artisan';
            } elseif (is_array($extra)) {
                $clean = [];
                foreach ($extra as $cmd) {
                    if (! is_string($cmd) || ! preg_match('/^[a-zA-Z0-9:_-]+$/', $cmd)) {
                        throw new \InvalidArgumentException("Invalid extra_artisan command: {$cmd}");
                    }
                    if (strtolower($cmd) === 'tinker') {
                        throw new \InvalidArgumentException('tinker is not allowed in extra_artisan');
                    }
                    $clean[] = $cmd;
                }
                $flags[] = '--extra-artisan=' . escapeshellarg(implode(',', $clean));
            } else {
                throw new \InvalidArgumentException('extra_artisan must be an array of command names');
            }
        }

        return $flags;
    }

    protected function assertLaravelApp(string $app): void
    {
        if (! $this->validator->appExists($app)) {
            throw new \RuntimeException("App '{$app}' not found");
        }
        if ($this->validator->isCustomApp($app)) {
            throw new \RuntimeException("App '{$app}' is a custom app and has no deploy.php recipe");
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeJsonObject(string $output): array
    {
        $plain = preg_replace('/\x1b\[[0-9;]*m/', '', $output) ?? $output;
        if (! preg_match('/\{.*\}/s', $plain, $m)) {
            throw new \RuntimeException('Could not parse deploy-config JSON from cipi output');
        }

        $decoded = json_decode($m[0], true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('Invalid deploy-config JSON from cipi');
        }

        return $decoded;
    }
}
