<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Storage;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

trait ImageUpload {

    public function upload(Request $request, $fieldname = 'image', $directory = 'images',$deleteImageFollder = '') {
        if( $request->hasFile( $fieldname ) ) {
            if ($request->file($fieldname)->isValid()) {
                $extension              = $request->file($fieldname)->getClientOriginalExtension();
                $fileName               = time() . '-image.' . $extension;
                $folderName             = strtoupper(date('M') . date('Y')) . "/";
                $folderPath             = $directory .$folderName;
                if($deleteImageFollder != ''){
                    $deleteFolderPath       = $directory.$deleteImageFollder;
                    if(Storage::disk('public')->exists($deleteFolderPath)) {
                        Storage::disk('public')->delete($deleteFolderPath);
                        // Cloudinary::destroy($deleteFolderPath);
                    }
                }

                $upload = Cloudinary::upload(
                    $file->getRealPath(),
                    [
                        'folder' => $folderPath,
                        'resource_type' => 'auto',
                    ]
                );
                $publicId = $upload->getPublicId();
                $imageUrl = Cloudinary::getUrl($publicId);
                return $imageUrl;
                // if (Storage::disk('public')->putFileAs($folderPath,$request->file($fieldname),$fileName, 'public')) {
                //     return $folderName . $fileName;
                // }
            }
        }else{
            if($deleteImageFollder != ''){
                return $deleteImageFollder; 
            }
        }
        return null;
    }
    public function floorupload($imagefile, $fieldname = 'image', $directory = 'images',$deleteImageFollder = '') {
        if ($imagefile->isValid()) {
           
            $extension = $imagefile->getClientOriginalExtension();
            $fileName = rand() . '-image.' . $extension;
            $folderName = strtoupper(date('M') . date('Y')) . "/";
            $folderPath = $directory . $folderName;

            if ($deleteImageFollder != '') {
                $deleteFolderPath = $directory . $deleteImageFollder;
                if (Storage::disk('public')->exists($deleteFolderPath)) {
                    Storage::disk('public')->deleteDirectory($deleteFolderPath);
                }
            }

            if (Storage::disk('public')->putFileAs($folderPath, $imagefile, $fileName, 'public')) {
                return $folderName . $fileName;
            }  
        }
        return null;
    }

    public function maltipalUploadFiles($file = '', $directory = '') {
        if ($file != '') {
            $extension = $file->getClientOriginalExtension();  
            $fileName = time() . rand(10, 100) . '-image.' . $extension;
            $folderName = strtoupper(date('M') . date('Y')) . "/";  
            $folderPath = $directory . $folderName;
            if (Storage::disk('public')->putFileAs($folderPath, $file, $fileName)) {
                return $folderName . $fileName;  
            }
        }
    
        return null; 
    }

    public function uploadCloudary($request, $fieldname = 'image', $directory = 'images',$deleteImageFollder = '') {
        if( $request->hasFile( $fieldname ) ) {
            // dd($request->file($fieldname)->isValid());
            if ($request->file($fieldname)->isValid()) {
                $extension              = $request->file($fieldname)->getClientOriginalExtension();
                $fileName               = time() . '-image.' . $extension;
                $folderName             = strtoupper(date('M') . date('Y')) . "/";
                $folderPath             = $directory .$folderName;
                if($deleteImageFollder != ''){
                    $deleteFolderPath       = $directory.$deleteImageFollder;
                    if(Storage::disk('public')->exists($deleteFolderPath)) {
                        Storage::disk('public')->delete($deleteFolderPath);
                        // Cloudinary::destroy($deleteFolderPath);
                    }
                }
                $file = $request->file($fieldname);
                $upload = Cloudinary::upload(
                    $file->getRealPath(),
                    [
                        'folder' => $folderPath,
                        'resource_type' => 'auto',
                    ]
                );
                
                $publicId = $upload->getPublicId();
                
                $imageUrl = Cloudinary::getUrl($publicId);
                return $imageUrl;
            }
        }else{
            if($deleteImageFollder != ''){
                return $deleteImageFollder; 
            }
        }
        return null;
    }

    public function getImageCloudinary($image) {

        $imageUrl = Cloudinary::getUrl($image, [
            'width' => 300,
            'height' => 300,
            'crop' => 'fill',
            'quality' => 'auto'
        ]);
        return $imageUrl;
    }
    
}