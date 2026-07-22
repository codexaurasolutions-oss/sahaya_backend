<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Service extends Model
{
    protected $fillable = [
        'user_id',
        'name', 
        'description', 
        'category_id', 
        'price', 
        'gender', 
        'duration', 
        'peak_hours',
        'service_id',
        'image',
        'addons',
        'available_schedule',
    ];
    
    protected $casts = [
        'price' => 'decimal:2',
        'peak_hours' => 'array',
        'addons' => 'array',
        'available_schedule' => 'array'
    ];

     public function getImageAttribute($value)
    {
        if ($value) {
            return url($value); // full URL
        }
        return null;
    }
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subServices()
{
    return $this->hasMany(SubService::class, 'category_id', 'category_id');
}
    
    /**
     * Calculate price for a specific time
     */
    public function getPriceAtTime($time): float
    {
        $time = date('H:i', strtotime($time));
        $peakHours = $this->peak_hours ?? [];
        
        foreach ($peakHours as $timeRange => $peakPrice) {
            list($start, $end) = explode('-', $timeRange);
            
            if ($time >= $start && $time <= $end) {
                return (float) $peakPrice;
            }
        }
        
        return (float) $this->price;
    }
}