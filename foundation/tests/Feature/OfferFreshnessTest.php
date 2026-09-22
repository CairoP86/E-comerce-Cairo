<?php

namespace Tests\Feature;

use App\Availability\Availability;
use App\Availability\AvailabilityState;
use App\Availability\OfferFreshness;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class OfferFreshnessTest extends TestCase
{
    private const TTL = 1440; // one day, the lower end of the production window

    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($time, 'UTC');
    }

    private function observed(AvailabilityState $state, string $observedAt): Availability
    {
        return new Availability($state, $state === AvailabilityState::Available ? 1 : 0, $this->at($observedAt), 'manual', 7);
    }

    public function test_a_recent_observation_is_fresh_and_says_when_it_expires(): void
    {
        $f = OfferFreshness::of($this->observed(AvailabilityState::Available, '2026-09-21 08:00'), $this->at('2026-09-21 10:00'), self::TTL);
        $this->assertSame('fresh', $f['level']);
        $this->assertSame('2026-09-22T08:00:00+00:00', $f['expires_at']);
        $this->assertSame(22 * 60, $f['remaining_minutes']);
    }

    public function test_the_last_quarter_of_the_window_is_flagged_as_expiring(): void
    {
        // A one day TTL warns during its last six hours, and not a minute earlier.
        $observation = $this->observed(AvailabilityState::Available, '2026-09-21 00:00');
        $this->assertSame('fresh', OfferFreshness::of($observation, $this->at('2026-09-21 17:59'), self::TTL)['level']);
        $this->assertSame('expiring', OfferFreshness::of($observation, $this->at('2026-09-21 18:00'), self::TTL)['level']);
        $this->assertSame(1, OfferFreshness::of($observation, $this->at('2026-09-21 23:59'), self::TTL)['remaining_minutes']);
    }

    public function test_the_threshold_scales_with_the_configured_ttl(): void
    {
        // The seven day local window warns from its last 42 hours: no fixed hour count is assumed.
        $observation = $this->observed(AvailabilityState::Available, '2026-09-01 00:00');
        $this->assertSame('fresh', OfferFreshness::of($observation, $this->at('2026-09-06 05:59'), 10080)['level']);
        $this->assertSame('expiring', OfferFreshness::of($observation, $this->at('2026-09-06 06:00'), 10080)['level']);
    }

    public function test_an_observation_past_the_ttl_is_expired_with_a_negative_remainder(): void
    {
        $f = OfferFreshness::of($this->observed(AvailabilityState::Stale, '2026-09-19 10:00'), $this->at('2026-09-21 10:00'), self::TTL);
        $this->assertSame('expired', $f['level']);
        $this->assertSame(-24 * 60, $f['remaining_minutes']);
    }

    public function test_a_sold_out_offer_still_has_a_freshness_of_its_own(): void
    {
        // Sold out products stay listed as sold out while the observation is fresh.
        $f = OfferFreshness::of($this->observed(AvailabilityState::Unavailable, '2026-09-21 08:00'), $this->at('2026-09-21 10:00'), self::TTL);
        $this->assertSame('fresh', $f['level']);
        $this->assertSame('unavailable', $f['state']);
    }

    public function test_no_preferred_offer_and_invalid_data_are_told_apart(): void
    {
        $this->assertSame('none', OfferFreshness::of(Availability::unknown(), $this->at('2026-09-21 10:00'), self::TTL)['level']);
        // An inactive offer or one without a confirmed stock has a date but can never show.
        $invalid = new Availability(AvailabilityState::Unknown, null, $this->at('2026-09-21 08:00'), 'manual', 7);
        $f = OfferFreshness::of($invalid, $this->at('2026-09-21 10:00'), self::TTL);
        $this->assertSame('invalid', $f['level']);
        $this->assertNull($f['remaining_minutes']);
    }
}
