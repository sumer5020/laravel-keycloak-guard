<?php

namespace KeycloakGuard\Models;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A lightweight Authenticatable user built entirely from JWT claims.
 * Used when load_user_from_database = false.
 */
class TokenUser extends GenericUser implements Authenticatable
{
    /** @var array<string, mixed> */
    protected array $attributes = [];

    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }

        return $this;
    }

    public function getAuthIdentifierName(): string
    {
        return 'sub';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->attributes['sub'] ?? null;
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): string
    {
        return '';
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return '';
    }

    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}
