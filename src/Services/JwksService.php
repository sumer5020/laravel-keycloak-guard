<?php

namespace KeycloakGuard\Services;

use Firebase\JWT\JWK;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use KeycloakGuard\Exceptions\KeycloakGuardException;

class JwksService
{
    private Client $http;

    public function __construct()
    {
        $this->http = new Client(['timeout' => 5.0]);
    }

    /**
     * Retrieve the parsed JWK key set from Keycloak's JWKS endpoint.
     * Results are cached to avoid hitting Keycloak on every request.
     *
     * @throws KeycloakGuardException
     */
    public function getKeys(): array
    {
        $uri = $this->resolveJwksUri();
        $ttl = (int) config('keycloak.jwks_cache_ttl', 3600);
        $cacheKey = 'keycloak_jwks_'.md5($uri);

        return Cache::remember($cacheKey, $ttl, function () use ($uri) {
            return $this->fetchKeys($uri);
        });
    }

    /**
     * Force-refresh the JWKS cache (useful after Keycloak key rotation).
     */
    public function refreshKeys(): array
    {
        $uri = $this->resolveJwksUri();
        $cacheKey = 'keycloak_jwks_'.md5($uri);

        Cache::forget($cacheKey);

        return $this->getKeys();
    }

    /**
     * Resolve the JWKS URI from config or auto-discover it.
     */
    public function resolveJwksUri(): string
    {
        if ($uri = config('keycloak.jwks_uri')) {
            return $uri;
        }

        $baseUrl = rtrim(config('keycloak.base_url', ''), '/');
        $realm = config('keycloak.realm');

        if (! $baseUrl || ! $realm) {
            throw new KeycloakGuardException(
                'Cannot resolve JWKS URI. Set KEYCLOAK_JWKS_URI or both KEYCLOAK_BASE_URL and KEYCLOAK_REALM.'
            );
        }

        return "{$baseUrl}/realms/{$realm}/protocol/openid-connect/certs";
    }

    /**
     * Get the OpenID Connect discovery document.
     */
    public function getDiscoveryDocument(): array
    {
        $baseUrl = rtrim(config('keycloak.base_url', ''), '/');
        $realm = config('keycloak.realm');
        $url = "{$baseUrl}/realms/{$realm}/.well-known/openid-configuration";

        try {
            $response = $this->http->get($url);

            return json_decode((string) $response->getBody(), true);
        } catch (GuzzleException $e) {
            throw new KeycloakGuardException(
                'Failed to fetch OpenID discovery document: '.$e->getMessage()
            );
        }
    }

    /**
     * Fetch and parse JWKS from the given URI.
     */
    private function fetchKeys(string $uri): array
    {
        try {
            $response = $this->http->get($uri);
            $jwks = json_decode((string) $response->getBody(), true);

            if (! isset($jwks['keys'])) {
                throw new KeycloakGuardException('Invalid JWKS response: missing "keys" array.');
            }

            return JWK::parseKeySet($jwks);
        } catch (GuzzleException $e) {
            throw new KeycloakGuardException(
                'Failed to fetch JWKS from Keycloak: '.$e->getMessage()
            );
        }
    }
}
