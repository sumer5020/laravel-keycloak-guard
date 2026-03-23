<?php

declare(strict_types=1);

namespace KeycloakGuard\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use KeycloakGuard\Exceptions\TokenException;
use stdClass;

class TokenService
{
    public function __construct(private JwksService $jwksService) {}

    /**
     * Decode and validate a JWT token string.
     * Supports both static realm_public_key and JWKS auto-discovery.
     *
     * @throws TokenException
     */
    public function decode(string $token): stdClass
    {
        JWT::$leeway = (int) config('keycloak.leeway', 0);

        try {
            if ($realmPublicKey = config('keycloak.realm_public_key')) {
                return $this->decodeWithPublicKey($token, $realmPublicKey);
            }

            return $this->decodeWithJwks($token);
        } catch (TokenException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new TokenException('Token validation failed.', 0, $e);
        }
    }

    /**
     * Decode using a static RSA public key (classic approach).
     */
    private function decodeWithPublicKey(string $token, string $rawKey): stdClass
    {
        $publicKey = $this->formatPublicKey($rawKey);
        $algorithm = config('keycloak.token_encryption_algorithm', 'RS256');

        try {
            return JWT::decode($token, new Key($publicKey, $algorithm));
        } catch (\Exception $e) {
            // Fallback is opt-in to avoid masking static-key misconfiguration.
            if (
                config('keycloak.allow_public_key_jwks_fallback', false)
                && (config('keycloak.jwks_uri') || (config('keycloak.base_url') && config('keycloak.realm')))
            ) {
                return $this->decodeWithJwks($token, true);
            }

            throw new TokenException('Token signature validation failed.', (int) $e->getCode(), $e);
        }
    }

    /**
     * Decode using JWKS from Keycloak (recommended for Keycloak 26+).
     * Automatically retries with a refreshed key set on kid mismatch.
     */
    private function decodeWithJwks(string $token, bool $isRetry = false): stdClass
    {
        try {
            $keys = $this->jwksService->getKeys();

            return JWT::decode($token, $keys);
        } catch (\Exception $e) {
            // If key ID mismatch, refresh JWKS once and retry (handles key rotation)
            if (! $isRetry && str_contains($e->getMessage(), 'kid')) {
                $keys = $this->refreshJwksWithLock();

                return JWT::decode($token, $keys);
            }

            throw new TokenException('Token signature validation failed.', (int) $e->getCode(), $e);
        }
    }

    /**
     * Refresh JWKS using a lock to avoid thundering-herd traffic on kid mismatch.
     */
    private function refreshJwksWithLock(): array
    {
        $uri = $this->jwksService->resolveJwksUri();
        $lock = Cache::lock('keycloak_jwks_refresh_'.md5($uri), 30);

        try {
            return $lock->block(5, function (): array {
                return $this->jwksService->refreshKeys();
            });
        } catch (\Throwable) {
            // Fall back to currently cached keys if lock acquisition fails.
            return $this->jwksService->getKeys();
        }
    }

    /**
     * Validate the decoded token's claims against application config.
     *
     * @throws TokenException
     */
    public function validate(stdClass $decodedToken): void
    {
        $this->validateIssuer($decodedToken);
        $this->validateAudience($decodedToken);
        $this->validateResources($decodedToken);
    }

    /**
     * Validate the 'iss' claim.
     *
     * @throws TokenException
     */
    private function validateIssuer(stdClass $decodedToken): void
    {
        if (! config('keycloak.validate.issuer', true)) {
            return;
        }

        $expectedIssuer = config('keycloak.validate.expected_issuer');

        if (! $expectedIssuer) {
            $baseUrl = rtrim(config('keycloak.base_url', ''), '/');
            $realm = config('keycloak.realm');
            if ($baseUrl && $realm) {
                $expectedIssuer = "{$baseUrl}/realms/{$realm}";
            }
        }

        if (! $expectedIssuer) {
            return;
        }

        if (($decodedToken->iss ?? null) !== $expectedIssuer) {
            throw new TokenException('Invalid issuer: '.($decodedToken->iss ?? 'missing'));
        }
    }

    /**
     * Validate the 'aud' claim.
     *
     * @throws TokenException
     */
    private function validateAudience(stdClass $decodedToken): void
    {
        if (! config('keycloak.validate.audience', true)) {
            return;
        }

        $expectedAudience = config('keycloak.validate.expected_audience')
            ?? config('keycloak.allowed_resources');

        if (! $expectedAudience) {
            return;
        }

        $expectedAudiences = array_map('trim', explode(',', $expectedAudience));
        $tokenAudiences = (array) ($decodedToken->aud ?? []);

        // The 'aud' claim can be a string or an array of strings.
        foreach ($expectedAudiences as $expected) {
            if (in_array($expected, $tokenAudiences, true)) {
                return;
            }
        }

        throw new TokenException('Invalid audience: '.json_encode($tokenAudiences));
    }

    /**
     * Validate that the token contains at least one of the allowed resources.
     *
     * @throws TokenException
     */
    private function validateResources(stdClass $decodedToken): void
    {
        if (config('keycloak.ignore_resources_validation', false)) {
            return;
        }

        $allowedResources = config('keycloak.allowed_resources');

        if (empty($allowedResources)) {
            return;
        }

        $resources = array_map('trim', explode(',', $allowedResources));
        $tokenResources = (array) ($decodedToken->resource_access ?? []);

        foreach ($resources as $resource) {
            if (array_key_exists($resource, $tokenResources)) {
                return;
            }
        }

        throw new TokenException(
            'Token does not contain any of the allowed resources: '.implode(', ', $resources)
        );
    }

    /**
     * Extract a specific claim from the decoded token.
     */
    public function getClaim(stdClass $token, string $claim): mixed
    {
        return $token->{$claim} ?? null;
    }

    /**
     * Extract all roles from the token (both realm and resource-level).
     */
    public function getRoles(stdClass $token, ?string $resource = null): array
    {
        $realmRoles = (array) ($token->realm_access->roles ?? []);
        $resourceRoles = [];

        if ($resource) {
            $resourceRoles = (array) ($token->resource_access?->{$resource}?->roles ?? []);

            // Merge resource roles with realm roles (standard practice for Keycloak)
            return array_unique(array_merge($realmRoles, $resourceRoles));
        }

        foreach ((array) ($token->resource_access ?? []) as $clientRoles) {
            $resourceRoles = array_merge($resourceRoles, (array) ($clientRoles->roles ?? []));
        }

        return array_unique(array_merge($realmRoles, $resourceRoles));
    }

    /**
     * Get the raw organizations claim from the token (Keycloak 26+).
     * Returns an associative array keyed by org alias.
     */
    public function getOrganizations(stdClass $token): array
    {
        // Keycloak 26 embeds organizations as: { "acme-corp": { "id": "...", "name": "...", "roles": [...] } }
        $org = $token->organization ?? null;

        if ($org === null) {
            return [];
        }

        // Normalize — Keycloak may return object or array
        return json_decode(json_encode($org), true);
    }

    /**
     * Format a raw base64 public key into a PEM-formatted RSA public key.
     */
    private function formatPublicKey(string $rawKey): string
    {
        // Already a PEM key
        if (str_starts_with(trim($rawKey), '-----BEGIN')) {
            return $rawKey;
        }

        // Strip any existing headers/footers and whitespace
        $key = preg_replace('/-----.*?-----|\s+/', '', $rawKey);

        return "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split($key, 64, "\n")
            ."-----END PUBLIC KEY-----\n";
    }
}
