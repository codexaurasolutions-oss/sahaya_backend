<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAddress extends Model
{
    use HasFactory;

    protected $table = 'user_addresses';

    protected $fillable = [
        'user_id',
        'name',
        'street',
        'city',
        'state',
        'pincode',
        'area_locality',
        'google_location',
        'latitude',
        'longitude',
        'is_primary'
    ];

    protected $casts = [
        'is_primary' => 'boolean'
    ];

    protected $appends = [
        'lat',
        'long',
    ];

    public function getLatAttribute()
    {
        return $this->attributes['latitude'] ?? null;
    }

    public function getLongAttribute()
    {
        return $this->attributes['longitude'] ?? null;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function householdInformation()
    {
        return $this->hasOne(UserHouseholdInformation::class, 'address_id');
    }

    public function petDetails()
    {
        return $this->hasMany(UserPetDetail::class, 'address_id');
    }
}
