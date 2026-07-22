<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Job extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'compensation',
        'expected_compensation',
        'compensation_type',
        'street_address',
        'city',
        'state',
        'zip_code',
        'area_locality',
        'google_location',
        'latitude',
        'longitude',
        'commitment_type',
        'stay_type',
        'preferred_hours',
        'preferred_days',
        'status',
        'childcare_experience',
        'cooking_required',
        'driving_license_required',
        'first_aid_certified',
        'pet_care_required',
        'additional_requirements',
        'required_skills',
        'created_by'
    ];

    protected $casts = [
        'childcare_experience' => 'boolean',
        'cooking_required' => 'boolean',
        'driving_license_required' => 'boolean',
        'first_aid_certified' => 'boolean',
        'pet_care_required' => 'boolean',
        'compensation' => 'decimal:2',
        'expected_compensation' => 'decimal:2',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function applications()
    {
        return $this->hasMany(JobApplication::class);
    }

    public function getApplicantsCountAttribute()
    {
        return $this->applications()->count();
    }

    // Scope for open jobs
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }
}