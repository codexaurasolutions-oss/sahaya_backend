<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Salary extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id',
        'houseowner_id',
        'basic_salary',
        'performative_allowance',
        'over_time_allowance',
        'tax',
        'advance_payment',
        'net_salary',
        'payment_mode',
        'payment_receipt',
        'payment_date',
        'status',
    ];

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function houseowner()
    {
        return $this->belongsTo(User::class, 'houseowner_id');
    }

    public function payouts()
    {
        return $this->hasMany(SalaryPayout::class);
    }
}
