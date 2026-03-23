<?php

namespace KeycloakGuard\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use KeycloakGuard\Testing\ActingAsKeycloak;
use KeycloakGuard\Tests\Fixtures\User;
use KeycloakGuard\Tests\TestCase;

class GuardAuthenticationTest extends TestCase
{
    use ActingAsKeycloak;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        User::create([
            'keycloak_id' => 'test-user-sub-123',
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Register a test route
        Route::middleware('auth:api')->get('/api/me', fn () => response()->json([
            'id' => Auth::id(),
            'email' => Auth::user()->email,
        ]));
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
    }

    public function test_authenticated_user_can_access_protected_route(): void
    {
        $this->withKeycloakToken(['sub' => 'test-user-sub-123'])
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonFragment(['email' => 'test@example.com']);
    }

    public function test_token_is_appended_to_user(): void
    {
        $this->withKeycloakToken(['sub' => 'test-user-sub-123', 'email' => 'test@example.com']);

        Route::middleware('auth:api')->get('/api/token-check', function () {
            return response()->json(['has_token' => Auth::user()->token !== null]);
        });

        $this->getJson('/api/token-check')->assertOk()->assertJson(['has_token' => true]);
    }

    public function test_auth_payload_returns_decoded_token(): void
    {
        Route::middleware('auth:api')->get('/api/payload', function () {
            $payload = Auth::guard('api')->payload();

            return response()->json(['sub' => $payload->sub]);
        });

        $this->withKeycloakToken(['sub' => 'test-user-sub-123'])
            ->getJson('/api/payload')
            ->assertOk()
            ->assertJson(['sub' => 'test-user-sub-123']);
    }

    public function test_auth_roles_returns_realm_roles(): void
    {
        Route::middleware('auth:api')->get('/api/roles', function () {
            return response()->json(['roles' => Auth::guard('api')->roles()]);
        });

        $this->withKeycloakToken([
            'sub' => 'test-user-sub-123',
            'realm_access' => ['roles' => ['admin', 'user']],
        ])->getJson('/api/roles')
            ->assertOk()
            ->assertJsonFragment(['roles' => ['admin', 'user']]);
    }
}
