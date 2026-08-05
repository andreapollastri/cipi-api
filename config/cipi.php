<?php

return [
    'apps_json' => env('CIPI_APPS_JSON', '/etc/cipi/apps.json'),

    'php_versions' => ['7.4', '8.0', '8.1', '8.2', '8.3', '8.4', '8.5'],

    /*
    | Canonical REST token abilities (ability => description).
    | Consumed by `php artisan cipi:token-abilities` — `cipi api token create` reads this list.
    */
    'token_abilities' => [
        'apps-view' => 'Read apps',
        'apps-create' => 'Create apps',
        'apps-edit' => 'Edit apps',
        'apps-delete' => 'Delete apps',
        'apps-suspend' => 'Suspend / unsuspend apps',
        'apps-basicauth' => 'HTTP Basic Auth',
        'apps-env' => 'Read / update app .env keys',
        'apps-auth' => 'Manage shared auth.json (not HTTP Basic Auth)',
        'apps-artisan' => 'Run Artisan commands (async jobs)',
        'apps-run' => 'Run whitelisted non-interactive app commands (async)',
        'apps-deploy-config' => 'Read / update structured deploy.php options',
        'deploy-manage' => 'Deploy, rollback, unlock',
        'ssl-manage' => 'SSL certificates',
        'aliases-view' => 'Read aliases',
        'aliases-create' => 'Add aliases',
        'aliases-delete' => 'Remove aliases',
        'www-manage' => 'WWW / apex redirects',
        'dbs-view' => 'List databases',
        'dbs-create' => 'Create databases',
        'dbs-delete' => 'Delete databases',
        'dbs-manage' => 'Backup, restore, DB password',
        'status-view' => 'Server status',
        'mcp-access' => 'MCP server',
    ],

    'reserved_usernames' => [
        'root', 'admin', 'www', 'nginx', 'mysql', 'mariadb', 'redis',
        'git', 'deploy', 'cipi', 'ubuntu', 'debian', 'supervisor',
        'nobody', 'postfix', 'sshd', 'clamav', 'daemon', 'bin', 'sys',
    ],
];
