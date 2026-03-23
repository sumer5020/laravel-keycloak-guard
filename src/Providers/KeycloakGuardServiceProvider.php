<?php

namespace KeycloakGuard\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use KeycloakGuard\Commands\KeycloakDoctorCommand;
use KeycloakGuard\Guards\KeycloakGuard;
use KeycloakGuard\Middleware\CheckOrganizationRole;
use KeycloakGuard\Middleware\CheckRole;
use KeycloakGuard\Middleware\ResolveOrganization;
use KeycloakGuard\Services\JwksService;
use KeycloakGuard\Services\OrganizationService;
use KeycloakGuard\Services\TokenService;

class KeycloakGuardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/keycloak.php', 'keycloak');

        $this->app->singleton(JwksService::class);

        $this->app->singleton(TokenService::class, function ($app) {
            return new TokenService($app->make(JwksService::class));
        });

        $this->app->singleton(OrganizationService::class, function ($app) {
            return new OrganizationService($app->make('request'));
        });
    }

    public function boot(): void
    {
        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                KeycloakDoctorCommand::class,
            ]);
        }

        // Publish config
        $this->publishes([
            __DIR__.'/../../config/keycloak.php' => config_path('keycloak.php'),
        ], 'keycloak-config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/../../database/migrations/' => database_path('migrations'),
        ], 'keycloak-migrations');

        // Register the Keycloak guard driver
        Auth::extend('keycloak', function ($app, $name, array $config) {
            return new KeycloakGuard(
                Auth::createUserProvider($config['provider']),
                $app->make('request'),
                $app->make(TokenService::class),
                $app->make(OrganizationService::class),
            );
        });

        // Register middleware aliases (Laravel 11+ style — users add via bootstrap/app.php)
        $this->registerMiddlewareAliases();
    }

    private function registerMiddlewareAliases(): void
    {
        $router = $this->app['router'];

        $router->aliasMiddleware('keycloak.role', CheckRole::class);
        $router->aliasMiddleware('keycloak.org', ResolveOrganization::class);
        $router->aliasMiddleware('keycloak.org.role', CheckOrganizationRole::class);
    }
}
