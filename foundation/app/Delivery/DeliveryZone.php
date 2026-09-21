<?php

namespace App\Delivery;

enum DeliveryZone: string
{
    case Gam = 'gam';
    case GuanacasteNorte = 'guanacaste_norte';
    case Rest = 'rest';

    public function label(): string
    {
        return match ($this) {
            self::Gam => 'Gran Área Metropolitana',
            self::GuanacasteNorte => 'Guanacaste norte',
            self::Rest => 'Resto del país',
        };
    }
}
