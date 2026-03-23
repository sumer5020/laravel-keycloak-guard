<?php

use KeycloakGuard\Models\Organization;

return [

    /*
    |--------------------------------------------------------------------------
    | Keycloak Realm Public Key
    |--------------------------------------------------------------------------
    | The Keycloak Server realm public key (RSA).
    | Found at: Realm Settings → Keys → RS256 → Public Key
    |
    | You may also leave this null and use JWKS auto-discovery instead
    | (recommended for Keycloak 26+ — supports automatic key rotation).
    */
    'realm_public_key' => env('KEYCLOAK_REALM_PUBLIC_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Token Encryption Algorithm
    |--------------------------------------------------------------------------
    | The JWT token encryption algorithm used by Keycloak.
    | Supported: 'RS256', 'RS384', 'RS512', 'HS256', 'HS384', 'HS512'
    */
    'token_encryption_algorithm' => env('KEYCLOAK_TOKEN_ENCRYPTION_ALGORITHM', 'RS256'),

    /*
    |--------------------------------------------------------------------------
    | Token Validation Options
    |--------------------------------------------------------------------------
    | Options for validating the JWT token.
    */
    'validate' => [
        /*
         | Validate Issuer (iss)
         | When true, the guard will validate the 'iss' claim against the
         | discovery document or a hardcoded value.
         */
        'issuer' => env('KEYCLOAK_VALIDATE_ISSUER', true),

        /*
         | Expected Issuer
         | If not set, it will be auto-resolved from base_url/realm.
         */
        'expected_issuer' => env('KEYCLOAK_EXPECTED_ISSUER'),

        /*
         | Validate Audience (aud)
         | When true, the guard will validate the 'aud' claim.
         */
        'audience' => env('KEYCLOAK_VALIDATE_AUDIENCE', true),

        /*
         | Expected Audience
         | The client ID or list of client IDs expected in the 'aud' claim.
         | If not set, KEYCLOAK_ALLOWED_RESOURCES will be used.
         */
        'expected_audience' => env('KEYCLOAK_EXPECTED_AUDIENCE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Load User from Database
    |--------------------------------------------------------------------------
    | If true, the guard will attempt to load the user from your database
    | after validating the JWT. Set to false to use a token-only user object.
    */
    'load_user_from_database' => env('KEYCLOAK_LOAD_USER_FROM_DATABASE', true),

    /*
    |--------------------------------------------------------------------------
    | Custom User Provider Retrieve Method
    |--------------------------------------------------------------------------
    | If set, this method on your UserProvider will be called instead of the
    | default retrieveByCredentials(). It receives the full decoded token.
    | Example: 'customRetrieveUser'
    */
    'user_provider_custom_retrieve_method' => env('KEYCLOAK_USER_PROVIDER_CUSTOM_RETRIEVE_METHOD'),

    /*
    |--------------------------------------------------------------------------
    | User Provider Credential
    |--------------------------------------------------------------------------
    | The database column name used to match the authenticated user.
    | Must correspond to the token_principal_attribute below.
    */
    'user_provider_credential' => env('KEYCLOAK_USER_PROVIDER_CREDENTIAL', 'username'),

    /*
    |--------------------------------------------------------------------------
    | Token Principal Attribute
    |--------------------------------------------------------------------------
    | The JWT claim used as the user's unique identifier.
    | Common values: 'sub', 'preferred_username', 'email'
    */
    'token_principal_attribute' => env('KEYCLOAK_TOKEN_PRINCIPAL_ATTRIBUTE', 'preferred_username'),

    /*
    |--------------------------------------------------------------------------
    | Append Decoded Token to User
    |--------------------------------------------------------------------------
    | When true, the decoded JWT payload is appended to the user object
    | as $user->token (stdClass). Useful for reading custom claims.
    */
    'append_decoded_token' => env('KEYCLOAK_APPEND_DECODED_TOKEN', false),

    /*
    |--------------------------------------------------------------------------
    | Allowed Resources
    |--------------------------------------------------------------------------
    | Comma-separated list of client IDs whose resource_access the token
    | must contain. Leave empty to skip this validation.
    | Example: 'my-laravel-api,another-client'
    */
    'allowed_resources' => env('KEYCLOAK_ALLOWED_RESOURCES'),

    /*
    |--------------------------------------------------------------------------
    | Ignore Resources Validation
    |--------------------------------------------------------------------------
    | Set to true to disable resource_access validation entirely.
    */
    'ignore_resources_validation' => env('KEYCLOAK_IGNORE_RESOURCES_VALIDATION', false),

    /*
    |--------------------------------------------------------------------------
    | Leeway (Clock Skew Tolerance)
    |--------------------------------------------------------------------------
    | Time in seconds to tolerate clock differences between your server
    | and Keycloak. Useful if you see "token expired" errors incorrectly.
    */
    'leeway' => env('KEYCLOAK_LEEWAY', 0),

    /*
    |--------------------------------------------------------------------------
    | Token Input Key
    |--------------------------------------------------------------------------
    | A fallback request parameter name to look for the token if no
    | Authorization: Bearer header is present.
    | Example: 'api_token'
    */
    'input_key' => env('KEYCLOAK_TOKEN_INPUT_KEY'),

    /*
    |--------------------------------------------------------------------------
    | JWKS URI (Auto-Discovery)
    |--------------------------------------------------------------------------
    | When set, the guard fetches the public key from Keycloak's JWKS endpoint
    | instead of using realm_public_key. Supports key rotation automatically.
    | Constructed from base_url + realm if not set explicitly.
    | Example: 'https://keycloak.example.com/realms/myrealm/protocol/openid-connect/certs'
    */
    'jwks_uri' => env('KEYCLOAK_JWKS_URI'),

    /*
    |--------------------------------------------------------------------------
    | JWKS Cache TTL (seconds)
    |--------------------------------------------------------------------------
    | How long to cache the JWKS response. Default: 3600 (1 hour).
    */
    'jwks_cache_ttl' => env('KEYCLOAK_JWKS_CACHE_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | Base URL & Realm (for JWKS auto-discovery)
    |--------------------------------------------------------------------------
    */
    'base_url' => env('KEYCLOAK_BASE_URL'),
    'realm' => env('KEYCLOAK_REALM'),

    /*
    |--------------------------------------------------------------------------
    | Organizations (Keycloak 26+)
    |--------------------------------------------------------------------------
    | Enable Keycloak 26 Organizations feature support.
    | When enabled, the package will parse the `organization` claim
    | from JWT tokens and make it available via the KeycloakOrg facade.
    */
    'organizations' => [

        /*
         | Enable Organization Support
         | When true, the `organization` JWT claim is parsed and available.
         */
        'enabled' => env('KEYCLOAK_ORGANIZATIONS_ENABLED', false),

        /*
         | Organization Header
         | The HTTP header clients send to select the active organization
         | when a user belongs to multiple organizations.
         | Example: X-Organization: acme-corp
         */
        'header' => env('KEYCLOAK_ORGANIZATION_HEADER', 'X-Organization'),

        /*
         | Require Organization
         | When true, requests to org-scoped routes will fail with 403
         | if the token contains no organization claim.
         */
        'require' => env('KEYCLOAK_ORGANIZATION_REQUIRE', false),

        /*
         | Sync to Database
         | When true, organizations and their memberships are automatically
         | synced to your database on each request. Requires running
         | the package migrations.
         */
        'sync_to_database' => env('KEYCLOAK_ORGANIZATION_SYNC_DB', false),

        /*
         | Organization Model
         | The Eloquent model used for persisting organizations.
         */
        'model' => env('KEYCLOAK_ORGANIZATION_MODEL', Organization::class),
    ],

];
