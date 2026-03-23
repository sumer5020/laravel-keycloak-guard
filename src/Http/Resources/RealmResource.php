<?php

namespace KeycloakGuard\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string|null $realm
 * @property string|null $base_url
 * @property string|null $jwks_uri
 * @property string|null $issuer
 */
class RealmResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'realm' => $this->resource['realm'] ?? config('keycloak.realm'),
            'base_url' => $this->resource['base_url'] ?? config('keycloak.base_url'),
            'jwks_uri' => $this->resource['jwks_uri'] ?? $this->resolveJwksUri(),
            'issuer' => $this->resource['issuer'] ?? $this->resolveIssuer(),
        ];
    }

    /**
     * Resolve the JWKS URI from config or auto-discovery.
     */
    private function resolveJwksUri(): ?string
    {
        if ($uri = config('keycloak.jwks_uri')) {
            return $uri;
        }

        $baseUrl = rtrim(config('keycloak.base_url', ''), '/');
        $realm = config('keycloak.realm');

        if (! $baseUrl || ! $realm) {
            return null;
        }

        return "{$baseUrl}/realms/{$realm}/protocol/openid-connect/certs";
    }

    /**
     * Resolve the expected issuer for the realm.
     */
    private function resolveIssuer(): ?string
    {
        $baseUrl = rtrim(config('keycloak.base_url', ''), '/');
        $realm = config('keycloak.realm');

        if (! $baseUrl || ! $realm) {
            return null;
        }

        return "{$baseUrl}/realms/{$realm}";
    }
}
