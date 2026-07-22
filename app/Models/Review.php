<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'service_id',
        'given_by_id',
        'given_by_type',
        'received_by_id',
        'received_by_type',
        'rating',
        'review',
    ];

    // Relation with Service
    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    // Polymorphic relation for who gave review
    public function givenBy()
    {
        return $this->morphTo(null, 'given_by_type', 'given_by_id');
    }

    // Polymorphic relation for who received review
    public function receivedBy()
    {
        return $this->morphTo(null, 'received_by_type', 'received_by_id');
    }
}
