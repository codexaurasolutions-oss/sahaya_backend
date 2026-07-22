<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffAdvance extends Model
{
    use HasFactory;

    protected $table = 'staff_advances';

    protected $fillable = [
        'employer_id',
        'staff_id',
        'amount',
        'remaining_balance',
        'deduction_type',
        'installment_amount',
        'num_installments',
        'status',
        'remarks',
        'given_date',
    ];

    protected $casts = [
        'amount'             => 'decimal:2',
        'remaining_balance'  => 'decimal:2',
        'installment_amount' => 'decimal:2',
        'num_installments'   => 'integer',
        'given_date'         => 'date',
    ];

    public function employer()
    {
        return $this->belongsTo(User::class, 'employer_id');
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function transactions()
    {
        return $this->hasMany(AdvanceTransaction::class, 'advance_id')->orderBy('created_at', 'desc');
    }
}
