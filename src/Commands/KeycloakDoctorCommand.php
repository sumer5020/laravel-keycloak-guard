<?php

namespace KeycloakGuard\Commands;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

class KeycloakDoctorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'keycloak:doctor';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the health and configuration of the Keycloak integration.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Laravel Keycloak Guard "Doctor" — Skill Diagnostic');
        $this->newLine();

        $this->checkConfiguration();
        $this->checkKeycloakConnectivity();
        $this->checkOrganizationSupport();

        $this->newLine();
        $this->info('🏁 Diagnostic complete.');

        return 0;
    }

    private function checkConfiguration(): void
    {
        $this->comment('--- [Configuration Check] ---');

        $baseUrl = config('keycloak.base_url');
        $realm = config('keycloak.realm');
        $clientId = config('keycloak.allowed_resources');

        if ($baseUrl) {
            $this->line("✅ Base URL: {$baseUrl}");
        } else {
            $this->error('❌ Base URL (KEYCLOAK_BASE_URL) is not set.');
        }

        if ($realm) {
            $this->line("✅ Realm: {$realm}");
        } else {
            $this->error('❌ Realm (KEYCLOAK_REALM) is not set.');
        }

        if ($clientId) {
            $this->line("✅ Allowed Resources (Client ID): {$clientId}");
        } else {
            $this->error('❌ Allowed Resources (KEYCLOAK_ALLOWED_RESOURCES) is not set.');
        }

        $jwksUri = config('keycloak.jwks_uri');
        if ($jwksUri) {
            $this->line("✅ JWKS URI: {$jwksUri}");
        } else {
            $this->line('ℹ️ JWKS URI: (Auto-calculated from Base URL and Realm)');
        }
    }

    private function checkKeycloakConnectivity(): void
    {
        $this->newLine();
        $this->comment('--- [Connectivity Check] ---');

        $baseUrl = config('keycloak.base_url');
        if (! $baseUrl) {
            $this->error('❌ Cannot check connectivity: Base URL is missing.');

            return;
        }

        $discoveryUrl = rtrim($baseUrl, '/').'/realms/'.config('keycloak.realm').'/.well-known/openid-configuration';

        $this->line("Fetching OIDC discovery: {$discoveryUrl}");

        try {
            $client = new Client(['timeout' => 5]);
            $response = $client->get($discoveryUrl);

            if ($response->getStatusCode() === 200) {
                $this->line('✅ Keycloak Realm is reachable.');
                $data = json_decode($response->getBody(), true);
                $this->line('   Issuer: '.($data['issuer'] ?? 'N/A'));
            } else {
                $this->error("❌ Keycloak returned status {$response->getStatusCode()}.");
            }
        } catch (Exception $e) {
            $this->error('❌ Connection failed: '.$e->getMessage());
        }
    }

    private function checkOrganizationSupport(): void
    {
        $this->newLine();
        $this->comment('--- [Organization Support] ---');

        if (config('keycloak.organizations.enabled', false)) {
            $this->line('✅ Organizations: Enabled');
            $this->line('   Mode: '.(config('keycloak.organizations.sync_to_database') ? 'Database Sync' : 'Token-Only (Stateless)'));
        } else {
            $this->line('ℹ️ Organizations: Disabled');
        }
    }
}
