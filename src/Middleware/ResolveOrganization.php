<?php

declare(strict_types=1);

namespace KeycloakGuard\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use KeycloakGuard\Exceptions\OrganizationException;
use KeycloakGuard\Facades\KeycloakOrg;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve and bind the active Keycloak 26 Organization for the current request.
 *
 * - Reads the `organization` claim from the JWT
 * - Uses the X-Organization header (configurable) to select among multiple orgs
 * - Optionally syncs the org and membership to the database
 * - Binds 'current_organization' in the service container
 *
 * Usage:
 *   Route::middleware(['auth:api', 'keycloak.org'])->group(...)
 */
class ResolveOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        if (! config('keycloak.organizations.enabled', false)) {
            return $next($request);
        }

        try {
            $org = KeycloakOrg::current();
        } catch (OrganizationException $e) {
            Log::debug('Keycloak organization resolution failed', [
                'reason' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Forbidden. Invalid organization context.'], 403);
        }

        if (! $org && config('keycloak.organizations.require', false)) {
            return response()->json([
                'error' => 'This endpoint requires an organization context, but none was found in the token.',
            ], 403);
        }

        // Bind to IoC container for easy access in controllers
        app()->instance('current_organization', $org);

        // Optionally sync to database
        if (config('keycloak.organizations.sync_to_database', false)) {
            $dbOrg = KeycloakOrg::syncToDatabase(Auth::user());
            app()->instance('current_organization_model', $dbOrg);
        }

        return $next($request);
    }
}
