<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $owner;

    protected User $staff;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->owner()->forTenant($this->tenant)->create();
        $this->staff = User::factory()->staff()->forTenant($this->tenant)->create();

        // Set up tenant context and create a product
        app(CurrentTenant::class)->set($this->tenant);
        $this->product = Product::factory()->forTenant($this->tenant)->create([
            'stock_quantity' => 100,
            'price' => 25.00,
        ]);
    }

    protected function withTenantHeader(array $headers = []): array
    {
        return array_merge(['X-Tenant-ID' => $this->tenant->id], $headers);
    }

    /**
     * Test owner can create an order.
     */
    public function test_owner_can_create_order(): void
    {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson('/api/v1/orders', [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2],
            ],
            'tax_rate' => 10,
        ], $this->withTenantHeader());

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'order_number',
                    'status',
                    'subtotal',
                    'tax_amount',
                    'total_amount',
                    'items',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'pending',
                    'subtotal' => 50.00, // 2 * 25.00
                    'tax_amount' => 5.00, // 10% of 50
                    'total_amount' => 55.00,
                ],
            ]);

        // Verify stock was deducted
        $this->product->refresh();
        $this->assertEquals(98, $this->product->stock_quantity);
    }

    /**
     * Test staff can create an order.
     */
    public function test_staff_can_create_order(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->postJson('/api/v1/orders', [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
        ], $this->withTenantHeader());

        $response->assertStatus(201);
    }

    /**
     * Test order creation fails when stock is insufficient.
     */
    public function test_order_creation_fails_with_insufficient_stock(): void
    {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson('/api/v1/orders', [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 150], // More than available
            ],
        ], $this->withTenantHeader());

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonPath('error.type', 'insufficient_stock');

        // Verify stock was not changed
        $this->product->refresh();
        $this->assertEquals(100, $this->product->stock_quantity);
    }

    /**
     * Test owner can cancel an order.
     */
    public function test_owner_can_cancel_order(): void
    {
        // Create an order first
        Sanctum::actingAs($this->owner);
        app(CurrentTenant::class)->set($this->tenant);

        $order = Order::factory()->forTenant($this->tenant)->pending()->create();
        $order->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit_price' => $this->product->price,
            'subtotal' => 5 * $this->product->price,
        ]);

        // Deduct stock manually for this test
        $this->product->decrement('stock_quantity', 5);
        $this->assertEquals(95, $this->product->fresh()->stock_quantity);

        // Cancel the order
        $response = $this->postJson("/api/v1/orders/{$order->id}/cancel", [], $this->withTenantHeader());

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Order cancelled successfully. Stock has been restored.',
                'data' => [
                    'status' => 'cancelled',
                ],
            ]);

        // Verify stock was restored
        $this->product->refresh();
        $this->assertEquals(100, $this->product->stock_quantity);
    }

    /**
     * Test staff cannot cancel an order.
     */
    public function test_staff_cannot_cancel_order(): void
    {
        Sanctum::actingAs($this->staff);
        app(CurrentTenant::class)->set($this->tenant);

        $order = Order::factory()->forTenant($this->tenant)->pending()->create();

        $response = $this->postJson("/api/v1/orders/{$order->id}/cancel", [], $this->withTenantHeader());

        $response->assertStatus(403);
    }

    /**
     * Test cannot cancel already cancelled order.
     */
    public function test_cannot_cancel_already_cancelled_order(): void
    {
        Sanctum::actingAs($this->owner);
        app(CurrentTenant::class)->set($this->tenant);

        $order = Order::factory()->forTenant($this->tenant)->cancelled()->create();

        $response = $this->postJson("/api/v1/orders/{$order->id}/cancel", [], $this->withTenantHeader());

        $response->assertStatus(500); // InvalidArgumentException thrown
    }

    /**
     * Test owner can update order status to paid.
     */
    public function test_owner_can_mark_order_as_paid(): void
    {
        Sanctum::actingAs($this->owner);
        app(CurrentTenant::class)->set($this->tenant);

        $order = Order::factory()->forTenant($this->tenant)->pending()->create();

        $response = $this->patchJson("/api/v1/orders/{$order->id}/status", [
            'status' => 'paid',
        ], $this->withTenantHeader());

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'paid',
                ],
            ]);

        $order->refresh();
        $this->assertNotNull($order->paid_at);
    }

    /**
     * Test order can have a customer.
     */
    public function test_order_can_be_created_with_customer(): void
    {
        Sanctum::actingAs($this->owner);
        app(CurrentTenant::class)->set($this->tenant);

        $customer = Customer::factory()->forTenant($this->tenant)->create();

        $response = $this->postJson('/api/v1/orders', [
            'customer_id' => $customer->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
        ], $this->withTenantHeader());

        $response->assertStatus(201)
            ->assertJsonPath('data.customer_id', $customer->id);
    }

    /**
     * Test order list is paginated.
     */
    public function test_orders_are_paginated(): void
    {
        Sanctum::actingAs($this->owner);
        app(CurrentTenant::class)->set($this->tenant);

        Order::factory()->count(20)->forTenant($this->tenant)->create();

        $response = $this->getJson('/api/v1/orders?per_page=5', $this->withTenantHeader());

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonCount(5, 'data');
    }

    /**
     * Test orders can be filtered by status.
     */
    public function test_orders_can_be_filtered_by_status(): void
    {
        Sanctum::actingAs($this->owner);
        app(CurrentTenant::class)->set($this->tenant);

        Order::factory()->forTenant($this->tenant)->pending()->count(3)->create();
        Order::factory()->forTenant($this->tenant)->paid()->count(2)->create();

        $response = $this->getJson('/api/v1/orders?status=paid', $this->withTenantHeader());

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    /**
     * Test transaction rollback on order creation failure.
     */
    public function test_stock_is_not_deducted_on_order_creation_failure(): void
    {
        Sanctum::actingAs($this->owner);

        $product2 = Product::factory()->forTenant($this->tenant)->create([
            'stock_quantity' => 1,
            'price' => 10.00,
        ]);

        // Try to order: 2 of product1 (has 100) and 5 of product2 (has only 1)
        $response = $this->postJson('/api/v1/orders', [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2],
                ['product_id' => $product2->id, 'quantity' => 5], // Will fail
            ],
        ], $this->withTenantHeader());

        $response->assertStatus(422);

        // Verify neither product had stock deducted (transaction rollback)
        $this->assertEquals(100, $this->product->fresh()->stock_quantity);
        $this->assertEquals(1, $product2->fresh()->stock_quantity);
    }
}
