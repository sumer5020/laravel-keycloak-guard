<?php

namespace KeycloakGuard\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use KeycloakGuard\Traits\HasOrganizations;

class User extends Authenticatable
{
    use HasOrganizations;

    protected $fillable = ['keycloak_id', 'name', 'email'];

    protected $table = 'users';
}
