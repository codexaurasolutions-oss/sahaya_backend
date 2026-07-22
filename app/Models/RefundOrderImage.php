<?php

namespace App\Models;
use Storage;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefundOrderImage extends Model
{
    use HasFactory;

    protected $table = 'refund_order_images';

    public function refundOrder()
    {
        return $this->belongsTo(RefundOrder::class, 'refund_order_id', 'id');
    }

    public function getImageAttribute($value) {
        if (!empty($value) && Storage::disk('public')->exists(Config('constants.REFUND_IMAGE_ROOT_PATH') . $value)) {
            $file = Storage::disk('public')->url(Config('constants.REFUND_IMAGE_ROOT_PATH').$value);
        } 
        else{
            $file = Storage::disk('public')->url(Config('constants.REFUND_IMAGE_ROOT_PATH').'noimage.png');
        }
        return $file;
    }
    
}
