<?php

namespace KeycloakGuard\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Eloquent model for persisting Keycloak 26 Organizations.
 * Only used when organizations.sync_to_database = true.
 *
 * @property string $keycloak_org_id The Keycloak organization UUID
 * @property string $alias The organization alias (e.g. "acme-corp")
 * @property string $name The display name
 * @property array $settings Optional JSON settings
 */
class Organization extends Model
{
    protected $fillable = [
        'keycloak_org_id',
        'alias',
        'name',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    /**
     * The users who belong to this organization (pivot includes roles).
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            config('auth.providers.users.model', User::class),
            'organization_user',
            'organization_id',
            'user_id'
        )->withPivot('roles', 'updated_at')->withTimestamps();
    }
}
