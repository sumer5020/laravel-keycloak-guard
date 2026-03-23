<?php

namespace KeycloakGuard\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use KeycloakGuard\Guards\KeycloakGuard;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validate that the authenticated user has a specific role.
 *
 * Usage in routes:
 *   Route::middleware('keycloak.role:admin')
 *   Route::middleware('keycloak.role:editor,moderator')         // any of these roles
 *   Route::middleware('keycloak.role:admin|my-client')          // role in specific resource
 */
class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $guard = Auth::guard();

        /** @var KeycloakGuard $guard */
        if (! method_exists($guard, 'hasRole')) {
            // Attempt to find the keycloak guard if the default one is not it
            $guard = Auth::guard('api'); // Standard fallback

            if (! method_exists($guard, 'hasRole')) {
                throw new \RuntimeException('Keycloak CheckRole middleware requires the "keycloak" guard driver.');
            }
        }

        if (! $guard->check()) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        foreach ($roles as $roleExpression) {
            // Support "role|resource" syntax for resource-specific role check
            [$role, $resource] = array_pad(explode('|', $roleExpression, 2), 2, null);

            if ($guard->hasRole(trim($role), $resource ? trim($resource) : null)) {
                return $next($request);
            }
        }

        return response()->json([
            'error' => 'Forbidden.',
            'required' => $roles,
        ], 403);
    }
}
