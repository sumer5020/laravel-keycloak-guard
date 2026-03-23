<?php

namespace KeycloakGuard\Tests\Unit;

use Illuminate\Http\Request;
use KeycloakGuard\Exceptions\OrganizationException;
use KeycloakGuard\Services\OrganizationService;
use KeycloakGuard\Tests\TestCase;

class OrganizationServiceTest extends TestCase
{
    private function makeService(array $headers = []): OrganizationService
    {
        $request = Request::create('/test', 'GET', [], [], [], []);
        foreach ($headers as $key => $value) {
            $request->headers->set($key, $value);
        }

        return new OrganizationService($request);
    }

    private function makeToken(array $orgs): object
    {
        return json_decode(json_encode(['organization' => $orgs]));
    }

    public function test_current_returns_null_when_no_org_in_token(): void
    {
        $service = $this->makeService();
        $service->boot(json_decode(json_encode(['sub' => 'uuid'])));

        $this->assertNull($service->current());
    }

    public function test_current_returns_first_org_by_default(): void
    {
        $orgs = [
            'acme-corp' => ['id' => 'org-1', 'name' => 'Acme', 'roles' => ['admin']],
            'beta-inc' => ['id' => 'org-2', 'name' => 'Beta', 'roles' => ['member']],
        ];

        $service = $this->makeService();
        $service->boot($this->makeToken($orgs));

        $this->assertSame('acme-corp', $service->current()['alias']);
    }

    public function test_current_reads_org_from_header(): void
    {
        $orgs = [
            'acme-corp' => ['id' => 'org-1', 'name' => 'Acme', 'roles' => ['admin']],
            'beta-inc' => ['id' => 'org-2', 'name' => 'Beta', 'roles' => ['member']],
        ];

        $service = $this->makeService(['X-Organization' => 'beta-inc']);
        $service->boot($this->makeToken($orgs));

        $this->assertSame('beta-inc', $service->current()['alias']);
    }

    public function test_current_throws_on_invalid_org_header(): void
    {
        $orgs = ['acme-corp' => ['id' => 'org-1', 'name' => 'Acme', 'roles' => []]];
        $service = $this->makeService(['X-Organization' => 'unknown-org']);
        $service->boot($this->makeToken($orgs));

        $this->expectException(OrganizationException::class);
        $service->current();
    }

    public function test_has_role_checks_active_org(): void
    {
        $orgs = ['acme-corp' => ['id' => 'org-1', 'name' => 'Acme', 'roles' => ['admin', 'member']]];
        $service = $this->makeService();
        $service->boot($this->makeToken($orgs));

        $this->assertTrue($service->hasRole('admin'));
        $this->assertTrue($service->hasRole('member'));
        $this->assertFalse($service->hasRole('billing-manager'));
    }

    public function test_has_any_role_returns_true_if_one_matches(): void
    {
        $orgs = ['acme-corp' => ['id' => 'org-1', 'name' => 'Acme', 'roles' => ['member']]];
        $service = $this->makeService();
        $service->boot($this->makeToken($orgs));

        $this->assertTrue($service->hasAnyRole(['admin', 'member']));
        $this->assertFalse($service->hasAnyRole(['admin', 'editor']));
    }

    public function test_belongs_to_returns_correct_result(): void
    {
        $orgs = ['acme-corp' => ['id' => 'org-1', 'name' => 'Acme', 'roles' => []]];
        $service = $this->makeService();
        $service->boot($this->makeToken($orgs));

        $this->assertTrue($service->belongsTo('acme-corp'));
        $this->assertFalse($service->belongsTo('beta-inc'));
    }

    public function test_all_returns_normalized_org_array(): void
    {
        $orgs = [
            'acme-corp' => ['id' => 'org-1', 'name' => 'Acme', 'roles' => ['admin']],
            'beta-inc' => ['id' => 'org-2', 'name' => 'Beta', 'roles' => ['member']],
        ];

        $service = $this->makeService();
        $service->boot($this->makeToken($orgs));

        $all = $service->all();

        $this->assertCount(2, $all);
        $this->assertSame('acme-corp', $all[0]['alias']);
        $this->assertSame('beta-inc', $all[1]['alias']);
    }
}
