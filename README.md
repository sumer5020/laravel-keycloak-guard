# Laravel Keycloak Guard

**Note: This Version is not production ready yet, still in active development**

> **robsontenorio/laravel-keycloak-guard fork** — Full support for **Laravel 13**, **Keycloak 26+**, and the **Keycloak 26 Organizations** feature (`--features=organization`).

[![Laravel](https://img.shields.io/badge/Laravel-13.x-red.svg)](https://laravel.com)
[![Keycloak](https://img.shields.io/badge/Keycloak-26%2B-blue.svg)](https://www.keycloak.org)
[![PHP](https://img.shields.io/badge/PHP-8.3%2B-purple.svg)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

---

## What's New VS the Original Package:

| Feature                            | Original | This Version |
|------------------------------------|----------|--------------|
| Laravel 13 support                 | ❌        | ✅            |
| Keycloak 26 JWKS auto-discovery    | ❌        | ✅            |
| Automatic key rotation support     | ❌        | ✅            |
| Keycloak 26 Organizations          | ❌        | ✅            |
| `KeycloakOrg` facade               | ❌        | ✅            |
| `keycloak.org` middleware          | ❌        | ✅            |
| `keycloak.org.role` middleware     | ❌        | ✅            |
| `HasOrganizations` trait           | ❌        | ✅            |
| `TokenUser` (no-DB mode)           | Partial  | ✅            |
| `Auth::payload()`                  | ❌        | ✅            |
| `Auth::roles()`                    | ❌        | ✅            |
| `RealmResource` (API Resource)     | ❌        | ✅            |
| `keycloak:doctor` command          | ❌        | ✅            |
| `ActingAsKeycloak` (Testing Trait) | ❌        | ✅            |
| PHP 8.3 constructor promotion      | ❌        | ✅            |

---

## Requirements

- PHP **8.3+**
- Laravel **12.x / 13.x**
- Keycloak **21+** (Organizations require **26+**)

---

## Installation

```bash
composer require sumer5020/laravel-keycloak-guard
```

Publish the config:

```bash
php artisan vendor:publish --tag=keycloak-config
```

Optionally publish migrations (only if using `organizations.sync_to_database`):

```bash
php artisan vendor:publish --tag=keycloak-migrations
php artisan migrate
```

---

## Health & Diagnostics

The package includes a "Doctor" command to help you troubleshoot connectivity and configuration issues.

```bash
php artisan keycloak:doctor
```

It performs the following checks:
- **Configuration**: Verifies that required environment variables are set.
- **Connectivity**: Attempts to fetch the OIDC discovery document (`.well-known/openid-configuration`) from Keycloak.
- **Organizations**: Checks if the organization feature is correctly configured.

---

## API Resources

If you need to expose your Keycloak configuration (realm name, base URL, issuer, JWKS URI) to your frontend (e.g., for `keycloak-js`), you can use the built-in `RealmResource`.

```php
use KeycloakGuard\Http\Resources\RealmResource;

Route::get('/keycloak/config', function () {
    return new RealmResource([]);
});
```

This returns a standardized JSON response:
```json
{
  "realm": "my-realm",
  "base_url": "https://keycloak.example.com",
  "jwks_uri": "https://keycloak.example.com/realms/my-realm/protocol/openid-connect/certs",
  "issuer": "https://keycloak.example.com/realms/my-realm"
}
```

---

## Basic Setup

### 1. Configure the Guard

**`config/auth.php`**:

```php
'guards' => [
    'api' => [
        'driver'   => 'keycloak',
        'provider' => 'users',
    ],
],

'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model'  => App\Models\User::class,
    ],
],
```

### 2. Environment Variables

```env
# ── Required ────────────────────────────────────────────────
KEYCLOAK_BASE_URL=https://keycloak.example.com
KEYCLOAK_REALM=my-realm
KEYCLOAK_ALLOWED_RESOURCES=my-laravel-api

# ── Key / Token Verification ────────────────────────────────
# Option A: Static public key (from Realm Settings → Keys → RS256 → Public Key)
KEYCLOAK_REALM_PUBLIC_KEY=MIIBIjANBgkq...

# Option B: JWKS auto-discovery (recommended for Keycloak 26+, supports key rotation)
# Leave KEYCLOAK_REALM_PUBLIC_KEY empty — the package constructs the JWKS URI automatically
# Or set it explicitly:
KEYCLOAK_JWKS_URI=https://keycloak.example.com/realms/my-realm/protocol/openid-connect/certs
KEYCLOAK_JWKS_CACHE_TTL=3600

# ── User Matching ────────────────────────────────────────────
KEYCLOAK_TOKEN_PRINCIPAL_ATTRIBUTE=sub        # JWT claim used as identifier
KEYCLOAK_USER_PROVIDER_CREDENTIAL=keycloak_id # DB column to match against

# ── Optional ─────────────────────────────────────────────────
KEYCLOAK_APPEND_DECODED_TOKEN=true            # Attach $user->token (stdClass)
KEYCLOAK_LEEWAY=30                            # Clock skew tolerance (seconds)
KEYCLOAK_LOAD_USER_FROM_DATABASE=true
KEYCLOAK_TOKEN_ENCRYPTION_ALGORITHM=RS256

# ── Organizations (Keycloak 26+) ─────────────────────────────
KEYCLOAK_ORGANIZATIONS_ENABLED=true
KEYCLOAK_ORGANIZATION_HEADER=X-Organization   # Header for selecting active org
KEYCLOAK_ORGANIZATION_REQUIRE=false           # 403 if no org in token?
KEYCLOAK_ORGANIZATION_SYNC_DB=false           # Persist orgs to database?
```

### 3. Protect Routes

```php
// routes/api.php

// Basic auth
Route::middleware('auth:api')->group(function () {
    Route::get('/me', fn(Request $r) => $r->user());
});

// Role-based access
Route::middleware(['auth:api', 'keycloak.role:admin'])->group(function () {
    Route::get('/admin', AdminController::class);
});

// Organization-scoped routes (Keycloak 26+)
Route::middleware(['auth:api', 'keycloak.org'])->group(function () {
    Route::apiResource('projects', ProjectController::class);

    // Org admin only
    Route::middleware('keycloak.org.role:admin')->group(function () {
        Route::apiResource('members', MemberController::class);
    });
});
```

---

## Keycloak 26 Organizations

### How It Works

When Keycloak is started with `--features=organization`, it injects an `organization` claim into the JWT:

```json
{
  "sub": "f7a8b9c0-...",
  "preferred_username": "john@acme.com",
  "organization": {
    "acme-corp": {
      "id": "3fa85f64-...",
      "name": "Acme Corporation",
      "roles": ["admin", "member"]
    }
  }
}
```

For users in **multiple organizations**, the client sends an `X-Organization` header to select the active one.

### The `KeycloakOrg` Facade

```php
use KeycloakGuard\Facades\KeycloakOrg;

// Get the active organization for this request
$org = KeycloakOrg::current();
// => ['alias' => 'acme-corp', 'id' => '3fa85f64-...', 'name' => 'Acme Corporation', 'roles' => ['admin']]

// Get all organizations the user belongs to (from the token)
$orgs = KeycloakOrg::all();

// Check membership
KeycloakOrg::belongsTo('acme-corp');        // bool
KeycloakOrg::hasOrganizations();             // bool

// Role checks within the active organization
KeycloakOrg::hasRole('admin');               // bool
KeycloakOrg::hasRole('admin', 'acme-corp'); // bool (explicit org)
KeycloakOrg::hasAnyRole(['admin', 'billing-manager']); // bool

// Sync to DB (requires sync_to_database = true)
KeycloakOrg::syncToDatabase(auth()->user());
```

### `HasOrganizations` Trait

Add to your `User` model for database relationships and convenient helpers:

```php
use KeycloakGuard\Traits\HasOrganizations;

class User extends Authenticatable
{
    use HasOrganizations;
}
```

Then use:

```php
// From JWT (no DB query)
$user->currentOrganization();        // array|null
$user->hasOrgRole('admin');          // bool
$user->belongsToOrg('acme-corp');    // bool

// From database (requires sync_to_database)
$user->organizations()->get();
$user->organizations()->where('alias', 'acme-corp')->first();
```

### Using Organization Context in Controllers

```php
class ProjectController extends Controller
{
    public function index(): JsonResponse
    {
        // Array from JWT — no DB query
        $org = KeycloakOrg::current();

        // Eloquent model — only if sync_to_database = true
        $orgModel = app('current_organization_model');

        return response()->json(
            Project::where('organization_id', $orgModel->id)->paginate()
        );
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $org = app('current_organization_model');

        $project = Project::create([
            ...$request->validated(),
            'organization_id' => $org->id,
        ]);

        return response()->json($project, 201);
    }
}
```

---

## Auth Guard Methods

```php
use Illuminate\Support\Facades\Auth;

// Standard Laravel
Auth::user();           // Authenticatable|null
Auth::check();          // bool
Auth::id();             // mixed

// Keycloak-specific
Auth::token();          // string|null  — full decoded token as JSON
Auth::payload();        // stdClass|null — decoded token object
Auth::roles();          // array — all realm + resource roles (with inheritance)
Auth::roles('my-api'); // array         — resource-specific roles (includes realm roles)
Auth::hasRole('admin'); // bool
Auth::hasRole('editor', 'my-api'); // bool — resource-scoped

// Organizations
Auth::organizations();  // OrganizationService
```

---

## Middleware Reference

| Middleware                     | Description                           | Example                                      |
|--------------------------------|---------------------------------------|----------------------------------------------|
| `keycloak.role:admin`          | Requires realm or any resource role   | `middleware('keycloak.role:admin,editor')`   |
| `keycloak.role:editor\|my-api` | Requires role in specific resource    | `middleware('keycloak.role:editor\|my-api')` |
| `keycloak.org`                 | Resolves active org from JWT + header | `middleware('keycloak.org')`                 |
| `keycloak.org.role:admin`      | Requires org-level role               | `middleware('keycloak.org.role:admin')`      |

> **Note**: The `keycloak.role` middleware supports role inheritance. If a user has a realm-level role (e.g., `admin`), they will pass checks for any resource-specific role requirements.

---

## No-Database Mode

If your API doesn't have a `users` table, set:

```env
KEYCLOAK_LOAD_USER_FROM_DATABASE=false
KEYCLOAK_APPEND_DECODED_TOKEN=true
```

`Auth::user()` returns a `TokenUser` built from JWT claims:

```php
$user = Auth::user();
$user->sub;                 // UUID
$user->preferred_username;  // username
$user->email;               // email
$user->token;               // full stdClass of JWT claims
```

---

## Custom User Provider

```php
// app/Providers/CustomUserProvider.php
class CustomUserProvider implements UserProvider
{
    public function customRetrieveUser(stdClass $token, array $credentials): ?User
    {
        return User::updateOrCreate(
            ['keycloak_id' => $token->sub],
            [
                'name'  => $token->name ?? $token->preferred_username,
                'email' => $token->email,
            ]
        );
    }
    // ... other required methods
}
```

```env
KEYCLOAK_USER_PROVIDER_CUSTOM_RETRIEVE_METHOD=customRetrieveUser
```

---

## Keycloak 26 — Admin Configuration Checklist

In your Keycloak 26 admin console:

**Realm Settings → Keys → RS256**: Copy the public key for `KEYCLOAK_REALM_PUBLIC_KEY`, or leave blank to use JWKS auto-discovery.

**Client Settings** (for your Laravel API client):
- Client protocol: `openid-connect`
- Access type: `confidential`
- Standard flow: **OFF** (API only)
- Direct access grants: **OFF**
- Service accounts enabled: **ON** (for M2M)

**Organizations** (Keycloak 26+):
- Realm Settings → General → Enable Organizations: **ON**
- OR start Keycloak with `--features=organization`
- Add the `organization` mapper to your client's token scope

**Token Mappers** — ensure your client has:
- `organization` claim mapper (type: Organization Membership)
- `realm roles` mapper
- `client roles` mapper

---

## Testing

The package provides a powerful `ActingAsKeycloak` trait to mock authenticated states in your feature tests without needing a live Keycloak server.

```php
namespace Tests\Feature;

use KeycloakGuard\Testing\ActingAsKeycloak;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use ActingAsKeycloak;

    public function test_api_is_protected(): void
    {
        // Simple authentication
        $this->withKeycloakToken(['name' => 'John Doe'])
             ->getJson('/api/me')
             ->assertOk();
             
        // Authentication with specific roles
        $this->withKeycloakToken([
            'realm_access' => ['roles' => ['admin']]
        ])->getJson('/api/admin')->assertOk();
    }

    public function test_organization_access(): void
    {
        // Authenticate with organization membership
        $this->withKeycloakOrganization([
            'acme' => ['id' => 'org-1', 'name' => 'Acme Corp', 'roles' => ['member']]
        ])
        ->withOrganization('acme') // Select active org via header
        ->getJson('/api/projects')
        ->assertOk();
    }
}
```

---

## License

[MIT](LICENSE)
