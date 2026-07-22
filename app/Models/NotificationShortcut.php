<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationShortcut extends Model
{
    use SoftDeletes;

    protected $table = 'notifications_shortcuts';

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'is_all_users',
        'user_ids'
    ];

    protected $casts = [
        'is_all_users' => 'boolean',
        'user_ids' => 'array',
    ];

    /**
     * Get the user that owns the shortcut
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the audience display text
     */
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

    /**
     * Create a notification from this shortcut
     */
    public function createNotification($scheduledTime = null)
    {
        return Notification::create([
            'user_id' => $this->user_id,
            'title' => $this->title,
            'description' => $this->description,
            'scheduled_time' => $scheduledTime ?? now()->addHours(1),
            'is_all_users' => $this->is_all_users,
            'user_ids' => $this->user_ids,
        ]);
    }
}