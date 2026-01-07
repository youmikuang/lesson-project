<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Confirmed = 'confirmed';
    case Waitlisted = 'waitlisted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => '已确认',
            self::Waitlisted => '候补中',
            self::Cancelled => '已取消',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Confirmed, self::Waitlisted]);
    }
}
