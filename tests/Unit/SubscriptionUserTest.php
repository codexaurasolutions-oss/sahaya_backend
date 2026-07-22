<?php

namespace Tests\Unit;

use App\Models\Subscription;
use App\Models\SubscriptionUser;
use Tests\TestCase;

class SubscriptionUserTest extends TestCase
{
    public function test_active_paid_plan_grants_paid_access_without_payment_metadata(): void
    {
        $subscriptionUser = new SubscriptionUser([
            'status' => 'active',
            'end_date' => now()->addDay(),
            'amount' => 0,
            'payment_status' => null,
        ]);
        $subscriptionUser->setRelation('subscription', new Subscription(['price' => 1500]));

        $this->assertTrue($subscriptionUser->hasActivePaidAccess());
    }

    public function test_active_free_plan_does_not_grant_paid_access(): void
    {
        $subscriptionUser = new SubscriptionUser([
            'status' => 'active',
            'end_date' => now()->addDay(),
            'amount' => 0,
            'payment_status' => 'free',
        ]);
        $subscriptionUser->setRelation('subscription', new Subscription(['price' => 0]));

        $this->assertFalse($subscriptionUser->hasActivePaidAccess());
    }

    public function test_expired_paid_plan_does_not_grant_paid_access(): void
    {
        $subscriptionUser = new SubscriptionUser([
            'status' => 'active',
            'end_date' => now()->subDay(),
            'amount' => 1500,
            'payment_status' => 'paid',
        ]);
        $subscriptionUser->setRelation('subscription', new Subscription(['price' => 1500]));

        $this->assertFalse($subscriptionUser->hasActivePaidAccess());
    }
}
