<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    /**
     * Determine whether the user can view any models.
     * Both Owner and Staff can view customers list.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     * Both Owner and Staff can view a customer if it belongs to their tenant.
     */
    public function view(User $user, Customer $customer): bool
    {
        return $user->tenant_id === $customer->tenant_id;
    }

    /**
     * Determine whether the user can create models.
     * Only Owner can create customers.
     */
    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    /**
     * Determine whether the user can update the model.
     * Only Owner can update customers within their tenant.
     */
    public function update(User $user, Customer $customer): bool
    {
        return $user->isOwner() && $user->tenant_id === $customer->tenant_id;
    }

    /**
     * Determine whether the user can delete the model.
     * Only Owner can delete customers within their tenant.
     */
    public function delete(User $user, Customer $customer): bool
    {
        return $user->isOwner() && $user->tenant_id === $customer->tenant_id;
    }

    /**
     * Determine whether the user can restore the model.
     * Only Owner can restore customers within their tenant.
     */
    public function restore(User $user, Customer $customer): bool
    {
        return $user->isOwner() && $user->tenant_id === $customer->tenant_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     * Only Owner can force delete customers within their tenant.
     */
    public function forceDelete(User $user, Customer $customer): bool
    {
        return $user->isOwner() && $user->tenant_id === $customer->tenant_id;
    }
}
