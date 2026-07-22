<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryPayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'salary_id',
        'staff_id',
        'houseowner_id',
        'bank_account_id',
        'requested_by',
        'contact_id',
        'fund_account_id',
        'payout_id',
        'reference_id',
        'amount',
        'currency',
        'mode',
        'purpose',
        'status',
        'idempotency_key',
        'narration',
        'queue_if_low_balance',
        'utr',
        'error_message',
        'request_payload',
        'response_payload',
        'requested_at',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'queue_if_low_balance' => 'boolean',
        'request_payload' => 'array',
        'response_payload' => 'array',
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function salary()
    {
        return $this->belongsTo(Salary::class);
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function houseowner()
    {
        return $this->belongsTo(User::class, 'houseowner_id');
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }
}
