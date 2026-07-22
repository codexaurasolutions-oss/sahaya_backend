<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class MailShortcut extends Model
{
    use SoftDeletes;

    protected $table = 'mail_shortcuts';

    protected $fillable = [
        'user_id',
        'subject',
        'body',
        'is_all_users',
        'user_ids'
    ];

    protected $casts = [
        'is_all_users' => 'boolean',
        'user_ids' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getAudienceAttribute(): string
    {
        if ($this->is_all_users) {
            return 'All Users';
        }

        if (!empty($this->user_ids)) {
            $userNames = User::whereIn('id', $this->user_ids)
                ->pluck('name')
                ->toArray();
            return implode(', ', $userNames) ?: 'Specific Users';
        }

        return 'No Audience';
    }
}
