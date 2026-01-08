<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReportController extends Controller
{
    public function __construct(protected ReportService $reportService) {}

    /**
     * Get daily sales summary.
     */
    public function dailySales(Request $request): JsonResponse
    {
        Gate::authorize('view-daily-sales');

        $request->validate([
            'date' => ['sometimes', 'date'],
        ]);

        $date = $request->has('date')
            ? $request->date('date')
            : now();
        $tenantId = $request->user()->tenant_id;

        $report = $this->reportService->getDailySalesSummary($date, $tenantId);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Get top selling products report.
     */
    public function topSellingProducts(Request $request): JsonResponse
    {
        Gate::authorize('view-top-selling-products');

        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $startDate = $request->date('start_date');
        $endDate = $request->date('end_date');
        $limit = $request->input('limit', 5);
        $tenantId = $request->user()->tenant_id;

        $report = $this->reportService->getTopSellingProducts(
            $startDate,
            $endDate,
            $tenantId,
            $limit
        );

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Get low stock products report.
     */
    public function lowStock(Request $request): JsonResponse
    {
        Gate::authorize('view-low-stock');

        $tenantId = $request->user()->tenant_id;

        $report = $this->reportService->getLowStockProducts($tenantId);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }
}
