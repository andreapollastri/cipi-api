<?php

namespace CipiApi;

use CipiApi\Console\Commands\CipiTokenCreate;
use CipiApi\Console\Commands\CipiTokenList;
use CipiApi\Console\Commands\CipiTokenRevoke;
use CipiApi\Console\Commands\SeedApiUser;
use CipiApi\Exceptions\AppsJsonUnreadableException;
use CipiApi\Exceptions\DisallowedCipiCommandException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;
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
        // No web login page: guests see the public welcome/docs routes; APIs use tokens.
        // Default auth middleware calls route('login') and throws if missing. For API/JSON,
        // return 401. For browser requests without a login route, send guests to welcome (/).
        Authenticate::redirectUsing(function ($request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            return Route::has('login') ? route('login') : '/';
        });

        $exceptionHandler = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class);

        $exceptionHandler->renderable(
            function (AppsJsonUnreadableException $e, $request) {
                if ($request && ($request->expectsJson() || $request->is('api/*'))) {
                    return response()->json(['error' => $e->getMessage()], 503);
                }
            }
        );

        $exceptionHandler->renderable(
            function (DisallowedCipiCommandException $e, $request) {
                if ($request && ($request->expectsJson() || $request->is('api/*'))) {
                    return response()->json(['error' => $e->getMessage()], 500);
                }
            }
        );

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
