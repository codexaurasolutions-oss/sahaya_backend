<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionUser extends Model
{
    use SoftDeletes;

    protected $table = 'subscription_users';

    protected $fillable = [
        'user_id',
        'subscription_id',
        'role',
        'transaction_id',
        'type',
        'order_id',
        'order_number',
        'reference_id',
        'amount',
        'base_amount',
        'gst_amount',
        'total_amount',
        'wallet_used',
        'currency',
        'payment_mode',
        'payment_status',
        'payment_response',
        'for_entry',
        'start_date',
        'end_date',
        'status',
        'user_limit',
        'job_user_limit',
        'staff_user_limit',
        'extra_jobs',
        'extra_staff'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'base_amount' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'payment_response' => 'array',
    ];

    /**
     * Get the user that owns the subscription
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subscription plan
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Check if subscription is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && 
               $this->end_date && 
               $this->end_date->isFuture();
    }

    public function hasActivePaidAccess(): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        $planPrice = (float) ($this->subscription?->price ?? 0);
        $paidAmount = (float) ($this->amount ?? 0);
        $paymentStatus = strtolower((string) ($this->payment_status ?? ''));

        return $planPrice > 0
            || $paidAmount > 0
            || in_array($paymentStatus, ['paid', 'completed', 'success', 'successful'], true);
    }

    
}
