<?php

namespace CipiApi\Services;

/**
 * Run whitelisted non-interactive binaries as the app user via `sudo cipi app run`.
 * Requires Cipi CLI ≥ 5.0.3. Interactive tools (nano, vim, less, tinker, ssh, bash, …) are excluded.
 */
class CipiAppRunService
{
    /**
     * Must stay in sync with `_APP_RUN_COMMANDS` in Cipi `lib/app.sh`.
     *
     * @var list<string>
     */
    public const ALLOWED_COMMANDS = [
        'composer', 'npm', 'npx', 'yarn', 'pnpm',
        'ls', 'll', 'cat', 'head', 'tail', 'pwd', 'du', 'wc', 'file', 'stat', 'tree',
        'mkdir', 'touch', 'cp', 'mv', 'ln', 'unlink', 'rm', 'rmdir',
        'tar', 'unzip', 'zip', 'gzip', 'gunzip',
        'git', 'php', 'node',
        'which', 'realpath', 'basename', 'dirname', 'find',
    ];

    public function __construct(
        protected CipiValidationService $validator,
    ) {}

    /**
     * @return list<string>
     */
    public function allowedCommands(): array
    {
        return self::ALLOWED_COMMANDS;
    }

    public function validateCommand(string $command): void
    {
        $command = trim($command);
        if ($command === '') {
            throw new \InvalidArgumentException('Command is required');
        }

        if (preg_match('/[;&|`$()<>\n\r\\\\]/', $command)) {
            throw new \InvalidArgumentException('Command contains disallowed characters');
        }

        $parts = preg_split('/\s+/', $command) ?: [];
        if ($parts === [] || $parts[0] === '') {
            throw new \InvalidArgumentException('Command is required');
        }

        $bin = strtolower($parts[0]);
        if (! in_array($bin, self::ALLOWED_COMMANDS, true)) {
            throw new \InvalidArgumentException(
                "Command not allowed: {$bin}. Use GET /api/run-commands for the whitelist."
            );
        }

        foreach ($parts as $part) {
            if (! preg_match('/^[a-zA-Z0-9:_\/.@%=+,~-]+$/', $part)) {
                throw new \InvalidArgumentException("Invalid argument: {$part}");
            }
            if (str_contains($part, '..')) {
                throw new \InvalidArgumentException('Path traversal (..) is not allowed');
            }
        }

        $this->assertNonInteractiveFlags($bin, array_slice($parts, 1));
    }

    public function buildCipiCommand(string $app, string $command): string
    {
        $parts = preg_split('/\s+/', trim($command)) ?: [];
        $escaped = array_map(static fn (string $part) => escapeshellarg($part), $parts);

        return 'app run ' . escapeshellarg($app) . ' ' . implode(' ', $escaped);
    }

    public function assertApp(string $app): void
    {
        if (! $this->validator->appExists($app)) {
            throw new \RuntimeException("App '{$app}' not found");
        }
    }

    /**
     * @param  list<string>  $args
     */
    protected function assertNonInteractiveFlags(string $bin, array $args): void
    {
        $blocked = match ($bin) {
            'find' => ['-exec', '-execdir', '-ok', '-okdir', '-delete'],
            'rm' => ['--no-preserve-root', '/', '/home', '/home/'],
            'php' => ['-a', '--interactive', '-S', '--server', '-r', '--run', '-t'],
            'node' => ['-e', '--eval', '-p', '--print', '-i', '--interactive', '--inspect', '--inspect-brk'],
            'tail' => ['-f', '--follow'],
            'composer' => ['shell', 'browse', 'fund'],
            'npm', 'npx', 'yarn', 'pnpm' => ['explore', 'init', 'login', 'adduser', 'edit'],
            default => [],
        };

        foreach ($args as $i => $arg) {
            if (in_array($arg, $blocked, true)) {
                throw new \InvalidArgumentException("Flag/subcommand not allowed: {$arg}");
            }
            if ($bin === 'tail' && str_starts_with($arg, '--follow=')) {
                throw new \InvalidArgumentException('tail --follow is interactive; use app logs instead');
            }
            if ($bin === 'git') {
                if (in_array($arg, ['-i', '--interactive', '-p', '--patch', '--edit'], true)) {
                    throw new \InvalidArgumentException("git interactive flag not allowed: {$arg}");
                }
                $prev = $args[$i - 1] ?? null;
                if (in_array($prev, ['rebase', 'add', 'checkout', 'reset', 'stash', 'clean', 'commit'], true)
                    && in_array($arg, ['-i', '--interactive', '-p', '--patch', '-e', '--edit'], true)) {
                    throw new \InvalidArgumentException("git {$prev} {$arg} is interactive; not allowed");
                }
            }
        }
    }
}
