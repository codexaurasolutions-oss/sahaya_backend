<?php

namespace App\Services;

use App\Models\Setting;

class ReferralPointService
{
    public function pointsPerReferral(): float
    {
        $configured = Setting::where('key', 'points_per_staff_referral')->value('value');
        if ($configured === null) {
            $configured = Setting::where('key', 'credits_per_staff_referral')->value('value');
        }

        return max(1, (float) ($configured ?? 10));
    }

    public function pointsPerCredit(): float
    {
        $configured = Setting::where('key', 'staff_referral_points_per_credit')->value('value');

        return max(1, (float) ($configured ?? 10));
    }

    public function conversion(float $availablePoints, ?float $pointsPerCredit = null): array
    {
        $rate = max(1, $pointsPerCredit ?? $this->pointsPerCredit());
        $credits = max(0, (int) floor(($availablePoints + 0.000001) / $rate));
        $pointsUsed = round($credits * $rate, 2);

        return [
            'credits' => $credits,
            'points_used' => $pointsUsed,
            'points_remaining' => max(0, round($availablePoints - $pointsUsed, 2)),
            'points_per_credit' => $rate,
        ];
    }
}
