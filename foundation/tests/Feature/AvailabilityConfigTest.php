<?php

namespace Tests\Feature;

use App\Availability\AvailabilityConfig;
use App\Availability\InvalidAvailabilityConfiguration;
use App\Providers\AppServiceProvider;
use Tests\TestCase;

class AvailabilityConfigTest extends TestCase
{
    public function test_valid_minutes_are_accepted_from_env_strings_and_integers(): void
    {
        AvailabilityConfig::assertValid('30', 60);
        config(['commerce.availability.ttl_minutes' => ' 45 ', 'commerce.availability.hold_minutes' => '90']);
        $this->assertSame(45, AvailabilityConfig::ttlMinutes());
        $this->assertSame(90, AvailabilityConfig::holdMinutes());
    }

    public function test_missing_or_invalid_ttl_is_rejected(): void
    {
        foreach ([null, '', '0', '-5', '1.5', 'abc', 0, -1, false, []] as $value) {
            try {
                AvailabilityConfig::assertValid($value, 60);
                $this->fail('Accepted invalid TTL: '.var_export($value, true));
            } catch (InvalidAvailabilityConfiguration $exception) {
                $this->assertStringContainsString('COMMERCE_AVAILABILITY_TTL_MINUTES', $exception->getMessage());
            }
        }
    }

    public function test_invalid_hold_minutes_are_rejected(): void
    {
        $this->expectException(InvalidAvailabilityConfiguration::class);
        $this->expectExceptionMessage('COMMERCE_CART_HOLD_MINUTES');
        AvailabilityConfig::assertValid('30', '0');
    }

    public function test_application_boot_fails_without_ttl(): void
    {
        config(['commerce.availability.ttl_minutes' => null]);
        $this->expectException(InvalidAvailabilityConfiguration::class);
        (new AppServiceProvider($this->app))->boot();
    }

    public function test_only_composer_package_discovery_skips_validation(): void
    {
        $this->assertFalse(AvailabilityConfig::mustValidate(true, ['artisan', 'package:discover']));
        foreach ([['artisan', 'serve'], ['artisan', 'migrate'], ['artisan', 'test'], ['artisan', 'key:generate'], ['artisan']] as $argv) {
            $this->assertTrue(AvailabilityConfig::mustValidate(true, $argv));
        }
        $this->assertTrue(AvailabilityConfig::mustValidate(false, ['index.php', 'package:discover']));
    }
}
