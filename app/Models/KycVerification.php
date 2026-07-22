<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KycVerification extends Model
{
    use HasFactory;

    protected $table = 'kyc_verifications';

    protected $fillable = [
        'user_id',
        'photo_path',
        'police_verification_path',
        'aadhaar_front_path',
        'aadhaar_back_path',
        'status',
        'verified_by',
        'remarks',
        'verified_at'
    ];

    protected $casts = [
        'verified_at' => 'datetime'
    ];

    // Relationship with user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

     public function getPhotoPathAttribute($value)
    {
        return $this->getFullUrl($value);
    }

    public function getPoliceVerificationPathAttribute($value)
    {
        return $this->getFullUrl($value);
    }

    public function getAadhaarFrontPathAttribute($value)
    {
        return $this->getFullUrl($value);
    }

    public function getAadhaarBackPathAttribute($value)
    {
        return $this->getFullUrl($value);
    }
    protected function getFullUrl($value)
    {
        if ($value) {
            // return env('APP_URL') . '/public/' . $value;
            return $value;
        }

        return null;
    }

    // Relationship with verifier
    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}