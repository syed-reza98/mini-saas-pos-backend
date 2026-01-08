<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Order\StoreOrderRequest;
use App\Http\Requests\Api\Order\UpdateOrderStatusRequest;
use App\Http\Resources\Api\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function __construct(protected OrderService $orderService) {}

    /**
     * Display a listing of orders.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Order::class);

        $query = Order::query()
            ->with(['customer', 'items.product'])
            ->withCount('items');

        // Filter by status
        if ($request->has('status')) {
            $status = OrderStatus::tryFrom($request->input('status'));
            if ($status) {
                $query->status($status);
            }
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->betweenDates(
                $request->date('start_date'),
                $request->date('end_date')
            );
        } elseif ($request->has('date')) {
            $query->onDate($request->date('date'));
        }

        // Filter by customer
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $perPage = $request->input('per_page', 15);
        $orders = $query->paginate($perPage);

        return OrderResource::collection($orders);
    }

    /**
     * Store a newly created order.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $this->authorize('create', Order::class);

        $order = $this->orderService->createOrder(
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully.',
            'data' => new OrderResource($order),
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified order.
     */
    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $order->load(['customer', 'items.product', 'user']);

        return response()->json([
            'success' => true,
            'data' => new OrderResource($order),
        ]);
    }

    /**
     * Update the order status.
     */
    public function update(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $this->authorize('update', $order);

        $status = OrderStatus::from($request->input('status'));
        $order = $this->orderService->updateStatus($order, $status);

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully.',
            'data' => new OrderResource($order),
        ]);
    }

    /**
     * Cancel an order.
     */
    public function cancel(Order $order): JsonResponse
    {
        $this->authorize('cancel', $order);

        $order = $this->orderService->cancelOrder($order, request()->user());

        return response()->json([
            'success' => true,
            'message' => 'Order cancelled successfully. Stock has been restored.',
            'data' => new OrderResource($order),
        ]);
    }

    /**
     * Remove the specified order.
     */
    public function destroy(Order $order): JsonResponse
    {
        $this->authorize('delete', $order);

        // Only allow deletion of cancelled orders
        if (! $order->isCancelled()) {
            return response()->json([
                'success' => false,
                'message' => 'Only cancelled orders can be deleted.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $order->delete();

        return response()->json([
            'success' => true,
            'message' => 'Order deleted successfully.',
        ]);
    }
}
