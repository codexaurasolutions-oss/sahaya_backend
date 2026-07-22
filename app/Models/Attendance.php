<?php
// app/Models/Attendance.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendance';

    protected $fillable = [
        'staff_id',
        'date',
        'status',
        'check_in_time',
        'late_minutes',
        'leave_id',
        'description',
        'processed_by'
    ];

    protected $casts = [
        'date' => 'date',
        'check_in_time' => 'datetime:H:i:s',
        'late_minutes' => 'integer'
    ];

    /**
     * Relationship with Staff
     */
    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /**
     * Relationship with Leave
     */
    public function leave()
    {
        return $this->belongsTo(LeaveType::class, 'leave_id');
    }

    /**
     * Relationship with User who processed the attendance
     */
    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Scope for present attendance
     */
    public function scopePresent($query)
    {
        return $query->where('status', 'present');
    }

    /**
     * Scope for absent attendance
     */
    public function scopeAbsent($query)
    {
        return $query->where('status', 'absent');
    }

    /**
     * Scope for late attendance
     */
    public function scopeLate($query)
    {
        return $query->where('status', 'late');
    }

    /**
     * Check if attendance is late
     */
    public function isLate()
    {
        return $this->status === 'late';
    }

    /**
     * Check if attendance is absent
     */
    public function isAbsent()
    {
        return $this->status === 'absent';
    }

    /**
     * Check if attendance is present
     */
    public function isPresent()
    {
        return $this->status === 'present';
    }
}