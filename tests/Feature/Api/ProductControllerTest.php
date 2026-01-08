<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $owner;

    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->owner()->forTenant($this->tenant)->create();
        $this->staff = User::factory()->staff()->forTenant($this->tenant)->create();
    }

    protected function withTenantHeader(array $headers = []): array
    {
        return array_merge(['X-Tenant-ID' => $this->tenant->id], $headers);
    }

    /**
     * Test owner can view all products.
     */
    public function test_owner_can_view_all_products(): void
    {
        app(CurrentTenant::class)->set($this->tenant);
        Product::factory()->count(5)->forTenant($this->tenant)->create();

        Sanctum::actingAs($this->owner);

        $response = $this->getJson('/api/v1/products', $this->withTenantHeader());

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'sku', 'price', 'stock_quantity', 'is_low_stock'],
                ],
                'meta' => ['current_page', 'total'],
            ]);
    }

    /**
     * Test staff can view all products.
     */
    public function test_staff_can_view_all_products(): void
    {
        app(CurrentTenant::class)->set($this->tenant);
        Product::factory()->count(3)->forTenant($this->tenant)->create();

        Sanctum::actingAs($this->staff);

        $response = $this->getJson('/api/v1/products', $this->withTenantHeader());

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    /**
     * Test owner can create a product.
     */
    public function test_owner_can_create_product(): void
    {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson('/api/v1/products', [
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'description' => 'A test product description',
            'price' => 99.99,
            'stock_quantity' => 50,
            'low_stock_threshold' => 10,
        ], $this->withTenantHeader());

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Product created successfully.',
                'data' => [
                    'name' => 'Test Product',
                    'sku' => 'TEST-001',
                    'price' => 99.99,
                    'stock_quantity' => 50,
                ],
            ]);

        $this->assertDatabaseHas('products', [
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /**
     * Test staff cannot create a product.
     */
    public function test_staff_cannot_create_product(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->postJson('/api/v1/products', [
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock_quantity' => 50,
            'low_stock_threshold' => 10,
        ], $this->withTenantHeader());

        $response->assertStatus(403);
    }

    /**
     * Test owner can update a product.
     */
    public function test_owner_can_update_product(): void
    {
        app(CurrentTenant::class)->set($this->tenant);
        $product = Product::factory()->forTenant($this->tenant)->create();

        Sanctum::actingAs($this->owner);

        $response = $this->putJson("/api/v1/products/{$product->id}", [
            'name' => 'Updated Product Name',
            'price' => 149.99,
        ], $this->withTenantHeader());

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Updated Product Name',
                    'price' => 149.99,
                ],
            ]);
    }

    /**
     * Test staff cannot update a product.
     */
    public function test_staff_cannot_update_product(): void
    {
        app(CurrentTenant::class)->set($this->tenant);
        $product = Product::factory()->forTenant($this->tenant)->create();

        Sanctum::actingAs($this->staff);

        $response = $this->putJson("/api/v1/products/{$product->id}", [
            'name' => 'Updated Product Name',
        ], $this->withTenantHeader());

        $response->assertStatus(403);
    }

    /**
     * Test owner can delete a product.
     */
    public function test_owner_can_delete_product(): void
    {
        app(CurrentTenant::class)->set($this->tenant);
        $product = Product::factory()->forTenant($this->tenant)->create();

        Sanctum::actingAs($this->owner);

        $response = $this->deleteJson("/api/v1/products/{$product->id}", [], $this->withTenantHeader());

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Product deleted successfully.',
            ]);

        // Product should be soft deleted
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    /**
     * Test SKU must be unique per tenant.
     */
    public function test_sku_must_be_unique_per_tenant(): void
    {
        app(CurrentTenant::class)->set($this->tenant);
        Product::factory()->forTenant($this->tenant)->create(['sku' => 'UNIQUE-SKU']);

        Sanctum::actingAs($this->owner);

        $response = $this->postJson('/api/v1/products', [
            'name' => 'Duplicate SKU Product',
            'sku' => 'UNIQUE-SKU',
            'price' => 50.00,
            'stock_quantity' => 10,
            'low_stock_threshold' => 5,
        ], $this->withTenantHeader());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sku']);
    }

    /**
     * Test same SKU can exist in different tenants.
     */
    public function test_same_sku_can_exist_in_different_tenants(): void
    {
        // Create product in another tenant with same SKU
        $otherTenant = Tenant::factory()->create();
        Product::factory()->forTenant($otherTenant)->create(['sku' => 'SHARED-SKU']);

        Sanctum::actingAs($this->owner);

        // Should succeed because SKU is unique per tenant
        $response = $this->postJson('/api/v1/products', [
            'name' => 'My Product',
            'sku' => 'SHARED-SKU',
            'price' => 50.00,
            'stock_quantity' => 10,
            'low_stock_threshold' => 5,
        ], $this->withTenantHeader());

        $response->assertStatus(201);
    }

    /**
     * Test products can be filtered by low stock.
     */
    public function test_products_can_be_filtered_by_low_stock(): void
    {
        app(CurrentTenant::class)->set($this->tenant);

        Product::factory()->forTenant($this->tenant)->count(3)->create([
            'stock_quantity' => 100,
            'low_stock_threshold' => 10,
        ]);

        Product::factory()->forTenant($this->tenant)->count(2)->lowStock()->create();

        Sanctum::actingAs($this->owner);

        $response = $this->getJson('/api/v1/products?low_stock=true', $this->withTenantHeader());

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    /**
     * Test products can be searched.
     */
    public function test_products_can_be_searched(): void
    {
        app(CurrentTenant::class)->set($this->tenant);

        Product::factory()->forTenant($this->tenant)->create(['name' => 'Apple iPhone']);
        Product::factory()->forTenant($this->tenant)->create(['name' => 'Samsung Galaxy']);
        Product::factory()->forTenant($this->tenant)->create(['sku' => 'APPLE-001']);

        Sanctum::actingAs($this->owner);

        $response = $this->getJson('/api/v1/products?search=Apple', $this->withTenantHeader());

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data'); // iPhone name + APPLE-001 SKU
    }

    /**
     * Test products are paginated.
     */
    public function test_products_are_paginated(): void
    {
        app(CurrentTenant::class)->set($this->tenant);
        Product::factory()->count(25)->forTenant($this->tenant)->create();

        Sanctum::actingAs($this->owner);

        $response = $this->getJson('/api/v1/products?per_page=10', $this->withTenantHeader());

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 25);
    }
}
