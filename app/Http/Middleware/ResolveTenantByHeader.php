<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantByHeader
{
    /**
     * The header name used to identify the tenant.
     */
    protected const TENANT_HEADER = 'X-Tenant-ID';

    /**
     * Create a new middleware instance.
     */
    public function __construct(protected CurrentTenant $currentTenant) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->header(self::TENANT_HEADER);

        if (empty($tenantId)) {
            return response()->json([
                'success' => false,
                'message' => 'Missing required header: '.self::TENANT_HEADER,
            ], Response::HTTP_BAD_REQUEST);
        }

        if (! is_numeric($tenantId)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid tenant ID format.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $tenant = Tenant::find((int) $tenantId);

        if (! $tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        if (! $tenant->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant is inactive.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Set the current tenant in the service
        $this->currentTenant->set($tenant);

        // Also store in request for easy access
        $request->attributes->set('tenant', $tenant);
        $request->attributes->set('tenant_id', $tenant->id);

        return $next($request);
    }
}
