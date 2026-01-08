<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected User $userA;

    protected User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        // Create two tenants
        $this->tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Tenant B']);

        // Create users for each tenant
        $this->userA = User::factory()->owner()->forTenant($this->tenantA)->create();
        $this->userB = User::factory()->owner()->forTenant($this->tenantB)->create();
    }

    /**
     * Test that products are isolated by tenant.
     */
    public function test_products_are_isolated_by_tenant(): void
    {
        // Create products for each tenant
        app(CurrentTenant::class)->set($this->tenantA);
        $productA = Product::factory()->forTenant($this->tenantA)->create(['name' => 'Product A']);

        app(CurrentTenant::class)->set($this->tenantB);
        $productB = Product::factory()->forTenant($this->tenantB)->create(['name' => 'Product B']);

        // Authenticate as user from Tenant A
        Sanctum::actingAs($this->userA);
        app(CurrentTenant::class)->set($this->tenantA);

        // User A should see Product A but not Product B
        $products = Product::all();
        $this->assertCount(1, $products);
        $this->assertEquals('Product A', $products->first()->name);

        // Switch to user from Tenant B
        Sanctum::actingAs($this->userB);
        app(CurrentTenant::class)->set($this->tenantB);

        // User B should see Product B but not Product A
        $products = Product::all();
        $this->assertCount(1, $products);
        $this->assertEquals('Product B', $products->first()->name);
    }

    /**
     * Test that customers are isolated by tenant.
     */
    public function test_customers_are_isolated_by_tenant(): void
    {
        // Create customers for each tenant
        app(CurrentTenant::class)->set($this->tenantA);
        $customerA = Customer::factory()->forTenant($this->tenantA)->create(['name' => 'Customer A']);

        app(CurrentTenant::class)->set($this->tenantB);
        $customerB = Customer::factory()->forTenant($this->tenantB)->create(['name' => 'Customer B']);

        // Verify isolation for Tenant A
        app(CurrentTenant::class)->set($this->tenantA);
        $customers = Customer::all();
        $this->assertCount(1, $customers);
        $this->assertEquals('Customer A', $customers->first()->name);

        // Verify isolation for Tenant B
        app(CurrentTenant::class)->set($this->tenantB);
        $customers = Customer::all();
        $this->assertCount(1, $customers);
        $this->assertEquals('Customer B', $customers->first()->name);
    }

    /**
     * Test that API requests require X-Tenant-ID header.
     */
    public function test_api_requires_tenant_header(): void
    {
        Sanctum::actingAs($this->userA);

        // Request without X-Tenant-ID header should fail
        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Missing required header: X-Tenant-ID',
            ]);
    }

    /**
     * Test that API request with valid tenant header works.
     */
    public function test_api_works_with_valid_tenant_header(): void
    {
        Sanctum::actingAs($this->userA);

        // Request with valid X-Tenant-ID header should succeed
        $response = $this->getJson('/api/v1/products', [
            'X-Tenant-ID' => $this->tenantA->id,
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test that user cannot access data from another tenant via API.
     */
    public function test_user_cannot_access_other_tenant_products_via_api(): void
    {
        // Create product for Tenant B
        $productB = Product::factory()->forTenant($this->tenantB)->create(['name' => 'Secret Product']);

        // Authenticate as user from Tenant A
        Sanctum::actingAs($this->userA);

        // Try to access products with Tenant A's header
        $response = $this->getJson('/api/v1/products', [
            'X-Tenant-ID' => $this->tenantA->id,
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');

        // Should not see Tenant B's product
        $productNames = collect($data)->pluck('name')->toArray();
        $this->assertNotContains('Secret Product', $productNames);
    }

    /**
     * Test that user cannot access specific product from another tenant.
     */
    public function test_user_cannot_access_specific_product_from_other_tenant(): void
    {
        // Create product for Tenant B
        $productB = Product::factory()->forTenant($this->tenantB)->create();

        // Authenticate as user from Tenant A
        Sanctum::actingAs($this->userA);

        // Try to access Tenant B's product directly
        $response = $this->getJson("/api/v1/products/{$productB->id}", [
            'X-Tenant-ID' => $this->tenantA->id,
        ]);

        // Should return 403 (policy denies access) or 404 (scope filters it out)
        // Either behavior is correct for tenant isolation
        $response->assertStatus(403);
    }

    /**
     * Test that inactive tenant cannot access API.
     */
    public function test_inactive_tenant_cannot_access_api(): void
    {
        // Deactivate Tenant A
        $this->tenantA->update(['is_active' => false]);

        Sanctum::actingAs($this->userA);

        $response = $this->getJson('/api/v1/products', [
            'X-Tenant-ID' => $this->tenantA->id,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Tenant is inactive.',
            ]);
    }

    /**
     * Test that products created via API belong to correct tenant.
     */
    public function test_products_created_via_api_belong_to_correct_tenant(): void
    {
        Sanctum::actingAs($this->userA);

        $response = $this->postJson('/api/v1/products', [
            'name' => 'New Product',
            'sku' => 'NEW-001',
            'price' => 29.99,
            'stock_quantity' => 100,
            'low_stock_threshold' => 10,
        ], [
            'X-Tenant-ID' => $this->tenantA->id,
        ]);

        $response->assertStatus(201);

        // Verify the product belongs to Tenant A
        $product = Product::withoutGlobalScopes()->latest()->first();
        $this->assertEquals($this->tenantA->id, $product->tenant_id);
    }
}
