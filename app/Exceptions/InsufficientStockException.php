<?php

namespace App\Exceptions;

use App\Models\Product;
use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class InsufficientStockException extends Exception
{
    /**
     * Create a new insufficient stock exception.
     */
    public function __construct(
        public readonly Product $product,
        public readonly int $requestedQuantity,
        public readonly int $availableQuantity
    ) {
        $message = sprintf(
            'Insufficient stock for product "%s" (SKU: %s). Requested: %d, Available: %d',
            $product->name,
            $product->sku,
            $requestedQuantity,
            $availableQuantity
        );

        parent::__construct($message);
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'error' => [
                'type' => 'insufficient_stock',
                'product_id' => $this->product->id,
                'product_name' => $this->product->name,
                'product_sku' => $this->product->sku,
                'requested_quantity' => $this->requestedQuantity,
                'available_quantity' => $this->availableQuantity,
            ],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
