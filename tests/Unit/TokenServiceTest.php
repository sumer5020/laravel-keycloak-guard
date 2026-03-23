<?php

namespace KeycloakGuard\Tests\Unit;

use KeycloakGuard\Exceptions\TokenException;
use KeycloakGuard\Services\JwksService;
use KeycloakGuard\Services\TokenService;
use KeycloakGuard\Tests\TestCase;
use Mockery;

class TokenServiceTest extends TestCase
{
    private TokenService $tokenService;

    protected function setUp(): void
    {
        parent::setUp();
        $jwks = Mockery::mock(JwksService::class);
        $this->tokenService = new TokenService($jwks);
    }

    public function test_validate_issuer_success(): void
    {
        config(['keycloak.validate.issuer' => true]);
        config(['keycloak.base_url' => 'https://keycloak.test']);
        config(['keycloak.realm' => 'test-realm']);

        $token = (object) ['iss' => 'https://keycloak.test/realms/test-realm'];

        // Should not throw exception
        $this->tokenService->validate($token);
        $this->assertTrue(true);
    }

    public function test_validate_issuer_fails_on_mismatch(): void
    {
        config(['keycloak.validate.issuer' => true]);
        config(['keycloak.base_url' => 'https://keycloak.test']);
        config(['keycloak.realm' => 'test-realm']);

        $token = (object) ['iss' => 'https://malicious.test/realms/test-realm'];

        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('Invalid issuer');

        $this->tokenService->validate($token);
    }

    public function test_validate_audience_success_string(): void
    {
        config(['keycloak.validate.audience' => true]);
        config(['keycloak.validate.expected_audience' => 'my-client']);

        $token = (object) [
            'iss' => 'https://keycloak.test/realms/test-realm', // valid issuer for context
            'aud' => 'my-client',
        ];

        $this->tokenService->validate($token);
        $this->assertTrue(true);
    }

    public function test_validate_audience_success_array(): void
    {
        config(['keycloak.validate.audience' => true]);
        config(['keycloak.validate.expected_audience' => 'my-client']);

        $token = (object) [
            'iss' => 'https://keycloak.test/realms/test-realm',
            'aud' => ['other-client', 'my-client'],
        ];

        $this->tokenService->validate($token);
        $this->assertTrue(true);
    }

    public function test_validate_audience_fails_on_mismatch(): void
    {
        config(['keycloak.validate.audience' => true]);
        config(['keycloak.validate.expected_audience' => 'my-client']);

        $token = (object) [
            'iss' => 'https://keycloak.test/realms/test-realm',
            'aud' => 'wrong-client',
        ];

        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('Invalid audience');

        $this->tokenService->validate($token);
    }

    public function test_get_roles_returns_realm_roles(): void
    {
        $token = json_decode(json_encode([
            'realm_access' => ['roles' => ['admin', 'user']],
            'resource_access' => [],
        ]));

        $roles = $this->tokenService->getRoles($token);

        $this->assertContains('admin', $roles);
        $this->assertContains('user', $roles);
    }

    public function test_get_roles_merges_resource_roles(): void
    {
        $token = json_decode(json_encode([
            'realm_access' => ['roles' => ['realm-admin']],
            'resource_access' => [
                'my-api' => ['roles' => ['api-editor']],
            ],
        ]));

        $all = $this->tokenService->getRoles($token);
        $specific = $this->tokenService->getRoles($token, 'my-api');

        $this->assertContains('realm-admin', $all);
        $this->assertContains('api-editor', $all);
        $this->assertContains('api-editor', $specific);
        $this->assertContains('realm-admin', $specific); // This is the expected behavior for inheritance
    }

    public function test_get_organizations_returns_empty_when_no_claim(): void
    {
        $token = json_decode(json_encode(['sub' => 'uuid']));
        $orgs = $this->tokenService->getOrganizations($token);

        $this->assertSame([], $orgs);
    }

    public function test_get_organizations_parses_org_claim(): void
    {
        $token = json_decode(json_encode([
            'organization' => [
                'acme-corp' => ['id' => 'org-uuid', 'name' => 'Acme', 'roles' => ['admin']],
            ],
        ]));

        $orgs = $this->tokenService->getOrganizations($token);

        $this->assertArrayHasKey('acme-corp', $orgs);
        $this->assertSame('org-uuid', $orgs['acme-corp']['id']);
        $this->assertContains('admin', $orgs['acme-corp']['roles']);
    }

    public function test_get_claim_returns_null_for_missing_claim(): void
    {
        $token = json_decode(json_encode(['sub' => 'test-uuid']));
        $result = $this->tokenService->getClaim($token, 'nonexistent');

        $this->assertNull($result);
    }
}
