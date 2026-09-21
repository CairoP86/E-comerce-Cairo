<?php

namespace Tests\Feature;

use App\Delivery\DeliveryZone;
use App\Models\DeliveryRate;
use App\Models\DeliveryRateSet;
use App\Models\DeliveryZoneCanton;
use App\Support\CostaRicaTerritories;
use Database\Seeders\DeliveryZonesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryZonesSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DeliveryZonesSeeder::class);
    }

    private function cantonCodes(): array
    {
        return array_values(array_unique(array_column(CostaRicaTerritories::all(), 'canton_code')));
    }

    public function test_every_canton_of_the_official_catalogue_has_exactly_one_zone(): void
    {
        $codes = $this->cantonCodes();
        $this->assertCount(84, $codes);
        $this->assertSame(count($codes), DeliveryZoneCanton::count());
        foreach ($codes as $code) {
            $this->assertNotNull(DeliveryZoneCanton::find($code), 'Cantón sin zona: '.$code);
        }
    }

    public function test_the_approved_canton_lists_are_seeded_exactly(): void
    {
        $inZone = fn (DeliveryZone $zone) => DeliveryZoneCanton::where('zone', $zone)->orderBy('canton_code')->pluck('canton_code')->all();
        $gam = ['101', '102', '103', '106', '107', '108', '109', '110', '111', '113', '114', '115', '118',
            '201', '205', '208', '301', '302', '303', '306', '307', '308',
            '401', '402', '403', '404', '405', '406', '407', '408', '409'];
        sort($gam);
        $this->assertSame($gam, $inZone(DeliveryZone::Gam));
        $this->assertCount(31, $gam);
        $this->assertSame(['501', '504', '506', '510'], $inZone(DeliveryZone::GuanacasteNorte));
        $this->assertCount(49, $inZone(DeliveryZone::Rest));
    }

    public function test_the_approved_rates_are_seeded_for_the_active_version(): void
    {
        $set = DeliveryRateSet::where('version', DeliveryZonesSeeder::VERSION)->sole();
        $rate = fn (DeliveryZone $zone) => DeliveryRate::where('delivery_rate_set_id', $set->id)->where('zone', $zone)->sole();
        $this->assertSame([350000, 9000000, 'CRC'], [$rate(DeliveryZone::Gam)->flat_minor, $rate(DeliveryZone::Gam)->free_from_minor, $rate(DeliveryZone::Gam)->currency]);
        $this->assertSame([0, null, 'CRC'], [$rate(DeliveryZone::GuanacasteNorte)->flat_minor, $rate(DeliveryZone::GuanacasteNorte)->free_from_minor, $rate(DeliveryZone::GuanacasteNorte)->currency]);
        $this->assertSame([450000, 11000000, 'CRC'], [$rate(DeliveryZone::Rest)->flat_minor, $rate(DeliveryZone::Rest)->free_from_minor, $rate(DeliveryZone::Rest)->currency]);
        $this->assertSame(3, DeliveryRate::count());
    }

    public function test_seeding_twice_changes_nothing(): void
    {
        $this->seed(DeliveryZonesSeeder::class);
        $this->assertSame(1, DeliveryRateSet::count());
        $this->assertSame(3, DeliveryRate::count());
        $this->assertSame(84, DeliveryZoneCanton::count());
    }

    public function test_zone_labels_are_public_facing_spanish(): void
    {
        $this->assertSame('Gran Área Metropolitana', DeliveryZone::Gam->label());
        $this->assertSame('Guanacaste norte', DeliveryZone::GuanacasteNorte->label());
        $this->assertSame('Resto del país', DeliveryZone::Rest->label());
    }
}
