<?php

use CipiApi\Http\Controllers\AliasController;
use CipiApi\Http\Controllers\AppController;
use CipiApi\Http\Controllers\DeployController;
use CipiApi\Http\Controllers\JobController;
use CipiApi\Http\Controllers\SslController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->middleware(['auth:sanctum'])->group(function () {
    // Apps
    Route::get('/apps', [AppController::class, 'list'])->middleware('ability:apps-view');
    Route::get('/apps/{name}', [AppController::class, 'show'])->middleware('ability:apps-view');
    Route::post('/apps', [AppController::class, 'create'])->middleware('ability:apps-create');
    Route::put('/apps/{name}', [AppController::class, 'edit'])->middleware('ability:apps-edit');
    Route::delete('/apps/{name}', [AppController::class, 'delete'])->middleware('ability:apps-delete');

    // Aliases
    Route::get('/apps/{name}/aliases', [AliasController::class, 'list'])->middleware('ability:aliases-view');
    Route::post('/apps/{name}/aliases/{alias}', [AliasController::class, 'create'])->middleware('ability:aliases-create');
    Route::delete('/apps/{name}/aliases/{alias}', [AliasController::class, 'delete'])->middleware('ability:aliases-delete');

    // Deploy
    Route::post('/apps/{name}/deploy', [DeployController::class, 'deploy'])->middleware('ability:deploy-manage');
    Route::post('/apps/{name}/deploy/rollback', [DeployController::class, 'rollback'])->middleware('ability:deploy-manage');
    Route::post('/apps/{name}/deploy/unlock', [DeployController::class, 'unlock'])->middleware('ability:deploy-manage');

    // SSL
    Route::post('/apps/{name}/ssl', [SslController::class, 'install'])->middleware('ability:ssl-manage');

    // Jobs
    Route::get('/jobs/{id}', [JobController::class, 'show']);
});
