<?php

use CipiApi\Http\Controllers\AliasController;
use CipiApi\Http\Controllers\AppLogsController;
use CipiApi\Http\Controllers\AppController;
use CipiApi\Http\Controllers\AppRunController;
use CipiApi\Http\Controllers\ArtisanController;
use CipiApi\Http\Controllers\AuthJsonController;
use CipiApi\Http\Controllers\BasicAuthController;
use CipiApi\Http\Controllers\DbController;
use CipiApi\Http\Controllers\DeployConfigController;
use CipiApi\Http\Controllers\DeployController;
use CipiApi\Http\Controllers\EnvController;
use CipiApi\Http\Controllers\JobController;
use CipiApi\Http\Controllers\HealthController;
use CipiApi\Http\Controllers\IpWhitelistController;
use CipiApi\Http\Controllers\PhpController;
use CipiApi\Http\Controllers\ServiceController;
use CipiApi\Http\Controllers\SmtpController;
use CipiApi\Http\Controllers\SshController;
use CipiApi\Http\Controllers\SslController;
use CipiApi\Http\Controllers\StatusController;
use CipiApi\Http\Controllers\WwwController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->middleware(['cipi.ip', 'auth:sanctum'])->group(function () {
    // Apps
    Route::get('/apps', [AppController::class, 'list'])->middleware('ability:apps-view');
    Route::get('/apps/{name}', [AppController::class, 'show'])->middleware('ability:apps-view');
    Route::get('/apps/{name}/logs', [AppLogsController::class, 'show'])->middleware('ability:apps-view');
    Route::post('/apps', [AppController::class, 'create'])->middleware('ability:apps-create');
    Route::put('/apps/{name}', [AppController::class, 'edit'])->middleware('ability:apps-edit');
    Route::post('/apps/{name}/webhook/recreate', [AppController::class, 'webhookRecreate'])->middleware('ability:apps-edit');
    Route::delete('/apps/{name}', [AppController::class, 'delete'])->middleware('ability:apps-delete');
    Route::post('/apps/{name}/suspend', [AppController::class, 'suspend'])->middleware('ability:apps-suspend');
    Route::post('/apps/{name}/unsuspend', [AppController::class, 'unsuspend'])->middleware('ability:apps-suspend');
    Route::get('/apps/{name}/basicauth', [BasicAuthController::class, 'status'])->middleware('ability:apps-basicauth');
    Route::post('/apps/{name}/basicauth/enable', [BasicAuthController::class, 'enable'])->middleware('ability:apps-basicauth');
    Route::post('/apps/{name}/basicauth/disable', [BasicAuthController::class, 'disable'])->middleware('ability:apps-basicauth');

    // App .env (key/value — requires Cipi CLI ≥ 5.0.3)
    Route::get('/apps/{name}/env', [EnvController::class, 'show'])->middleware('ability:apps-env');
    Route::put('/apps/{name}/env', [EnvController::class, 'update'])->middleware('ability:apps-env');

    // Shared auth.json (Composer / structured JSON — not HTTP Basic Auth)
    Route::get('/apps/{name}/auth', [AuthJsonController::class, 'show'])->middleware('ability:apps-auth');
    Route::post('/apps/{name}/auth', [AuthJsonController::class, 'create'])->middleware('ability:apps-auth');
    Route::put('/apps/{name}/auth', [AuthJsonController::class, 'update'])->middleware('ability:apps-auth');
    Route::delete('/apps/{name}/auth', [AuthJsonController::class, 'delete'])->middleware('ability:apps-auth');

    // Artisan (async job)
    Route::post('/apps/{name}/artisan', [ArtisanController::class, 'run'])->middleware('ability:apps-artisan');

    // Whitelisted non-interactive app commands (async — requires Cipi CLI ≥ 5.0.3)
    Route::get('/run-commands', [AppRunController::class, 'commands'])->middleware('ability:apps-run');
    Route::post('/apps/{name}/run', [AppRunController::class, 'run'])->middleware('ability:apps-run');

    // Structured deploy.php options (sync — not raw PHP upload; requires Cipi CLI ≥ 5.0.3)
    Route::get('/apps/{name}/deploy-config', [DeployConfigController::class, 'show'])->middleware('ability:apps-deploy-config');
    Route::put('/apps/{name}/deploy-config', [DeployConfigController::class, 'update'])->middleware('ability:apps-deploy-config');

    // Aliases
    Route::get('/apps/{name}/aliases', [AliasController::class, 'list'])->middleware('ability:aliases-view');
    Route::post('/apps/{name}/aliases/{alias}', [AliasController::class, 'create'])->middleware('ability:aliases-create');
    Route::delete('/apps/{name}/aliases/{alias}', [AliasController::class, 'delete'])->middleware('ability:aliases-delete');

    // WWW / apex redirects (Cipi 4.8+)
    Route::get('/apps/{name}/www', [WwwController::class, 'status'])->middleware('ability:www-manage');
    Route::post('/apps/{name}/www/add', [WwwController::class, 'add'])->middleware('ability:www-manage');
    Route::post('/apps/{name}/www/force-to-root', [WwwController::class, 'forceToRoot'])->middleware('ability:www-manage');
    Route::post('/apps/{name}/www/force-from-root', [WwwController::class, 'forceFromRoot'])->middleware('ability:www-manage');
    Route::post('/apps/{name}/www/clear', [WwwController::class, 'clear'])->middleware('ability:www-manage');

    // Deploy
    Route::post('/apps/{name}/deploy', [DeployController::class, 'deploy'])->middleware('ability:deploy-manage');
    Route::post('/apps/{name}/deploy/rollback', [DeployController::class, 'rollback'])->middleware('ability:deploy-manage');
    Route::post('/apps/{name}/deploy/unlock', [DeployController::class, 'unlock'])->middleware('ability:deploy-manage');

    // SSL
    Route::post('/apps/{name}/ssl', [SslController::class, 'install'])->middleware('ability:ssl-manage');
    Route::post('/apps/{name}/ssl/force', [SslController::class, 'force'])->middleware('ability:ssl-manage');

    // Databases
    Route::get('/dbs/engines', [DbController::class, 'engines'])->middleware('ability:dbs-view');
    Route::post('/dbs/engines/install', [DbController::class, 'installEngine'])->middleware('ability:dbs-manage');
    Route::get('/dbs', [DbController::class, 'list'])->middleware('ability:dbs-view');
    Route::post('/dbs', [DbController::class, 'create'])->middleware('ability:dbs-create');
    Route::post('/dbs/{name}/backup', [DbController::class, 'backup'])->middleware('ability:dbs-manage');
    Route::post('/dbs/{name}/restore', [DbController::class, 'restore'])->middleware('ability:dbs-manage');
    Route::post('/dbs/{name}/password', [DbController::class, 'password'])->middleware('ability:dbs-manage');

    // PHP versions (Cipi CLI ≥ 5.0.6)
    Route::get('/php', [PhpController::class, 'list'])->middleware('ability:php-view');
    Route::post('/php/install', [PhpController::class, 'install'])->middleware('ability:php-manage');

    // SSH keys — cipi user (Cipi CLI ≥ 5.0.6)
    Route::get('/ssh/keys', [SshController::class, 'list'])->middleware('ability:ssh-view');
    Route::post('/ssh/keys', [SshController::class, 'add'])->middleware('ability:ssh-manage');
    Route::delete('/ssh/keys/{n}', [SshController::class, 'remove'])->middleware('ability:ssh-manage')
        ->where('n', '[0-9]+');

    // System services (Cipi CLI ≥ 5.0.6)
    Route::get('/services', [ServiceController::class, 'list'])->middleware('ability:services-view');
    Route::post('/services/{name}/restart', [ServiceController::class, 'restart'])
        ->middleware('ability:services-manage')
        ->where('name', '[a-z0-9.-]+');

    // SMTP notifications (Cipi CLI ≥ 5.0.7)
    Route::get('/smtp', [SmtpController::class, 'show'])->middleware('ability:smtp-view');
    Route::put('/smtp', [SmtpController::class, 'update'])->middleware('ability:smtp-manage');
    Route::post('/smtp/enable', [SmtpController::class, 'enable'])->middleware('ability:smtp-manage');
    Route::post('/smtp/disable', [SmtpController::class, 'disable'])->middleware('ability:smtp-manage');
    Route::post('/smtp/test', [SmtpController::class, 'test'])->middleware('ability:smtp-manage');
    Route::delete('/smtp', [SmtpController::class, 'destroy'])->middleware('ability:smtp-manage');

    // App HTTP healthchecks (Cipi CLI ≥ 5.0.7 for --json)
    Route::get('/health', [HealthController::class, 'list'])->middleware('ability:health-view');
    Route::get('/apps/{name}/health', [HealthController::class, 'show'])->middleware('ability:health-view');
    Route::put('/apps/{name}/health', [HealthController::class, 'update'])->middleware('ability:health-manage');
    Route::delete('/apps/{name}/health', [HealthController::class, 'destroy'])->middleware('ability:health-manage');
    Route::post('/apps/{name}/health/check', [HealthController::class, 'check'])->middleware('ability:health-view');

    // Jobs
    Route::get('/jobs/{id}', [JobController::class, 'show']);

    // Server
    Route::get('/status', [StatusController::class, 'show'])->middleware('ability:status-view');

    // API client IP whitelist (Cipi CLI ≥ 5.0.8) — default * = allow all
    Route::get('/ip-whitelist', [IpWhitelistController::class, 'show'])->middleware('ability:ip-whitelist-view');
    Route::put('/ip-whitelist', [IpWhitelistController::class, 'update'])->middleware('ability:ip-whitelist-manage');
    Route::post('/ip-whitelist', [IpWhitelistController::class, 'add'])->middleware('ability:ip-whitelist-manage');
    Route::delete('/ip-whitelist', [IpWhitelistController::class, 'remove'])->middleware('ability:ip-whitelist-manage');
    Route::post('/ip-whitelist/allow-all', [IpWhitelistController::class, 'allowAll'])->middleware('ability:ip-whitelist-manage');
});
