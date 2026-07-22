<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Traits\ImageUpload;


class SubServiceController extends Controller
{
    use ImageUpload;
    
    // List all sub services
    public function index()
    {
            $userId = Auth::guard('api')->user()->id;
        $subServices = SubService::with(['user', 'category'])->where('user_id',$userId)->get();

        return response()->json([
            'status' => 'success',
                        'message' => 'Sub services retrieved successfully',
            'data' => $subServices
        ]);
    }

    // Show single sub service
    public function show($id)
    {
        $subService = SubService::with(['user', 'category'])->find($id);

        if (!$subService) {
            return response()->json(['status' => 'error', 'message' => 'Not found'], 404);
        }

        return response()->json(['status' => 'success',           
         'message' => 'Sub service retrieved successfully',
          'data' => $subService]);
    }

    // Add new sub service
   public function store(Request $request)
{
    $user = Auth::guard('api')->user();
    $request->validate([
        'name' => 'required|string|max:255',
        'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    ]);

    $serviceData = [
        'user_id' => $user->id,              // Always from logged in user
        'category_id' => $user->service_category,
        'name' => $request->name,
    ];

    // Image Upload
    if ($request->hasFile('image')) {
        // $image = $request->file('image');
        $directory = 'uploads/services';

        // if (!file_exists(public_path($directory))) {
        //     mkdir(public_path($directory), 0755, true);
        // }

        // $fileName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
        // $image->move(public_path($directory), $fileName);
        $path = $this->uploadCloudary($request,"image",$directory);
        $serviceData['image'] = $path;
    }

    $subService = SubService::create($serviceData);

    return response()->json(['status' => 'true',            'message' => 'Sub service created successfully',
 'data' => $subService]);
}

// Update sub service
public function update(Request $request, $id)
{
    $user = Auth::guard('api')->user();
    $subService = SubService::find($id);

    if (!$subService) {
        return response()->json(['status' => 'error', 'message' => 'Not found'], 404);
    }

    $request->validate([
        'name' => 'sometimes|string|max:255',
        'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    ]);

    $serviceData = [
        'user_id' => $user->id,  // Always from logged in user
    ];

        $serviceData['category_id'] = $user->service_category;
        if ($request->has('name')) {
        $serviceData['name'] = $request->name;
    }

    // Image Upload
    if ($request->hasFile('image')) {
        // $image = $request->file('image');
        $directory = 'uploads/services';

        // if (!file_exists(public_path($directory))) {
        //     mkdir(public_path($directory), 0755, true);
        // }

        // $fileName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
        // $image->move(public_path($directory), $fileName);
        $path = $this->uploadCloudary($request,"image",$directory);
        $serviceData['image'] = $path;
    }

    $subService->update($serviceData);

    return response()->json(['status' => 'success',            'message' => 'Sub service updated successfully',
 'data' => $subService]);
}

    // Delete sub service
    public function destroy($id)
    {
        $subService = SubService::find($id);

        if (!$subService) {
            return response()->json(['status' => 'error', 'message' => 'Not found'], 404);
        }

        $subService->delete();

        return response()->json(['status' => 'success', 'message' => 'Deleted successfully']);
    }
}
