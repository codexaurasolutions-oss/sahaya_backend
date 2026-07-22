<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Storage;

class Cms extends Model
{
    use HasFactory;

    public function cmsDescription()
    {
        return $this->hasOne(CmsDescription::class, 'parent_id', 'id');
    }

    public function getImageAttribute($value) {
        ///'image.php?height=350px&image='
        if (!empty($value) && Storage::disk('public')->exists(Config('constants.CMS_PAGE_IMAGE_ROOT_PATH') . $value)) {
            $file =  Storage::disk('public')->url(Config('constants.CMS_PAGE_IMAGE_ROOT_PATH').$value);
        }
        else{
            $file =  Storage::disk('public')->url(Config('constants.CMS_PAGE_IMAGE_ROOT_PATH').'noimage.png');
        }
        return $file;
    }
}
