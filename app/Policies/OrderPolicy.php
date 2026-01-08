<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Determine whether the user can view any models.
     * Both Owner and Staff can view orders list.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     * Both Owner and Staff can view an order if it belongs to their tenant.
     */
    public function view(User $user, Order $order): bool
    {
        return $user->tenant_id === $order->tenant_id;
    }

    /**
     * Determine whether the user can create models.
     * Both Owner and Staff can create orders.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     * Only Owner can update orders within their tenant.
     */
    public function update(User $user, Order $order): bool
    {
        return $user->isOwner() && $user->tenant_id === $order->tenant_id;
    }

    /**
     * Determine whether the user can cancel the order.
     * Only Owner can cancel orders within their tenant.
     */
    public function cancel(User $user, Order $order): bool
    {
        return $user->isOwner() && $user->tenant_id === $order->tenant_id;
    }

    /**
     * Determine whether the user can delete the model.
     * Only Owner can delete orders within their tenant.
     */
    public function delete(User $user, Order $order): bool
    {
        return $user->isOwner() && $user->tenant_id === $order->tenant_id;
    }

    /**
     * Determine whether the user can restore the model.
     * Only Owner can restore orders within their tenant.
     */
    public function restore(User $user, Order $order): bool
    {
        return $user->isOwner() && $user->tenant_id === $order->tenant_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     * Only Owner can force delete orders within their tenant.
     */
    public function forceDelete(User $user, Order $order): bool
    {
        return $user->isOwner() && $user->tenant_id === $order->tenant_id;
    }
}
