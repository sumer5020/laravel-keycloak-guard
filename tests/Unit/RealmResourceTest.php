<?php

namespace KeycloakGuard\Tests\Unit;

use Illuminate\Http\Request;
use KeycloakGuard\Http\Resources\RealmResource;
use KeycloakGuard\Tests\TestCase;
use Mockery;

class RealmResourceTest extends TestCase
{
    public function test_realm_resource_returns_config_defaults(): void
    {
        config(['keycloak.realm' => 'test-realm']);
        config(['keycloak.base_url' => 'https://keycloak.test']);

        $resource = new RealmResource([]);
        $data = $resource->toArray(Mockery::mock(Request::class));

        $this->assertEquals('test-realm', $data['realm']);
        $this->assertEquals('https://keycloak.test', $data['base_url']);
        $this->assertEquals('https://keycloak.test/realms/test-realm/protocol/openid-connect/certs', $data['jwks_uri']);
        $this->assertEquals('https://keycloak.test/realms/test-realm', $data['issuer']);
    }

    public function test_realm_resource_overrides_with_provided_data(): void
    {
        $resource = new RealmResource([
            'realm' => 'overridden-realm',
            'base_url' => 'https://overridden.test',
            'jwks_uri' => 'https://overridden.test/certs',
            'issuer' => 'https://overridden.test/issuer',
        ]);

        $data = $resource->toArray(Mockery::mock(Request::class));

        $this->assertEquals('overridden-realm', $data['realm']);
        $this->assertEquals('https://overridden.test', $data['base_url']);
        $this->assertEquals('https://overridden.test/certs', $data['jwks_uri']);
        $this->assertEquals('https://overridden.test/issuer', $data['issuer']);
    }

    public function test_realm_resource_prefers_explicit_jwks_uri_config(): void
    {
        config(['keycloak.realm' => 'test-realm']);
        config(['keycloak.base_url' => 'https://keycloak.test']);
        config(['keycloak.jwks_uri' => 'https://custom.test/certs']);

        $resource = new RealmResource([]);
        $data = $resource->toArray(Mockery::mock(Request::class));

        $this->assertEquals('https://custom.test/certs', $data['jwks_uri']);
    }
}
