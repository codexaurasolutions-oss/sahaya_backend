<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;


class ProductColor extends Model
{
    use HasFactory;

    public function prodcutVariantDetails(){
        return $this->hasMany(ProductVariant::class, 'product_id', 'product_id');
    }

    public function colorDetails(){
        return $this->hasOne(Color::class, 'id', 'color_id');
    }

    // public function getVideoAttribute($value) {
    //     if (!empty($value) && Storage::disk('public')->exists(Config('constants.PRODUCT_VIDEO_ROOT_PATH') . $value)) {
    //         $file= "https://" . env("CDN_HOSTNAME") . "/" . $value . "/playlist.m3u8";

    //        // $file = Storage::disk('public')->url(Config('constants.PRODUCT_VIDEO_ROOT_PATH').$value);
    //     }
    //     else{
    //         $file= "https://" . env("CDN_HOSTNAME") . "/" . $value . "/playlist.m3u8";
    //        // $file = Storage::disk('public')->url(Config('constants.PRODUCT_VIDEO_ROOT_PATH').'noimage.png');
    //     }
    //     return $file;
    // }


    // public function getVideoThumbnailAttribute($value) {
    //     if (!empty($value) && Storage::disk('public')->exists(Config('constants.PRODUCT_VIDEO_THUMBNAIL_ROOT_PATH') . $value)) {
    //        // $file = Storage::disk('public')->url(Config('constants.PRODUCT_VIDEO_THUMBNAIL_ROOT_PATH').$value);
    //        $file =  "https://" . env("CDN_HOSTNAME") . "/"  . $value . "thumbnail.jpg";
    //     }
    //     else{
    //         $file =  "https://" . env("CDN_HOSTNAME") . "/"  . $value . "thumbnail.jpg";
    //       //  $file = Storage::disk('public')->url(Config('constants.PRODUCT_VIDEO_THUMBNAIL_ROOT_PATH').'noimage.png');
    //     }
    //     return $file;
    // }


    // public function getVideoAttribute($value) {
    //     if (!empty($value) && Storage::disk('public')->exists(Config('constants.PRODUCT_VIDEO_ROOT_PATH') . $value)) {
    //         return Storage::disk('public')->url(Config('constants.PRODUCT_VIDEO_ROOT_PATH') . $value);
    //     }
        
    //     return $value;
    // }
    // public function getVideoThumbnailAttribute($value) {
    //     if (!empty($value) && Storage::disk('public')->exists(Config('constants.PRODUCT_VIDEO_THUMBNAIL_ROOT_PATH') . $value)) {
    //         $file = Storage::disk('public')->url(Config('constants.PRODUCT_VIDEO_THUMBNAIL_ROOT_PATH').$value);
    //     }
    //     else{
    //         $file = Storage::disk('public')->url(Config('constants.PRODUCT_VIDEO_THUMBNAIL_ROOT_PATH').'noimage.png');
    //     }
    //     return $file;
    // }
}
