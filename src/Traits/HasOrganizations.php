<?php

namespace KeycloakGuard\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use KeycloakGuard\Facades\KeycloakOrg;
use KeycloakGuard\Models\Organization;

/**
 * Add to your User model to enable Keycloak Organization relationships.
 *
 * Usage:
 *   class User extends Authenticatable {
 *       use \KeycloakGuard\Traits\HasOrganizations;
 *   }
 */
trait HasOrganizations
{
    /**
     * All organizations this user belongs to (from database).
     */
    public function organizations(): BelongsToMany
    {
        $model = config('keycloak.organizations.model', Organization::class);

        return $this->belongsToMany($model, 'organization_user', 'user_id', 'organization_id')
            ->withPivot('roles', 'updated_at')
            ->withTimestamps();
    }

    /**
     * Get the current organization from the active token (not DB).
     * Useful for quick access inside controllers without DB query.
     */
    public function currentOrganization(): ?array
    {
        return KeycloakOrg::current();
    }

    /**
     * Check if the user has a given role in their active organization (from token).
     */
    public function hasOrgRole(string $role, ?string $orgAlias = null): bool
    {
        return KeycloakOrg::hasRole($role, $orgAlias);
    }

    /**
     * Check if the user belongs to a given organization alias (from token).
     */
    public function belongsToOrg(string $alias): bool
    {
        return KeycloakOrg::belongsTo($alias);
    }
}
