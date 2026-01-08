<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderService
{
    /**
     * Create a new order with the given items.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InsufficientStockException
     */
    public function createOrder(array $data, User $user): Order
    {
        return DB::transaction(function () use ($data, $user) {
            // Lock products for update to prevent race conditions
            $productIds = collect($data['items'])->pluck('product_id')->toArray();
            $products = Product::whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // Validate stock availability for all items
            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);

                if (! $product) {
                    throw new \InvalidArgumentException(
                        "Product with ID {$item['product_id']} not found."
                    );
                }

                if (! $product->hasStock($item['quantity'])) {
                    throw new InsufficientStockException(
                        $product,
                        $item['quantity'],
                        $product->stock_quantity
                    );
                }
            }

            // Generate unique order number
            $orderNumber = $this->generateOrderNumber($user->tenant_id);

            // Calculate totals
            $subtotal = 0;
            $orderItems = [];

            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);
                $unitPrice = $product->price;
                $itemSubtotal = $unitPrice * $item['quantity'];
                $subtotal += $itemSubtotal;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'subtotal' => $itemSubtotal,
                ];

                // Deduct stock
                $product->decrement('stock_quantity', $item['quantity']);
            }

            // Calculate tax and total
            $taxRate = $data['tax_rate'] ?? 0;
            $taxAmount = $subtotal * ($taxRate / 100);
            $discountAmount = $data['discount_amount'] ?? 0;
            $totalAmount = $subtotal + $taxAmount - $discountAmount;

            // Create the order
            $order = Order::create([
                'tenant_id' => $user->tenant_id,
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => $user->id,
                'order_number' => $orderNumber,
                'status' => OrderStatus::Pending,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discountAmount,
                'total_amount' => $totalAmount,
                'notes' => $data['notes'] ?? null,
            ]);

            // Create order items
            foreach ($orderItems as $item) {
                $order->items()->create($item);
            }

            return $order->load('items.product', 'customer');
        });
    }

    /**
     * Update the status of an order.
     */
    public function updateStatus(Order $order, OrderStatus $status): Order
    {
        return DB::transaction(function () use ($order, $status) {
            $order->status = $status;

            if ($status === OrderStatus::Paid) {
                $order->paid_at = now();
            }

            $order->save();

            return $order->fresh(['items.product', 'customer']);
        });
    }

    /**
     * Cancel an order and restore stock.
     *
     * @throws \InvalidArgumentException
     */
    public function cancelOrder(Order $order, User $user): Order
    {
        if (! $order->canBeCancelled()) {
            throw new \InvalidArgumentException(
                'This order cannot be cancelled. Only pending or paid orders can be cancelled.'
            );
        }

        return DB::transaction(function () use ($order) {
            // Load order items with products
            $order->load('items.product');

            // Restore stock for each item
            foreach ($order->items as $item) {
                // Lock the product for update
                $product = Product::lockForUpdate()->find($item->product_id);

                if ($product) {
                    $product->increment('stock_quantity', $item->quantity);
                }
            }

            // Update order status
            $order->status = OrderStatus::Cancelled;
            $order->cancelled_at = now();
            $order->save();

            return $order->fresh(['items.product', 'customer']);
        });
    }

    /**
     * Generate a unique order number for the tenant.
     */
    protected function generateOrderNumber(int $tenantId): string
    {
        $date = now()->format('Ymd');
        $prefix = "ORD-{$tenantId}-{$date}";

        // Get the last order number for today
        $lastOrder = Order::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('order_number', 'like', "{$prefix}-%")
            ->orderBy('order_number', 'desc')
            ->first();

        if ($lastOrder) {
            // Extract the sequence number and increment
            $parts = explode('-', $lastOrder->order_number);
            $sequence = (int) end($parts) + 1;
        } else {
            $sequence = 1;
        }

        return sprintf('%s-%04d', $prefix, $sequence);
    }

    /**
     * Calculate the totals for an order based on items.
     *
     * @param  array<array{product_id: int, quantity: int}>  $items
     * @return array{subtotal: float, items: array}
     */
    public function calculateTotals(array $items): array
    {
        $productIds = collect($items)->pluck('product_id')->toArray();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $subtotal = 0;
        $calculatedItems = [];

        foreach ($items as $item) {
            $product = $products->get($item['product_id']);

            if ($product) {
                $itemSubtotal = $product->price * $item['quantity'];
                $subtotal += $itemSubtotal;

                $calculatedItems[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                    'subtotal' => $itemSubtotal,
                ];
            }
        }

        return [
            'subtotal' => $subtotal,
            'items' => $calculatedItems,
        ];
    }
}
