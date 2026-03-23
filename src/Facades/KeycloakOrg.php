<?php

namespace KeycloakGuard\Facades;

use Illuminate\Support\Facades\Facade;
use KeycloakGuard\Services\OrganizationService;

/**
 * Facade for the Keycloak 26 OrganizationService.
 *
 * @method static array|null current() Get the active org for this request
 * @method static array all() Get all orgs from the token
 * @method static bool belongsTo(string $alias) Check if user belongs to an org
 * @method static bool hasRole(string $role, ?string $orgAlias = null)
 * @method static bool hasAnyRole(array $roles, ?string $orgAlias = null)
 * @method static bool hasOrganizations() Check if token has any org claim
 * @method static object|null syncToDatabase(mixed $user) Sync org+membership to DB
 *
 * @see OrganizationService
 */
class KeycloakOrg extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return OrganizationService::class;
    }
}
