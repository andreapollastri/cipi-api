<?php

return [
    'apps_json' => env('CIPI_APPS_JSON', '/etc/cipi/apps.json'),

    // Client IP allowlist for API + MCP (Cipi CLI ≥ 5.0.8). "*" or missing file = allow all.
    'ip_whitelist_file' => env('CIPI_API_IP_WHITELIST', '/etc/cipi/api-ip-whitelist'),

    // Installable / assignable PHP versions (Cipi CLI ≥ 4.5.4 — Deployer 8 requires ≥ 8.3).
    'php_versions' => ['8.3', '8.4', '8.5'],

    /*
    | Canonical REST token abilities (ability => description).
    | Consumed by `php artisan cipi:token-abilities` — `cipi api token create` reads this list.
    */
    'token_abilities' => [
        'apps-view' => 'Read apps',
        'apps-create' => 'Create apps',
        'apps-edit' => 'Edit apps (includes webhook recreate)',
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
        'dbs-manage' => 'Backup, restore, DB password, install/set default engines',
        'php-view' => 'List installed PHP versions',
        'php-manage' => 'Install / remove PHP versions',
        'ssh-view' => 'List SSH keys (cipi user)',
        'ssh-manage' => 'Add / remove SSH keys (cipi user)',
        'services-view' => 'List system services',
        'services-manage' => 'Restart system services',
        'smtp-view' => 'View SMTP notification settings',
        'smtp-manage' => 'Configure SMTP, test, enable/disable',
        'health-view' => 'View app HTTP healthchecks',
        'health-manage' => 'Set / unset / run app HTTP healthchecks',
        'status-view' => 'Server status',
        'ip-whitelist-view' => 'View API IP whitelist',
        'ip-whitelist-manage' => 'Manage API IP whitelist',
        'mcp-access' => 'MCP server',
    ],

    'reserved_usernames' => [
        'root', 'admin', 'www', 'nginx', 'mysql', 'mariadb', 'redis',
        'git', 'deploy', 'cipi', 'ubuntu', 'debian', 'supervisor',
        'nobody', 'postfix', 'sshd', 'clamav', 'daemon', 'bin', 'sys',
    ],
];
