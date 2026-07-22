<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromoCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'promo_code',
        'type',
        'amount',
        'start_on',
        'expired_on',
        'description',
        'isActive',
        'user_id',
        'extra_text',
        'is_highlighted',
    ];

    protected $casts = [
        'start_on' => 'date',
        'expired_on' => 'date',
        'isActive' => 'boolean'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('isActive', true)
                    ->where('start_on', '<=', now())
                    ->where('expired_on', '>=', now());
    }

    public function calculateDiscount($totalAmount)
    {
        if ($this->type === 'percentage') {
            // For percentage, ensure amount is between 0-100
            $percentage = min(max(floatval($this->amount), 0), 100);
            return ($totalAmount * $percentage) / 100;
        } else {
            // For flat amount, ensure it doesn't exceed total amount
            return min(floatval($this->amount), $totalAmount);
        }
    }
}