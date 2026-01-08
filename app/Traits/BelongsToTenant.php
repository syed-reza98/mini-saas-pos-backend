<?php

namespace App\Traits;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Services\CurrentTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    /**
     * Boot the BelongsToTenant trait.
     *
     * Automatically adds the TenantScope to filter queries by tenant_id
     * and sets the tenant_id when creating new models.
     */
    protected static function bootBelongsToTenant(): void
    {
        // Add global scope to filter by tenant_id
        static::addGlobalScope(new TenantScope);

        // Automatically set tenant_id when creating a new model
        static::creating(function ($model) {
            $currentTenant = app(CurrentTenant::class);

            if ($currentTenant->check() && empty($model->tenant_id)) {
                $model->tenant_id = $currentTenant->id();
            }
        });
    }

    /**
     * Get the tenant that this model belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope a query to a specific tenant.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForTenant($query, int $tenantId)
    {
        return $query->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId);
    }
}
