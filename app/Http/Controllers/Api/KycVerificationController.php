<?php

namespace App\Http\Controllers\Api;

use App\Models\KycVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ImageUpload;

class KycVerificationController extends Controller
{
    use ImageUpload;

    public function updateOrCreateKyc(Request $request)
    {
        $request->validate([
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'police_verification' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
            'aadhaar_front' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'aadhaar_back' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        try {
            DB::beginTransaction();

            $user_id = Auth::guard('api')->user()->id;
            $userData = User::find($user_id);
            $data = ['user_id' => $user_id];

            // Handle photo upload
            if ($request->hasFile('photo')) {
                $data['photo_path'] = $this->handleFileUpload($request, 'uploads/kyc/photos', $user_id, 'photo');
            }

            // Handle police verification upload
            if ($request->hasFile('police_verification')) {
                $data['police_verification_path'] = $this->handleFileUpload($request, 'uploads/kyc/police_verifications', $user_id, 'police_verification');
            }

            // Handle Aadhaar front upload
            if ($request->hasFile('aadhaar_front')) {
                $data['aadhaar_front_path'] = $this->handleFileUpload($request, 'uploads/kyc/aadhaar', $user_id, 'aadhaar_front');
            }

            // Handle Aadhaar back upload
            if ($request->hasFile('aadhaar_back')) {
                $data['aadhaar_back_path'] = $this->handleFileUpload($request, 'uploads/kyc/aadhaar', $user_id, 'aadhaar_back');
            }

            // Update or create KYC verification record
            $kycVerification = KycVerification::updateOrCreate(
                ['user_id' => $user_id],
                $data
            );

            DB::commit();
            $userData->update(['step' => 3]);

            // Notify admin about new KYC submission
            try {
                \App\Services\NotificationService::send(
                    1,
                    'New KYC Submission',
                    ($userData->first_name ?? $userData->name ?? 'A staff member') . ' has submitted KYC documents for verification.',
                    'kyc_submitted',
                    ['skip_push' => true, 'skip_whatsapp' => true, 'skip_sms' => true]
                );
            } catch (\Exception $e) {
                \Log::warning('KYC admin notification failed: ' . $e->getMessage());
            }

            return response()->json([
                'status' => true,
                'userData' => $userData,
                'message' => 'KYC documents uploaded successfully',
                'data' => $kycVerification,
                
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => false,
                'userData' => $userData,
                'message' => 'Failed to upload KYC documents: ' . $e->getMessage()
            ], 500);
        }
    }

    private function handleFileUpload($file, $directory, $user_id, $type)
    {
        // Create directory if it doesn't exist
        // if (!file_exists(public_path($directory))) {
        //     mkdir(public_path($directory), 0755, true);
        // }

        // // Generate unique file name
        // $extension = $file->getClientOriginalExtension();
        // $fileName = $user_id . '_' . $type . '_' . time() . '_' . uniqid() . '.' . $extension;
        
        // // Move file to directory
        // $file->move(public_path($directory), $fileName);
        
        // $path = $directory . '/' . $fileName;

        // // Delete old file if exists (for update scenario)
        // $this->deleteOldFile($user_id, $type, $path);
        $path = $this->uploadCloudary($file,$type,$directory);
        // return $path;
        return $path;
    }

    private function deleteOldFile($user_id, $type, $newPath)
    {
        $kyc = KycVerification::where('user_id', $user_id)->first();
        
        if (!$kyc) return;

        $oldPath = null;
        $fieldName = '';

        switch ($type) {
            case 'photo':
                $oldPath = $kyc->photo_path;
                $fieldName = 'photo_path';
                break;
            case 'police_verification':
                $oldPath = $kyc->police_verification_path;
                $fieldName = 'police_verification_path';
                break;
            case 'aadhaar_front':
                $oldPath = $kyc->aadhaar_front_path;
                $fieldName = 'aadhaar_front_path';
                break;
            case 'aadhaar_back':
                $oldPath = $kyc->aadhaar_back_path;
                $fieldName = 'aadhaar_back_path';
                break;
        }

        // Delete old file if it exists and is different from new file
        if ($oldPath && $oldPath !== $newPath && file_exists(public_path($oldPath))) {
            unlink(public_path($oldPath));
        }
    }

    // Additional function to get KYC status
    public function getKycStatus(Request $request, $user_id)
    {
        try {
            $kyc = KycVerification::where('user_id', $user_id)->first();

            if (!$kyc) {
                return response()->json([
                    'status' => false,
                    'message' => 'KYC verification not found for this user',
                    'data' => null
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'KYC status retrieved successfully',
                'data' => $kyc
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve KYC status: ' . $e->getMessage()
            ], 500);
        }
    }

    // Admin: Get all KYC list
    public function getAdminKycList(Request $request)
    {
        try {
            $query = KycVerification::with('user:id,name,first_name,last_name,email,phone_number,role_id');

            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            $kycs = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $kycs
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch KYC list',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Admin: Approve or Reject KYC
    public function updateKycStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
        ]);

        try {
            DB::beginTransaction();

            $kyc = KycVerification::find($id);

            if (!$kyc) {
                return response()->json([
                    'success' => false,
                    'message' => 'KYC record not found',
                ], 404);
            }

            $kyc->status = $request->status;
            $kyc->save();

            // Update user aadhar verify status and notify staff
            if ($request->status === 'approved') {
                $user = User::find($kyc->user_id);
                if ($user) {
                    $user->aadhar__verify = 1;
                    $user->save();
                }
                \App\Services\NotificationService::send(
                    $kyc->user_id,
                    'KYC Approved',
                    'Your KYC verification has been approved.',
                    'kyc_approved'
                );
            } else {
                $user = User::find($kyc->user_id);
                if ($user) {
                    $user->aadhar__verify = 0;
                    $user->save();
                }
                \App\Services\NotificationService::send(
                    $kyc->user_id,
                    'KYC Rejected',
                    'Your KYC verification has been rejected. Please re-upload your documents.',
                    'kyc_rejected'
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'KYC status updated to ' . $request->status,
                'data' => $kyc
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update KYC status: ' . $e->getMessage()
            ], 500);
        }
    }
}