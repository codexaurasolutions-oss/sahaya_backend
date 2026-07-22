<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        "base/uploder",
        "mangopay/payout-success",
        "mangopay/payout-failed",
        "mangopay/payout-refund",
        "mangopay/kyc-failed",
        "mangopay/kyc-success",
        "get-stripe-callback",
        "paytr/callback-ptr",
        "initiatePayment"
    ];
}
