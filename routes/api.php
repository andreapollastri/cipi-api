<?php

use CipiApi\Http\Controllers\AliasController;
use CipiApi\Http\Controllers\AppLogsController;
use CipiApi\Http\Controllers\AppController;
use CipiApi\Http\Controllers\BasicAuthController;
use CipiApi\Http\Controllers\DbController;
use CipiApi\Http\Controllers\DeployController;
use CipiApi\Http\Controllers\JobController;
use CipiApi\Http\Controllers\SslController;
use CipiApi\Http\Controllers\StatusController;
use CipiApi\Http\Controllers\WwwController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->middleware(['auth:sanctum'])->group(function () {
    // Apps
    Route::get('/apps', [AppController::class, 'list'])->middleware('ability:apps-view');
    Route::get('/apps/{name}', [AppController::class, 'show'])->middleware('ability:apps-view');
    Route::get('/apps/{name}/logs', [AppLogsController::class, 'show'])->middleware('ability:apps-view');
    Route::post('/apps', [AppController::class, 'create'])->middleware('ability:apps-create');
    Route::put('/apps/{name}', [AppController::class, 'edit'])->middleware('ability:apps-edit');
    Route::delete('/apps/{name}', [AppController::class, 'delete'])->middleware('ability:apps-delete');
    Route::post('/apps/{name}/suspend', [AppController::class, 'suspend'])->middleware('ability:apps-suspend');
    Route::post('/apps/{name}/unsuspend', [AppController::class, 'unsuspend'])->middleware('ability:apps-suspend');
    Route::get('/apps/{name}/basicauth', [BasicAuthController::class, 'status'])->middleware('ability:apps-basicauth');
    Route::post('/apps/{name}/basicauth/enable', [BasicAuthController::class, 'enable'])->middleware('ability:apps-basicauth');
    Route::post('/apps/{name}/basicauth/disable', [BasicAuthController::class, 'disable'])->middleware('ability:apps-basicauth');

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
    Route::get('/dbs', [DbController::class, 'list'])->middleware('ability:dbs-view');
    Route::post('/dbs', [DbController::class, 'create'])->middleware('ability:dbs-create');
    Route::delete('/dbs/{name}', [DbController::class, 'delete'])->middleware('ability:dbs-delete');
    Route::post('/dbs/{name}/backup', [DbController::class, 'backup'])->middleware('ability:dbs-manage');
    Route::post('/dbs/{name}/restore', [DbController::class, 'restore'])->middleware('ability:dbs-manage');
    Route::post('/dbs/{name}/password', [DbController::class, 'password'])->middleware('ability:dbs-manage');

    // Jobs
    Route::get('/jobs/{id}', [JobController::class, 'show']);

    // Server
    Route::get('/status', [StatusController::class, 'show'])->middleware('ability:status-view');
});
