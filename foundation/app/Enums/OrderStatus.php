<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';

    public function label(): string
    {
        return 'Pendiente de pago';
    }
}
