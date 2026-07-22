<?php

namespace Tests\Unit;

use App\Services\ReferralPointService;
use PHPUnit\Framework\TestCase;

class ReferralPointServiceTest extends TestCase
{
    public function test_it_redeems_only_complete_credit_units_and_keeps_leftover_points(): void
    {
        $result = (new ReferralPointService())->conversion(27, 10);

        $this->assertSame(2, $result['credits']);
        $this->assertSame(20.0, $result['points_used']);
        $this->assertSame(7.0, $result['points_remaining']);
        $this->assertSame(10.0, $result['points_per_credit']);
    }

    public function test_it_returns_zero_credits_when_points_are_below_the_rate(): void
    {
        $result = (new ReferralPointService())->conversion(9, 10);

        $this->assertSame(0, $result['credits']);
        $this->assertSame(0.0, $result['points_used']);
        $this->assertSame(9.0, $result['points_remaining']);
    }
}
