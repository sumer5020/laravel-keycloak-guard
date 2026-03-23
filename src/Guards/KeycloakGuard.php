<?php

declare(strict_types=1);

namespace KeycloakGuard\Guards;

use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use KeycloakGuard\Exceptions\KeycloakGuardException;
use KeycloakGuard\Exceptions\TokenException;
use KeycloakGuard\Models\TokenUser;
use KeycloakGuard\Services\OrganizationService;
use KeycloakGuard\Services\TokenService;
use stdClass;

class KeycloakGuard implements Guard
{
    use GuardHelpers;

    private ?stdClass $decodedToken = null;

    public function __construct(
        UserProvider $provider,
        private Request $request,
        private TokenService $tokenService,
        private OrganizationService $organizationService,
    ) {
        $this->provider = $provider;
    }

    /**
     * Get the currently authenticated user.
     */
    public function user(): ?Authenticatable
    {
        if (! is_null($this->user)) {
            return $this->user;
        }

        $token = $this->extractBearerToken();

        if (! $token) {
            return null;
        }

        try {
            $this->decodedToken = $this->tokenService->decode($token);
            $this->tokenService->validate($this->decodedToken);
        } catch (TokenException|KeycloakGuardException $e) {
            Log::debug('Keycloak token validation failed', [
                'reason' => $e->getMessage(),
                'ip' => $this->request->ip(),
            ]);

            return null;
        }

        // Boot Organization service with the decoded token
        if (config('keycloak.organizations.enabled', false)) {
            $this->organizationService->boot($this->decodedToken, $this->request);
        }

        $this->user = $this->resolveUser();

        return $this->user;
    }

    /**
     * Validate user credentials (not used in token-based auth).
     */
    public function validate(array $credentials = []): bool
    {
        return false;
    }

    /**
     * Determine if the guard has a user.
     */
    public function check(): bool
    {
        return ! is_null($this->user());
    }

    /**
     * Get the decoded JWT token as a JSON string.
     * Compatible with Auth::token().
     */
    public function token(): ?string
    {
        if (! $this->decodedToken) {
            $this->user();
        }

        return $this->decodedToken
            ? json_encode($this->decodedToken)
            : null;
    }

    /**
     * Get the decoded token as a stdClass.
     */
    public function payload(): ?stdClass
    {
        if (! $this->decodedToken) {
            $this->user();
        }

        return $this->decodedToken;
    }

    /**
     * Check if the authenticated user has a specific realm or resource role.
     */
    public function hasRole(string $role, ?string $resource = null): bool
    {
        if (! $this->decodedToken) {
            $this->user();
        }

        if (! $this->decodedToken) {
            return false;
        }

        $roles = $this->tokenService->getRoles($this->decodedToken, $resource);

        return in_array($role, $roles, true);
    }

    /**
     * Get all roles from the token (realm + resource-level).
     */
    public function roles(?string $resource = null): array
    {
        if (! $this->decodedToken) {
            $this->user();
        }

        if (! $this->decodedToken) {
            return [];
        }

        return $this->tokenService->getRoles($this->decodedToken, $resource);
    }

    /**
     * Get the OrganizationService (Keycloak 26+).
     */
    public function organizations(): OrganizationService
    {
        return $this->organizationService;
    }

    /**
     * Extract the bearer token from the request.
     * Falls back to the configured input_key if no Authorization header.
     */
    private function extractBearerToken(): ?string
    {
        $token = $this->request->bearerToken();

        if (! $token && $inputKey = config('keycloak.input_key')) {
            $token = $this->request->input($inputKey);
        }

        return $token;
    }

    /**
     * Resolve the user from the database or build a token-only user.
     */
    private function resolveUser(): ?Authenticatable
    {
        if (! config('keycloak.load_user_from_database', true)) {
            return $this->buildTokenUser();
        }

        $customMethod = config('keycloak.user_provider_custom_retrieve_method');

        if ($customMethod && method_exists($this->provider, $customMethod)) {
            $principalAttribute = config('keycloak.token_principal_attribute', 'preferred_username');
            $credential = config('keycloak.user_provider_credential', 'username');

            $user = $this->provider->{$customMethod}(
                $this->decodedToken,
                [$credential => $this->decodedToken->{$principalAttribute} ?? null]
            );
        } else {
            $principalAttribute = config('keycloak.token_principal_attribute', 'preferred_username');
            $credential = config('keycloak.user_provider_credential', 'username');
            $credentialValue = $this->decodedToken->{$principalAttribute} ?? null;

            if (! $credentialValue) {
                return null;
            }

            $user = $this->provider->retrieveByCredentials([$credential => $credentialValue]);
        }

        if ($user && config('keycloak.append_decoded_token', false)) {
            $user->token = $this->decodedToken;
        }

        return $user;
    }

    /**
     * Build an Authenticatable user object directly from the JWT claims.
     * Used when load_user_from_database is false.
     */
    private function buildTokenUser(): Authenticatable
    {
        $user = new TokenUser;
        $user->fill((array) $this->decodedToken);

        if (config('keycloak.append_decoded_token', false)) {
            $user->token = $this->decodedToken;
        }

        return $user;
    }
}
