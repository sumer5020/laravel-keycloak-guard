<?php

namespace KeycloakGuard\Testing;

use KeycloakGuard\Services\TokenService;
use Mockery;

/**
 * Add this trait to your test classes for easy Keycloak authentication mocking.
 *
 * Usage:
 *   use KeycloakGuard\Testing\ActingAsKeycloak;
 *
 *   class MyTest extends TestCase {
 *       use ActingAsKeycloak;
 *
 *       public function test_authenticated_route(): void {
 *           $this->withKeycloakToken(['email' => 'john@acme.com'])
 *                ->getJson('/api/me')
 *                ->assertOk();
 *       }
 *   }
 */
trait ActingAsKeycloak
{
    /**
     * Authenticate as a Keycloak user with the given JWT claims.
     * Merges with sensible defaults — only specify what your test needs.
     */
    public function withKeycloakToken(array $claims = [], string $guard = 'api'): static
    {
        $payload = $this->buildDefaultPayload($claims);
        $token = $this->encodeTestToken($payload);

        $this->withToken($token);
        $this->mockTokenService($payload);

        return $this;
    }

    /**
     * Authenticate as a user belonging to specific Keycloak 26 organizations.
     * Automatically enables organizations for this test.
     *
     * Example:
     *   $this->withKeycloakOrganization([
     *       'acme-corp' => ['id' => 'org-uuid', 'name' => 'Acme Corp', 'roles' => ['admin']],
     *   ]);
     */
    public function withKeycloakOrganization(
        array $organizations,
        array $extraClaims = [],
        string $guard = 'api'
    ): static {
        config(['keycloak.organizations.enabled' => true]);

        return $this->withKeycloakToken(
            array_merge($extraClaims, ['organization' => $organizations]),
            $guard
        );
    }

    /**
     * Send the X-Organization header to select an active org (multi-org users).
     */
    public function withOrganization(string $alias): static
    {
        $header = config('keycloak.organizations.header', 'X-Organization');

        return $this->withHeader($header, $alias);
    }

    /**
     * Build a default JWT payload merged with given overrides.
     */
    protected function buildDefaultPayload(array $overrides = []): array
    {
        $now = time();

        return array_merge([
            'sub' => 'test-user-uuid-'.uniqid(),
            'iss' => rtrim(config('keycloak.base_url', 'https://keycloak.test'), '/')
                                    .'/realms/'.config('keycloak.realm', 'test'),
            'aud' => config('keycloak.allowed_resources', 'test-api'),
            'iat' => $now,
            'exp' => $now + 3600,
            'preferred_username' => 'testuser',
            'email' => 'test@example.com',
            'name' => 'Test User',
            'realm_access' => ['roles' => []],
            'resource_access' => [],
        ], $overrides);
    }

    /**
     * Build a fake JWT string (not cryptographically signed).
     * The TokenService is mocked so real validation is bypassed.
     */
    protected function encodeTestToken(array $payload): string
    {
        $header = rtrim(base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])), '=');
        $body = rtrim(base64_encode(json_encode($payload)), '=');
        $sig = rtrim(base64_encode('test-signature'), '=');

        return "{$header}.{$body}.{$sig}";
    }

    /**
     * Swap the TokenService with a mock that returns our payload without real JWT validation.
     */
    protected function mockTokenService(array $payload): void
    {
        $decoded = json_decode(json_encode($payload));

        $mock = Mockery::mock(TokenService::class);

        $mock->shouldReceive('decode')->andReturn($decoded);
        $mock->shouldReceive('validate')->andReturnNull();
        $mock->shouldReceive('getRoles')->andReturnUsing(
            function ($decoded, ?string $resource = null): array {
                $realmRoles = (array) ($decoded->realm_access->roles ?? []);
                $resourceRoles = [];

                if ($resource) {
                    $resourceRoles = (array) ($decoded->resource_access?->{$resource}?->roles ?? []);
                } else {
                    foreach ((array) ($decoded->resource_access ?? []) as $clientRoles) {
                        $resourceRoles = array_merge($resourceRoles, (array) ($clientRoles->roles ?? []));
                    }
                }

                return array_unique(array_merge($realmRoles, $resourceRoles));
            }
        );

        $this->app->instance(TokenService::class, $mock);
    }
}
