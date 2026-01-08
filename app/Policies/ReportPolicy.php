<?php

namespace App\Policies;

use App\Models\User;

class ReportPolicy
{
    /**
     * Determine whether the user can view daily sales report.
     * Both Owner and Staff can view reports.
     */
    public function viewDailySales(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view top selling products report.
     * Both Owner and Staff can view reports.
     */
    public function viewTopSellingProducts(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view low stock report.
     * Both Owner and Staff can view reports.
     */
    public function viewLowStock(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view any reports.
     * Both Owner and Staff can view reports.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }
}
