<?php

namespace KeycloakGuard\Tests\Feature;

use Illuminate\Support\Facades\Route;
use KeycloakGuard\Facades\KeycloakOrg;
use KeycloakGuard\Testing\ActingAsKeycloak;
use KeycloakGuard\Tests\Fixtures\User;
use KeycloakGuard\Tests\TestCase;

class OrganizationTest extends TestCase
{
    use ActingAsKeycloak;

    private array $orgPayload = [
        'acme-corp' => [
            'id' => '3fa85f64-5717-4562-b3fc-2c963f66afa6',
            'name' => 'Acme Corporation',
            'roles' => ['admin', 'member'],
        ],
        'beta-inc' => [
            'id' => '7cb96d28-1234-5678-abcd-9ef012345678',
            'name' => 'Beta Inc',
            'roles' => ['member'],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        User::create([
            'keycloak_id' => 'test-user-sub-123',
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        Route::middleware(['auth:api', 'keycloak.org'])->get('/api/org', function () {
            return response()->json(KeycloakOrg::current());
        });

        Route::middleware(['auth:api', 'keycloak.org', 'keycloak.org.role:admin'])
            ->get('/api/org/admin-only', fn () => response()->json(['ok' => true]));
    }

    public function test_current_org_resolved_from_token(): void
    {
        $this->withKeycloakOrganization($this->orgPayload, ['sub' => 'test-user-sub-123'])
            ->withOrganization('acme-corp')
            ->getJson('/api/org')
            ->assertOk()
            ->assertJsonFragment(['alias' => 'acme-corp', 'name' => 'Acme Corporation']);
    }

    public function test_defaults_to_first_org_without_header(): void
    {
        $this->withKeycloakOrganization($this->orgPayload, ['sub' => 'test-user-sub-123'])
            ->getJson('/api/org')
            ->assertOk()
            ->assertJsonFragment(['alias' => 'acme-corp']);
    }

    public function test_org_switch_via_header(): void
    {
        $this->withKeycloakOrganization($this->orgPayload, ['sub' => 'test-user-sub-123'])
            ->withOrganization('beta-inc')
            ->getJson('/api/org')
            ->assertOk()
            ->assertJsonFragment(['alias' => 'beta-inc']);
    }

    public function test_invalid_org_header_returns_403(): void
    {
        $this->withKeycloakOrganization($this->orgPayload, ['sub' => 'test-user-sub-123'])
            ->withOrganization('nonexistent-org')
            ->getJson('/api/org')
            ->assertStatus(403);
    }

    public function test_admin_role_allows_access(): void
    {
        $this->withKeycloakOrganization($this->orgPayload, ['sub' => 'test-user-sub-123'])
            ->withOrganization('acme-corp')
            ->getJson('/api/org/admin-only')
            ->assertOk();
    }

    public function test_member_only_role_is_forbidden_on_admin_route(): void
    {
        $this->withKeycloakOrganization($this->orgPayload, ['sub' => 'test-user-sub-123'])
            ->withOrganization('beta-inc')
            ->getJson('/api/org/admin-only')
            ->assertStatus(403);
    }

    public function test_no_org_without_require_returns_null(): void
    {
        config(['keycloak.organizations.require' => false]);

        Route::middleware(['auth:api', 'keycloak.org'])->get('/api/org/optional', function () {
            return response()->json(['org' => KeycloakOrg::current()]);
        });

        $this->withKeycloakToken(['sub' => 'test-user-sub-123'])
            ->getJson('/api/org/optional')
            ->assertOk()
            ->assertJson(['org' => null]);
    }

    public function test_no_org_with_require_returns_403(): void
    {
        config([
            'keycloak.organizations.enabled' => true,
            'keycloak.organizations.require' => true,
        ]);

        $this->withKeycloakToken(['sub' => 'test-user-sub-123'])
            ->getJson('/api/org')
            ->assertStatus(403);
    }

    public function test_has_role_checks_current_org(): void
    {
        Route::middleware(['auth:api', 'keycloak.org'])->get('/api/org/role-check', function () {
            return response()->json([
                'is_admin' => KeycloakOrg::hasRole('admin'),
                'is_editor' => KeycloakOrg::hasRole('editor'),
            ]);
        });

        $this->withKeycloakOrganization($this->orgPayload, ['sub' => 'test-user-sub-123'])
            ->withOrganization('acme-corp')
            ->getJson('/api/org/role-check')
            ->assertOk()
            ->assertJson(['is_admin' => true, 'is_editor' => false]);
    }

    public function test_all_returns_every_org_from_token(): void
    {
        Route::middleware(['auth:api', 'keycloak.org'])->get('/api/orgs/all', function () {
            return response()->json(KeycloakOrg::all());
        });

        $this->withKeycloakOrganization($this->orgPayload, ['sub' => 'test-user-sub-123'])
            ->getJson('/api/orgs/all')
            ->assertOk()
            ->assertJsonCount(2);
    }
}
