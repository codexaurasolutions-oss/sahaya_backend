<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Storage;

class AboutAyva extends Model
{
    use HasFactory;

    public function getImageAttribute($value) {
//         'image.php?height=350px&image='
        if (!empty($value) && Storage::disk('public')->exists(Config('constants.ABOUT_AYVA_IMAGE_ROOT_PATH') . $value)) {
            $file = url() . Storage::disk('public')->url(Config('constants.ABOUT_AYVA_IMAGE_ROOT_PATH').$value);
        }
        else{
            $file = url() . Storage::disk('public')->url(Config('constants.ABOUT_AYVA_IMAGE_ROOT_PATH').'noimage.png');
        }
        return $file;
    }

    public function abountayvaDescription()
    {
        return $this->hasOne(AboutAyvaDescription::class, 'parent_id', 'id');
    }
}
