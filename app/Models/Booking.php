<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $table = 'bookings';

    protected $fillable = [
        'order_id',
        'customer_id',
        'vendor_id',
        'schedule_time',
        'service_id',
        'promo_code_id',
        'amount',
        'tax',
        'platform_fee',
        'status',
        'reschedule_time',
        'rejection_reason',
        'is_paid_key',
        'payment_id',
        'note',
    ];

    // Automatically cast JSON/csv fields if needed
    protected $casts = [
        'reschedule_time' => 'datetime',
                'schedule_time' => 'array', // You can decode CSV manually or store as JSON

    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * Get the vendor that owns the booking.
     */
    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    /**
     * Get the service that owns the booking.
     */
    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }


     public function getInvoicePdfUrlAttribute()
    {
        return $this->generateInvoicePdfUrl();
    }

    private function generateInvoicePdfUrl()
    {
        $filename = 'booking-invoice-' . ($this->order_id ?? $this->id) . '.pdf';
        $filePath = 'invoices/booking/' . $this->customer_id . '/' . $filename;
        
        if (file_exists(public_path($filePath))) {
            return url($filePath);
        }
        return null;
    }
}
