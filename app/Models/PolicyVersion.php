<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PolicyVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'version',
        'title',
        'content_hash',
        'is_current',
    ];

    protected $casts = [
        'is_current' => 'boolean',
    ];

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }
}
