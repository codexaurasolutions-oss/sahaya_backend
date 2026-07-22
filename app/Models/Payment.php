<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'staff_id',
        'amount',
        'payment_id',
        'order_id',
        'reject_reason',
        'status',
        'payment_mode',
        'full_name',
        'mobile_number',
        'alt_number',
        'address',
        'pin_code',
        'number_of_attendees',
        'booking_date',
        'event_time',
        'catering_needed',
        'chef_needed',
        'photographer_needed',
        'decore_needed',
        'groceries_needed',
        'comment',
        'lat',
        'long',
        'order_status',
        'additional_amount',
        'base_salary',
        'performance_bonus',
        'overtime_pay',
        'tax_deduction',
        'advance_payment',
        'net_salary',
        'salary_period'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'additional_amount' => 'decimal:2',
        'base_salary' => 'decimal:2',
        'performance_bonus' => 'decimal:2',
        'overtime_pay' => 'decimal:2',
        'tax_deduction' => 'decimal:2',
        'advance_payment' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'catering_needed' => 'boolean',
        'chef_needed' => 'boolean',
        'photographer_needed' => 'boolean',
        'decore_needed' => 'boolean',
        'groceries_needed' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

public function jobApplication()
{
    return $this->belongsTo(JobApplication::class, 'staff_id', 'user_id');
}

public function job()
{
    return $this->belongsTo(Job::class);
}
}