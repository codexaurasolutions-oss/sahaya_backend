<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Support;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Traits\ImageUpload;

class SupportController extends Controller
{

    use ImageUpload;

    // Save new support request
    public function store(Request $request)
    {
        try {
            $user = Auth::guard('api')->user();

            $validator = Validator::make($request->all(), [
                'title' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors()->first(),
                ], 422);
            }

            $data = $request->only(['title', 'description']);
            $data['user_id'] = $user->id;

            // Image Upload
            if ($request->hasFile('image')) {
                // $image = $request->file('image');
                $directory = 'uploads/supports';

                // if (!file_exists(public_path($directory))) {
                //     mkdir(public_path($directory), 0755, true);
                // }

                // $fileName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                // $image->move(public_path($directory), $fileName);
                $path = $this->uploadCloudary($request,"image",$directory);
                $data['image'] = $path;
            }

            $support = Support::create($data);

            return response()->json([
                'status' => 'success',
                'message' => 'Support request created successfully',
                'data' => $support
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // List all supports for logged in user
    public function index()
    {
        try {
            $user = Auth::guard('api')->user();
            $supports = Support::with(['user', 'replyUser'])
                ->where('user_id', $user->id)
                ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Support list retrieved successfully',
                'data' => $supports
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Reply to a support request (admin/manager can reply)
    public function reply(Request $request, $id)
    {
        try {
            $user = Auth::guard('api')->user();

            $validator = Validator::make($request->all(), [
                'reply' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors()->first(),
                ], 422);
            }

            $support = Support::find($id);

            if (!$support) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Support not found'
                ], 404);
            }

            $support->reply = $request->reply;
            $support->replyed_by = $user->id;
            $support->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Reply added successfully',
                'data' => $support
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
