<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';

    /** Recorded by an administrator after confirming payment outside the platform (no online charge). */
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pendiente de pago',
            self::Paid => 'Pagado',
        };
    }
}
