<?php

namespace App\Services;

use App\Models\Tenant;

class CurrentTenant
{
    /**
     * The current tenant instance.
     */
    protected ?Tenant $tenant = null;

    /**
     * Set the current tenant.
     */
    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    /**
     * Set the current tenant by ID.
     */
    public function setById(int $tenantId): void
    {
        $this->tenant = Tenant::find($tenantId);
    }

    /**
     * Get the current tenant.
     */
    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * Get the current tenant ID.
     */
    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    /**
     * Check if a tenant is currently set.
     */
    public function check(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * Clear the current tenant.
     */
    public function forget(): void
    {
        $this->tenant = null;
    }

    /**
     * Check if the current tenant is active.
     */
    public function isActive(): bool
    {
        return $this->tenant?->is_active ?? false;
    }
}
