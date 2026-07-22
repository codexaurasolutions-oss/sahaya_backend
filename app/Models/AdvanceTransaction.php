<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdvanceTransaction extends Model
{
    use HasFactory;

    protected $table = 'advance_transactions';

    protected $fillable = [
        'advance_id',
        'staff_id',
        'employer_id',
        'deducted_amount',
        'balance_after',
        'salary_id',
        'note',
    ];

    protected $casts = [
        'deducted_amount' => 'decimal:2',
        'balance_after'   => 'decimal:2',
    ];

    public function advance()
    {
        return $this->belongsTo(StaffAdvance::class, 'advance_id');
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function employer()
    {
        return $this->belongsTo(User::class, 'employer_id');
    }
}
