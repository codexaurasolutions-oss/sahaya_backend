<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Transaction extends Model
{
    use HasFactory;

    protected $table = 'transactions';

    protected $fillable = [
        'user_id', 'role', 'transaction_id', 'type', 'order_id', 
        'order_number', 'reference_id', 'amount', 'currency',
        'payment_mode', 'payment_status', 'payment_response', 'for_entry',
        'created_by','transaction_id','type','order_number','reference_id','currency','payment_mode','payment_status','payment_response','for_entry'


        
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // Accessor for PDF URL
    public function getInvoicePdfUrlAttribute()
    {
        return $this->generateInvoicePdfUrl();
    }

    private function generateInvoicePdfUrl()
    {
        // Logic to generate or retrieve PDF URL
        $filename = 'invoice-' . $this->order_number . '.pdf';
        if (Storage::disk('public')->exists('invoices/' . $this->user_id . '/' . $filename)) {
            return Storage::disk('public')->url('invoices/' . $this->user_id . '/' . $filename);
        }
        return null;
    }
}