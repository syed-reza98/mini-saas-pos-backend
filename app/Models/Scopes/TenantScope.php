<?php

namespace App\Models\Scopes;

use App\Services\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * This ensures that all queries for tenant-scoped models
     * are automatically filtered by the current tenant ID.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $currentTenant = app(CurrentTenant::class);

        if ($currentTenant->check()) {
            $builder->where($model->getTable().'.tenant_id', $currentTenant->id());
        }
    }
}
