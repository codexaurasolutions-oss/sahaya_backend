<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LastWorkExperience extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'last_work_experiences';

    protected $fillable = [
        'user_id',
        'role',
        'join_date',
        'end_date',
        'salary',
        'working_hours',
        'house_sold',
        'owner_name',
        'contact_number',
        'state',
        'city',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
