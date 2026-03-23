<?php

declare(strict_types=1);

namespace KeycloakGuard\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use KeycloakGuard\Facades\KeycloakOrg;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure the authenticated user has one of the given roles within their
 * active Keycloak Organization. Must be used AFTER keycloak.org middleware.
 *
 * Usage:
 *   Route::middleware(['auth:api', 'keycloak.org', 'keycloak.org.role:admin'])
 *   Route::middleware(['auth:api', 'keycloak.org', 'keycloak.org.role:admin,billing-manager'])
 */
class CheckOrganizationRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! Auth::check()) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        if (! config('keycloak.organizations.enabled', false)) {
            return $next($request);
        }

        foreach ($roles as $role) {
            if (KeycloakOrg::hasRole(trim($role))) {
                return $next($request);
            }
        }

        return response()->json([
            'error' => 'Forbidden. Insufficient organization role.',
            'required_roles' => $roles,
            'organization' => KeycloakOrg::current()['alias'] ?? null,
        ], 403);
    }
}
