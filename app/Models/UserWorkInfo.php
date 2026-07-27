<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
class UserWorkInfo extends Model
{
    use HasFactory;

    protected $table = 'user_work_infos';

    protected $fillable = [
        'user_id',
        'primary_role',
        'skills',
        'languages_spoken',
        'total_experience',
        'education',
        'additional_info',
        'voice_note',
        'emergency_contact_number',
        'emergency_contact_name',
        'working_days',
        'stay_type',
        'pay_frequency',
        'salary_closing_date',
        'salary',
        'joining_date',
        'preferred_work_location',
        'embedding',
    ];

    protected $casts = [
        'skills' => 'array', // automatically convert to/from JSON
        'languages_spoken' => 'array',
        'working_days' => 'array',
        'primary_role' => 'array',
    ];

    // Hide embedding from JSON responses (1536 floats — too large for API output)
    protected $hidden = ['embedding'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

     public function getVoiceNoteAttribute($value)
    {
        if (!$value) {
            return null;
        }
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }
        if (Storage::disk('public')->exists($value)) {
            return Storage::disk('public')->url($value);
        }
        if (file_exists(public_path($value))) {
            return env('APP_URL') . '/public/' . $value;
        }

        return null;
    }
}
