<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Storage;

class MobileIntroScreen extends Model
{
    use HasFactory;
    protected $table ="mobile_intro_screen";

    // public function getImage($value) {
    //     if (!empty($value) && Storage::disk('public')->exists(Config('constants.MOBILE_INTRO_IMAGE_ROOT_PATH') . $value)) {
    //         $file = Storage::disk('public')->url(Config('constants.MOBILE_INTRO_IMAGE_ROOT_PATH').$value);
    //     } 
    //     else {
    //         $value = null;
    //     }
    //     dd($file);
    //     return $file;
    // }

    public function getImageAttribute($value) {
        if (!empty($value) && Storage::disk('public')->exists(Config('constants.MOBILE_INTRO_IMAGE_ROOT_PATH') . $value)) {
            $file = url('image.php?height=350px&image=') . Storage::disk('public')->url(Config('constants.MOBILE_INTRO_IMAGE_ROOT_PATH').$value);
        }
        else{
            $file = url('image.php?height=350px&image=') . Storage::disk('public')->url(Config('constants.MOBILE_INTRO_IMAGE_ROOT_PATH').'noimage.png');
        }
        return $file;
    }

    public function MobileIntroScreenDiscription($value='')
    {
        return $this->hasOne(MobileIntroScreenDescription::class, 'parent_id', 'id');
    }
}
