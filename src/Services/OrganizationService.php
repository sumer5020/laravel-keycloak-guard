<?php

declare(strict_types=1);

namespace KeycloakGuard\Services;

use Illuminate\Http\Request;
use KeycloakGuard\Exceptions\OrganizationException;
use KeycloakGuard\Models\Organization;
use stdClass;

class OrganizationService
{
    /** @var array<string, array> Parsed organizations from the current token */
    private array $organizations = [];

    /** @var array|null The resolved active organization for this request */
    private ?array $currentOrg = null;

    private ?Request $request = null;

    private bool $resolved = false;

    public function __construct(?Request $request = null)
    {
        $this->request = $request;
    }

    /**
     * Boot the service with the decoded token from the current request.
     * Called once by the guard after token validation.
     */
    public function boot(stdClass $decodedToken, ?Request $request = null): void
    {
        if ($request) {
            $this->request = $request;
        } elseif (! $this->request) {
            $this->request = app('request');
        }

        $org = $decodedToken->organization ?? null;

        if ($org !== null) {
            $this->organizations = json_decode(json_encode($org), true);
        } else {
            $this->organizations = [];
        }

        $this->resolved = false;
        $this->currentOrg = null;
    }

    /**
     * Resolve and return the active organization for this request.
     * For multi-org users, reads the configured HTTP header.
     *
     * @throws OrganizationException
     */
    public function current(): ?array
    {
        if ($this->resolved) {
            return $this->currentOrg;
        }

        $this->resolved = true;

        if (empty($this->organizations)) {
            return null;
        }

        $header = config('keycloak.organizations.header', 'X-Organization');
        $request = $this->request ?? app('request');
        $alias = $request->header($header);

        if ($alias) {
            if (! isset($this->organizations[$alias])) {
                throw new OrganizationException(
                    "Organization '{$alias}' (from header {$header}) is not accessible with this token."
                );
            }

            $this->currentOrg = $this->buildOrgArray($alias, $this->organizations[$alias]);
        } else {
            // Default to first org when no header supplied
            $alias = array_key_first($this->organizations);
            $this->currentOrg = $this->buildOrgArray($alias, $this->organizations[$alias]);
        }

        return $this->currentOrg;
    }

    /**
     * Return all organizations from the token.
     */
    public function all(): array
    {
        return collect($this->organizations)
            ->map(fn ($data, $alias) => $this->buildOrgArray($alias, $data))
            ->values()
            ->toArray();
    }

    /**
     * Check whether the current user belongs to a specific organization.
     */
    public function belongsTo(string $alias): bool
    {
        return isset($this->organizations[$alias]);
    }

    /**
     * Check if the user has a specific role in the current (or given) organization.
     */
    public function hasRole(string $role, ?string $orgAlias = null): bool
    {
        if ($orgAlias) {
            $org = isset($this->organizations[$orgAlias])
                ? $this->buildOrgArray($orgAlias, $this->organizations[$orgAlias])
                : null;
        } else {
            $org = $this->current();
        }

        if (! $org) {
            return false;
        }

        return in_array($role, $org['roles'] ?? [], true);
    }

    /**
     * Check if the user has any of the given roles in the current org.
     */
    public function hasAnyRole(array $roles, ?string $orgAlias = null): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role, $orgAlias)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns true if the token contains an organization claim.
     */
    public function hasOrganizations(): bool
    {
        return ! empty($this->organizations);
    }

    /**
     * Sync the current organization and user membership to the database.
     * Requires the sync_to_database option and package migrations.
     */
    public function syncToDatabase(mixed $user): ?object
    {
        if (! config('keycloak.organizations.sync_to_database', false)) {
            return null;
        }

        $org = $this->current();

        if (! $org || ! $user || ! method_exists($user, 'organizations')) {
            return null;
        }

        $modelClass = config('keycloak.organizations.model', Organization::class);

        /** @var Organization $model */
        $model = app($modelClass);

        $organization = $model->updateOrCreate(
            ['keycloak_org_id' => $org['id']],
            [
                'alias' => $org['alias'],
                'name' => $org['name'],
            ]
        );

        // Check if we already synced this user/org combo recently to avoid redundant writes.
        // We use the 'updated_at' in the pivot table to check if it's already up-to-date.
        $pivot = $user->organizations()->where($organization->getForeignKey(), $organization->getKey())->first()?->pivot;

        $rolesJson = json_encode($org['roles'] ?? []);

        $pivotUpdatedAt = $pivot?->updated_at;
        $isStale = ! $pivotUpdatedAt || $pivotUpdatedAt->diffInMinutes(now()) > 60;

        if (! $pivot || $pivot->roles !== $rolesJson || $isStale) {
            $user->organizations()->syncWithoutDetaching([
                $organization->getKey() => [
                    'roles' => $rolesJson,
                    'updated_at' => now(),
                ],
            ]);
        }

        return $organization;
    }

    /**
     * Build a normalized organization array with the alias included.
     */
    private function buildOrgArray(string $alias, array $data): array
    {
        return [
            'alias' => $alias,
            'id' => $data['id'] ?? null,
            'name' => $data['name'] ?? $alias,
            'attributes' => $data['attributes'] ?? [],
            'roles' => $data['roles'] ?? [],
        ];
    }
}
