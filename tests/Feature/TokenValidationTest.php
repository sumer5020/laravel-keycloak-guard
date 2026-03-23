<?php

namespace KeycloakGuard\Tests\Feature;

use Illuminate\Support\Facades\Route;
use KeycloakGuard\Exceptions\TokenException;
use KeycloakGuard\Services\TokenService;
use KeycloakGuard\Testing\ActingAsKeycloak;
use KeycloakGuard\Tests\TestCase;
use Mockery;

class TokenValidationTest extends TestCase
{
    use ActingAsKeycloak;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('auth:api')->get('/api/protected', fn () => response()->json(['ok' => true]));
    }

    /**
     * Test that an expired token is rejected by the guard.
     */
    public function test_expired_token_is_rejected(): void
    {
        // We mock the TokenService to throw a TokenException (Expired token)
        // ActingAsKeycloak trait's withKeycloakToken normally mocks it to succeed.

        $mock = Mockery::mock(TokenService::class);
        $mock->shouldReceive('decode')->andThrow(new TokenException('Expired token'));

        $this->app->instance(TokenService::class, $mock);

        $this->withToken('expired-token')
            ->getJson('/api/protected')
            ->assertStatus(401);
    }

    /**
     * Test that a token with invalid issuer is rejected.
     */
    public function test_invalid_issuer_is_rejected(): void
    {
        $mock = Mockery::mock(TokenService::class);
        $decoded = (object) ['iss' => 'https://wrong-issuer.com', 'sub' => '123'];

        $mock->shouldReceive('decode')->andReturn($decoded);
        $mock->shouldReceive('validate')->andThrow(new TokenException('Invalid issuer'));

        $this->app->instance(TokenService::class, $mock);

        $this->withToken('invalid-issuer-token')
            ->getJson('/api/protected')
            ->assertStatus(401);
    }
}
