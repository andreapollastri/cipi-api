<?php

namespace CipiApi\Services;

class CipiCliService
{
    /**
     * Prefixes for `cipi <command>` strings that {@see CipiJobService} may queue.
     * Must cover every command built for {@see RunCipiCommand} (controllers + MCP tools).
     */
    public const ALLOWED_COMMANDS = [
        'app create',
        'app edit',
        'app delete',
        'deploy ',
        'alias add',
        'alias remove',
        'ssl install',
        'db list',
        'db create',
        'db delete',
        'db backup',
        'db restore',
        'db password',
    ];

    /**
     * Whether the given command string is permitted (matches a whitelisted prefix).
     */
    public function commandIsPermitted(string $command): bool
    {
        return $this->isAllowed($command);
    }

    public function run(string $command): array
    {
        if (! $this->commandIsPermitted($command)) {
            return [
                'output' => "Command not allowed: {$command}",
                'exit_code' => 1,
                'success' => false,
            ];
        }

        $fullCommand = 'sudo /usr/local/bin/cipi ' . $command . ' 2>&1';
        $output = [];
        exec($fullCommand, $output, $exitCode);

        return [
            'output' => implode("\n", $output),
            'exit_code' => $exitCode,
            'success' => $exitCode === 0,
        ];
    }

    private function isAllowed(string $command): bool
    {
        foreach (self::ALLOWED_COMMANDS as $allowed) {
            if (str_starts_with($command, $allowed)) {
                return true;
            }
        }

        return false;
    }
}
