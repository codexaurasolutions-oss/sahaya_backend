<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Banner;
use Illuminate\Support\Facades\Auth;
use App\Models\Cms;
use App\Traits\ImageUpload;

class BannerController extends Controller
{
    use ImageUpload;
public function storeOrUpdate(Request $request)
{
    $userId = Auth::guard('api')->user()->id;

    $updateData = [
        'user_id'    => $userId,
        'type'       => $request->type,
        'extensions' => $request->extensions,
        'position'   => $request->position,
    ];

    if ($request->hasFile('image')) {
        $directory = 'uploads/banner';

        // $image = $request->file('image');
        
        // if (!file_exists(public_path($directory))) {
        //     mkdir(public_path($directory), 0755, true);
        // }

        // $fileName = 'banner_' . time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
        // $image->move(public_path($directory), $fileName);

        $path = $this->uploadCloudary($request,"image",$directory);
        $updateData['image'] = $path;
    } else {
        $updateData['image'] = $request->image;
    }
    if ($request->banner_id) {
        $banner = Banner::find($request->banner_id);

        if ($banner) {
            $banner->update($updateData);

            return response()->json([
                'status'  => true,
                'message' => 'Banner updated successfully',
                'data'    => $banner->fresh()
            ]);
        }

        return response()->json([
            'status'  => false,
            'message' => 'Banner not found'
        ], 404);
    }
    $banner = Banner::create($updateData);

    return response()->json([
        'status'  => true,
        'message' => 'Banner created successfully',
        'data'    => $banner
    ]);
}

public function delete(Request $request)
{
    $id = $request->id;

    $banner = Banner::find($id);

    if (!$banner) {
        return response()->json([
            'status'  => false,
            'message' => 'Banner not found',
        ], 404);
    }

    $banner->delete();

    return response()->json([
        'status'  => true,
        'message' => 'Banner deleted successfully',
    ], 200);
}


    // Get Banner
    public function index()
    {
        $banner = Banner::with('user')->get();
        if ($banner) {
            return response()->json([
                'status'  => true,
                'message' => 'Banner fetched successfully',
                'data'    => $banner
            ]);
        }
        return response()->json([
            'status'  => false,
            'message' => 'No banner found',
            'data'    => null
        ]);
    }

    public function updateBody(Request $request)
{
    $request->validate([
        'id'   => 'required|integer|exists:cms,id',
        'body' => 'required|string',
    ]);

    $cms = Cms::find($request->id);

    if (!$cms) {
        return response()->json([
            'status'  => false,
            'message' => 'CMS record not found',
        ], 404);
    }

    $cms->body = $request->body;
    $cms->save();

    return response()->json([
        'status'  => true,
        'message' => 'CMS body updated successfully',
        'data'    => $cms
    ], 200);
}


}
