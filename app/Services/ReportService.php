<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Cache TTL in seconds (30 minutes).
     */
    protected const CACHE_TTL = 1800;

    /**
     * Get daily sales summary for a specific date.
     *
     * @return array{date: string, total_orders: int, total_revenue: float, average_order_value: float, orders_by_status: array}
     */
    public function getDailySalesSummary(Carbon $date, int $tenantId): array
    {
        $cacheKey = "daily_sales_{$tenantId}_{$date->format('Y-m-d')}";

        // Only cache if the date is in the past
        if ($date->isToday()) {
            return $this->calculateDailySalesSummary($date, $tenantId);
        }

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($date, $tenantId) {
            return $this->calculateDailySalesSummary($date, $tenantId);
        });
    }

    /**
     * Calculate daily sales summary.
     */
    protected function calculateDailySalesSummary(Carbon $date, int $tenantId): array
    {
        $orders = Order::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereDate('created_at', $date)
            ->get();

        $paidOrders = $orders->where('status', OrderStatus::Paid);
        $totalRevenue = (float) $paidOrders->sum('total_amount');
        $totalOrders = $paidOrders->count();
        $averageOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        $ordersByStatus = [];
        foreach (OrderStatus::cases() as $status) {
            $ordersByStatus[$status->value] = $orders->where('status', $status)->count();
        }

        return [
            'date' => $date->format('Y-m-d'),
            'total_orders' => $totalOrders,
            'total_revenue' => round($totalRevenue, 2),
            'average_order_value' => round($averageOrderValue, 2),
            'orders_by_status' => $ordersByStatus,
        ];
    }

    /**
     * Get top selling products for a date range.
     *
     * @return array{start_date: string, end_date: string, products: array}
     */
    public function getTopSellingProducts(
        Carbon $startDate,
        Carbon $endDate,
        int $tenantId,
        int $limit = 5
    ): array {
        $products = OrderItem::query()
            ->select([
                'products.id',
                'products.name',
                'products.sku',
                DB::raw('SUM(order_items.quantity) as total_quantity_sold'),
                DB::raw('SUM(order_items.subtotal) as total_revenue'),
            ])
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('products.tenant_id', $tenantId)
            ->where('orders.status', OrderStatus::Paid->value)
            ->whereBetween('orders.created_at', [
                $startDate->startOfDay(),
                $endDate->endOfDay(),
            ])
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('total_quantity_sold')
            ->limit($limit)
            ->get();

        return [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'products' => $products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'total_quantity_sold' => (int) $product->total_quantity_sold,
                    'total_revenue' => round((float) $product->total_revenue, 2),
                ];
            })->toArray(),
        ];
    }

    /**
     * Get low stock products report.
     *
     * @return array{total_low_stock: int, products: array}
     */
    public function getLowStockProducts(int $tenantId): array
    {
        $products = Product::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->orderBy('stock_quantity')
            ->get(['id', 'name', 'sku', 'stock_quantity', 'low_stock_threshold']);

        return [
            'total_low_stock' => $products->count(),
            'products' => $products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'stock_quantity' => $product->stock_quantity,
                    'low_stock_threshold' => $product->low_stock_threshold,
                    'shortage' => $product->low_stock_threshold - $product->stock_quantity,
                ];
            })->toArray(),
        ];
    }

    /**
     * Invalidate daily sales cache for a specific tenant and date.
     */
    public function invalidateDailySalesCache(int $tenantId, Carbon $date): void
    {
        $cacheKey = "daily_sales_{$tenantId}_{$date->format('Y-m-d')}";
        Cache::forget($cacheKey);
    }
}
