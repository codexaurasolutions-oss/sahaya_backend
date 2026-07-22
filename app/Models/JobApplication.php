<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_id',
        'user_id',
        'application_status',
        'cover_letter',
        'expected_salary',
        'available_from',
        'is_advance',
    ];

    protected $casts = [
        'expected_salary' => 'decimal:2',
        'available_from' => 'date'
    ];

    public function job()
    {
        return $this->belongsTo(Job::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scope for pending applications
    public function scopePending($query)
    {
        return $query->where('application_status', 'pending');
    }

    // In App\Models\JobApplication
public function payments()
{
    return $this->hasMany(Payment::class, 'staff_id', 'user_id');
}

}