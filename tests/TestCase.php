<?php

namespace KeycloakGuard\Tests;

use KeycloakGuard\Providers\KeycloakGuardServiceProvider;
use KeycloakGuard\Tests\Fixtures\User;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();
        $this->setUpConfig();
    }

    protected function getPackageProviders($app): array
    {
        return [KeycloakGuardServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('auth.defaults.guard', 'api');
        $app['config']->set('auth.guards.api', [
            'driver' => 'keycloak',
            'provider' => 'users',
        ]);
        $app['config']->set('auth.providers.users', [
            'driver' => 'eloquent',
            'model' => User::class,
        ]);
    }

    protected function setUpDatabase(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/migrations');
    }

    protected function setUpConfig(): void
    {
        config([
            'keycloak.validate.issuer' => true,
            'keycloak.validate.audience' => true,
            'keycloak.realm_public_key' => null,
            'keycloak.token_encryption_algorithm' => 'RS256',
            'keycloak.load_user_from_database' => true,
            'keycloak.user_provider_credential' => 'keycloak_id',
            'keycloak.token_principal_attribute' => 'sub',
            'keycloak.append_decoded_token' => true,
            'keycloak.allowed_resources' => 'test-api',
            'keycloak.ignore_resources_validation' => true,
            'keycloak.leeway' => 0,
            'keycloak.base_url' => 'https://keycloak.test',
            'keycloak.realm' => 'test-realm',
            'keycloak.organizations.enabled' => false,
            'keycloak.organizations.header' => 'X-Organization',
            'keycloak.organizations.require' => false,
            'keycloak.organizations.sync_to_database' => false,
        ]);
    }
}
