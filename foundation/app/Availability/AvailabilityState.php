<?php

namespace App\Availability;

enum AvailabilityState: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';
    case Stale = 'stale';
    case Unknown = 'unknown';

    /** Stale and unknown products are hidden from the public catalog (V1-B-SCOPE §2-§3). */
    public function isPubliclyVisible(): bool
    {
        return $this === self::Available || $this === self::Unavailable;
    }

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Disponible',
            self::Unavailable => 'No disponible',
            self::Stale => 'Vencido',
            self::Unknown => 'Desconocido',
        };
    }
}
