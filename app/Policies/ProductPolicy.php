<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /**
     * Determine whether the user can view any models.
     * Both Owner and Staff can view products list.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     * Both Owner and Staff can view a product if it belongs to their tenant.
     */
    public function view(User $user, Product $product): bool
    {
        return $user->tenant_id === $product->tenant_id;
    }

    /**
     * Determine whether the user can create models.
     * Only Owner can create products.
     */
    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    /**
     * Determine whether the user can update the model.
     * Only Owner can update products within their tenant.
     */
    public function update(User $user, Product $product): bool
    {
        return $user->isOwner() && $user->tenant_id === $product->tenant_id;
    }

    /**
     * Determine whether the user can delete the model.
     * Only Owner can delete products within their tenant.
     */
    public function delete(User $user, Product $product): bool
    {
        return $user->isOwner() && $user->tenant_id === $product->tenant_id;
    }

    /**
     * Determine whether the user can restore the model.
     * Only Owner can restore products within their tenant.
     */
    public function restore(User $user, Product $product): bool
    {
        return $user->isOwner() && $user->tenant_id === $product->tenant_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     * Only Owner can force delete products within their tenant.
     */
    public function forceDelete(User $user, Product $product): bool
    {
        return $user->isOwner() && $user->tenant_id === $product->tenant_id;
    }
}
