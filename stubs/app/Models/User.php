<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use KeycloakGuard\Traits\HasOrganizations;

class User extends Authenticatable
{
    use HasOrganizations;

    protected $fillable = [
        'name',
        'email',
        'keycloak_id',
    ];

    protected $hidden = [
        'remember_token',
    ];
}
