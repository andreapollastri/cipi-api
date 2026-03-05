<?php

namespace CipiApi;

use CipiApi\Console\Commands\CipiTokenCreate;
use CipiApi\Console\Commands\CipiTokenList;
use CipiApi\Console\Commands\CipiTokenRevoke;
use CipiApi\Console\Commands\SeedApiUser;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

class CipiApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/cipi.php', 'cipi');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        if (class_exists(\Laravel\Mcp\Facades\Mcp::class)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/mcp.php');
        }
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'cipi');

        if ($this->app->runningInConsole()) {
            $this->commands([
                CipiTokenCreate::class,
                CipiTokenList::class,
                CipiTokenRevoke::class,
                SeedApiUser::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/cipi.php' => config_path('cipi.php'),
            ], 'cipi-config');

            $this->publishes([
                __DIR__ . '/../public/api-docs' => public_path('api-docs'),
            ], 'cipi-assets');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/cipi'),
            ], 'cipi-views');
        }

        $router = $this->app['router'];
        $router->aliasMiddleware('abilities', CheckAbilities::class);
        $router->aliasMiddleware('ability', CheckForAnyAbility::class);
    }
}
