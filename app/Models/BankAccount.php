<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'bank_name',
        'account_number',
        'ifsc_code',
        'bank_type',
        'user_id',
        'is_set',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeSaving($query)
    {
        return $query->where('bank_type', 'saving');
    }

    public function scopeCurrent($query)
    {
        return $query->where('bank_type', 'current');
    }
}