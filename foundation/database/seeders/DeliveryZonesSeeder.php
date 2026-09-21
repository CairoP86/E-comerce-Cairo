<?php

namespace Database\Seeders;

use App\Delivery\DeliveryZone;
use App\Models\DeliveryRate;
use App\Models\DeliveryRateSet;
use App\Models\DeliveryZoneCanton;
use App\Support\CostaRicaTerritories;
use Illuminate\Database\Seeder;

/**
 * Approved delivery zones and rates (owner decision, 2026-09-19). Idempotent and additive:
 * it never deletes rows and never touches orders. Rates are versioned so past orders keep theirs.
 */
class DeliveryZonesSeeder extends Seeder
{
    public const VERSION = 'v1-2026-09';

    /** The 31 GAM cantons, verified against database/data/cr-territories-2026.json. */
    public const GAM_CANTONS = [
        '101', '102', '103', '106', '107', '108', '109', '110', '111', '113', '114', '115', '118',
        '201', '205', '208',
        '301', '302', '303', '306', '307', '308',
        '401', '402', '403', '404', '405', '406', '407', '408', '409',
    ];

    /** Liberia, Bagaces, Cañas and La Cruz: delivery is always free. */
    public const GUANACASTE_NORTE_CANTONS = ['501', '504', '506', '510'];

    public function run(): void
    {
        foreach (array_unique(array_column(CostaRicaTerritories::all(), 'canton_code')) as $canton) {
            $zone = match (true) {
                in_array($canton, self::GAM_CANTONS, true) => DeliveryZone::Gam,
                in_array($canton, self::GUANACASTE_NORTE_CANTONS, true) => DeliveryZone::GuanacasteNorte,
                default => DeliveryZone::Rest,
            };
            DeliveryZoneCanton::updateOrCreate(['canton_code' => $canton], ['zone' => $zone]);
        }
        $set = DeliveryRateSet::firstOrCreate(
            ['version' => self::VERSION],
            ['effective_from' => '2026-09-19 00:00:00', 'notes' => 'Tarifa plana con envío gratis por monto mínimo; estudio de mercado 2026-09.']
        );
        foreach ([
            [DeliveryZone::Gam, 350000, 9000000],
            [DeliveryZone::GuanacasteNorte, 0, null],
            [DeliveryZone::Rest, 450000, 11000000],
        ] as [$zone, $flat, $freeFrom]) {
            DeliveryRate::updateOrCreate(
                ['delivery_rate_set_id' => $set->id, 'zone' => $zone],
                ['flat_minor' => $flat, 'free_from_minor' => $freeFrom, 'currency' => 'CRC']
            );
        }
    }
}
