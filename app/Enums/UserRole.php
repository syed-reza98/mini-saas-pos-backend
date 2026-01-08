<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Staff = 'staff';

    /**
     * Get human-readable label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Staff => 'Staff',
        };
    }

    /**
     * Check if the role has owner privileges.
     */
    public function isOwner(): bool
    {
        return $this === self::Owner;
    }

    /**
     * Check if the role has staff privileges.
     */
    public function isStaff(): bool
    {
        return $this === self::Staff;
    }
}
