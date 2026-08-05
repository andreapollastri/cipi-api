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
        'app logs read',
        'app suspend',
        'app unsuspend',
        'app artisan',
        'app run',
        'app deploy-config',
        'app env',
        'app webhook recreate',
        'auth create',
        'auth edit',
        'auth show',
        'auth delete',
        'basicauth enable',
        'basicauth disable',
        'basicauth status',
        'deploy ',
        'alias add',
        'alias remove',
        'www add',
        'www force-to-root',
        'www force-from-root',
        'www clear',
        'www status',
        'ssl install',
        'ssl force',
        'db list',
        'db engines',
        'db install',
        'db default',
        'db create',
        'db delete',
        'db backup',
        'db restore',
        'db password',
        'php list',
        'php install',
        'php remove',
        'ssh list',
        'ssh add',
        'ssh remove',
        'status',
        'service list',
        'service restart',
        'smtp status',
        'smtp configure',
        'smtp enable',
        'smtp disable',
        'smtp test',
        'smtp delete',
        'health list',
        'health set',
        'health unset',
        'health check',
        'api ip-whitelist',
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
