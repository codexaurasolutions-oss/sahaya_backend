<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Response;
use DB;
use App\Models\LastWorkExperience;
use App\Models\Order;
use App\Models\PortfolioImage;
use App\Models\Service;
use App\Models\SubService;
use App\Models\UserHouseholdInformation;
use App\Models\Wishlist;
use App\Models\Booking;
use App\Models\UserAddress;
use Carbon\Carbon; 
use App\Models\UserWorkInfo;
use App\Models\Category;
use Illuminate\Validation\Rule;
use App\Models\Designation;
use App\Models\Role;
use App\Models\UserRole;
use App\Models\LeaveRequest;
use App\Models\ReferralReward;
use App\Models\ReferralRedemption;
use App\Services\ReferralPointService;
use App\Traits\ImageUpload;
use App\Traits\SmsCountryTrait;
use App\Models\SubscriptionUser;
use App\Models\Subscription;
use App\Models\JobApplication;

class UserController extends Controller
{
    use ImageUpload,SmsCountryTrait;

    private const OTP_VALID_MINUTES = 30;

    public function signUp(Request $request)
    {
        $existingUser = User::where('phone_number', $request->phone_number)
            ->where('is_deleted', 0)
            ->first();

        // If user exists and was added by an employer, let them "register" (verify)
        if ($existingUser && $existingUser->is_staff_added == 1) {
            // Format phone number to E.164
            $to = '+91' . ltrim($request->phone_number, '0');

            // Generate OTP
            $otp = rand(100000, 999999);
            $smsResult = $this->sendOtp(str_replace('+', '', $to), $otp);
            if (!$smsResult['success']) {
                Log::warning('Signup SMS failed (existing staff)', ['number' => $to, 'api' => $smsResult['api']]);
            }

            // Update existing user with OTP
            $existingUser->update([
                'verification_code' => $otp,
                'verification_code_sent_time' => now(),
            ]);

            return response()->json([
                'message' => 'Verification code sent to your Phone Number (Pre-registered account found)',
                'user_id' => $existingUser->id
            ]);
        }

        // Normal validation for completely new users
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email',
            'phone_number' => 'required|string|max:20|unique:users,phone_number,NULL,id,is_deleted,0',
            'business_name' => 'string|max:255|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Format phone number to E.164
        $to = '+91' . ltrim($request->phone_number, '0');

        // Generate OTP
        $otp = rand(100000, 999999);
        $response = $this->sendOtp(str_replace('+', '', $to), $otp);
        if (!$response['success']) {
            Log::warning('Signup SMS failed (new user)', ['number' => $to, 'api' => $response['api']]);
        }
        
        // Create new user
        $user = User::create([
            'name' => $request->name ?? 'User',
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'verification_code' => $otp,
            'verification_code_sent_time' => now(),
            'user_role_id' => in_array($request->role_id, [2, 3]) ? $request->role_id : 3, // Only staff(2) or house_owner(3). Never admin(1).
        ]);

        Notification::create([
            'user_id' => $user->id,
            'title' => 'User Registered',
            'message' => 'Wel come to the our team.',
            'status' => 'unread',
        ]);


        return response()->json([
            'message' => 'Verification code sent to your Phone Number',
            'user_id' => $user->id
        ]);
    }

  public function saveLastWorkExperience(Request $request)
    {
        try {
            $user = Auth::guard('api')->user();
            $validated = $request->validate([
                'id' => 'nullable',
                'role' => 'nullable',
                'join_date' => 'nullable',
                'end_date' => 'nullable',
                'salary' => 'nullable',
                'working_hours' => 'nullable',
                'house_sold' => 'nullable',
                'owner_name' => 'nullable',
                'contact_number' => 'nullable',
                'state' => 'nullable',
                'city' => 'nullable',
            ]);

            $joinDate = $this->parseDateToYmd($validated['join_date'] ?? null);
            $endDate = $this->parseDateToYmd($validated['end_date'] ?? null);

            $data = [
                'user_id' => $user->id,
                'role' => $validated['role'] ?? null,
                'join_date' => $joinDate,
                'end_date' => $endDate,
                'salary' => $validated['salary'] ?? null,
                'working_hours' => $validated['working_hours'] ?? null,
                'house_sold' => $validated['house_sold'] ?? 0,
                'owner_name' => $validated['owner_name'] ?? null,
                'contact_number' => $validated['contact_number'] ?? null,
                'state' => $validated['state'] ?? null,
                'city' => $validated['city'] ?? null,
            ];
            $user->update(['step' => 6]);

            $experience = LastWorkExperience::updateOrCreate(
                ['id' => $validated['id'] ?? null, 'user_id' => $user->id],
                $data
            );

            return response()->json([
                'success' => true,
                'message' => !empty($validated['id'])
                    ? 'Work experience updated successfully'
                    : 'Work experience added successfully',
                'data' => $experience
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('saveLastWorkExperience Fail: ' . $e->getMessage());
            try {
                 file_put_contents(storage_path('logs/debug_error.log'), date('Y-m-d H:i:s') . " - Error in saveLastWorkExperience: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n", FILE_APPEND);
            } catch (\Exception $writeErr) {}
            return response()->json(['success' => false, 'message' => 'Failed to save last work experience', 'error' => $e->getMessage()], 500);
        }
    }
 
public function deleteAcc()
{
    $userId = Auth::guard('api')->user()->id;

  
    $user = User::find($userId);

    if (!$user) {
        return response()->json([
            'status' => 'error',
            'message' => 'User not found'
        ], 404);
    }

    // Mark as deleted (soft delete with in_deleted flag)
    $user->is_deleted = 1;
    $user->save();

    return response()->json([
        'status' => 'success',
        'message' => 'Account deleted successfully'
    ]);
}


public function deleteAccUser($id)
{
    $userId = $id;

  
    $user = User::find($userId);

    if (!$user) {
        return response()->json([
            'status' => 'error',
            'message' => 'User not found'
        ], 404);
    }

    // Mark as deleted (soft delete with in_deleted flag)
    $user->is_deleted = 1;
    $user->save();

    return response()->json([
        'status' => 'success',
        'message' => 'Account deleted successfully'
    ]);
}


public function loginCustomer(Request $request)
{
    $validator = Validator::make($request->all(), [
        'phone_number' => 'required|string|max:20',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    // Format phone number to E.164 format
    $to = '+91' . ltrim($request->phone_number, '0');

    // Development/test numbers
    $devNumbers = [
        '+919782488408',
        '+917020916535',
        '+919001136061',
        '+919509993036',
    ];

    $songName = $request->all();
\Log::info('Login request', ['data' => $songName]);
    // Find user by phone number
    $user = User::where('phone_number', $request->phone_number)
                ->where('is_deleted', 0)
                ->first();

    if (!$user) {
        return response()->json([
            'status'  => false,
            'message' => 'User not found. Please sign up first.'
        ], 404);
    }
    
    // Reuse a fresh pending OTP so delayed SMS messages do not invalidate each other.
    $verificationCode = $this->getReusableOrNewOtp($user);
    $response = $this->sendOtp(str_replace('+', '', $to), $verificationCode);
    if (!$response['success']) {
        \Log::warning('Login SMS failed', ['number' => $to, 'api' => $response['api']]);
    }
    
    // Update verification code and time
    $user->update([
        'verification_code'           => $verificationCode,
        'verification_code_sent_time' => now(),
      //  'country_code'                => $request->country_code,
    ]);



    // --- Send OTP (optional) ---
    // Uncomment if you want to send SMS using Twilio
    /*
    if (!in_array($to, $devNumbers)) {
        try {
            $sid   = env('TWILIO_SID');
            $token = env('TWILIO_AUTH_TOKEN');
            $from  = env('TWILIO_PHONE_NUMBER');
            $url   = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";

            $response = \Http::withBasicAuth($sid, $token)->asForm()->post($url, [
                'From' => $from,
                'To'   => $to,
                'Body' => "Your login verification code for QuickMySlot (QMS) is {$verificationCode}. It expires in 10 minutes."
            ]);

            $data = $response->json();

            \App\Models\SmsLog::create([
                'user_id' => $user->id,
                'to'      => $to,
                'from'    => $from,
                'message' => "Your login verification code for QuickMySlot (QMS) is {$verificationCode}.",
                'status'  => $data['status'] ?? 'sent',
                'sid'     => $data['sid'] ?? null,
                'sent_at' => now(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Login OTP SMS failed: '.$e->getMessage());
        }
    }
    */

    return response()->json([
        'status'  => true,
        'message' => 'OTP sent successfully',
        'user_id' => $user->id,
    ]);
}


public function signUpCustomer(Request $request)
{ 
    $validator = Validator::make($request->all(), [
        'name'         => 'nullable|string|max:255',
        //'email'        => 'nullable|email|unique:users,email',
        'phone_number' => 'required|string|max:20|unique:users,phone_number,NULL,id,is_deleted,0',
        'location'     => 'nullable|string|max:255',
        'lat'         => 'nullable',
        'long'        => 'nullable',
    ]);
    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }
    // Format phone number to E.164
    $to = '91' . ltrim($request->phone_number, '0');

    // Generate OTP
    $otp = rand(100000, 999999);
    $response = $this->sendOtp($to, $otp);
    if (!$response['success']) {
        \Log::warning('signUpCustomer SMS failed', ['number' => $to, 'api' => $response['api']]);
    }
    
    // Create new user (validation ensures phone is unique)
    $user = User::create([
        'name'                          => $request->name ?? 'User',
        'email'                         => $request->email ?? '',
        'phone_number'                  => $request->phone_number,
        'location'                      => $request->location,
        'lat'                           => $request->lat,
        'long'                          => $request->long,
        // The role is finalized on the Choose Role onboarding screen.
        'user_role_id'                  => 3,
        'verification_code'             => $otp,
        'verification_code_sent_time'   => now(),
        'country_code'                  => $request->country_code,
    ]);

    

    return response()->json([
        'status'  => true,
        'message' => 'OTP sent successfully',
        'user_id' => $user->id,
    ]);
}

public function getProfile(Request $request)
{
    try {
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }
        $userDetails = User::with(['addresses.householdInformation','addresses.petDetails','petDetails','lastExp','householdInformation','kycInformation','userWorkInfo','addedByUser', 'addedByUser.addresses.householdInformation', 'addedByUser.addresses.petDetails',
            'addedByUser.petDetails',
            'addedByUser.lastExp',
            'addedByUser.householdInformation',
            'addedByUser.kycInformation',
            'addedByUser.userWorkInfo'])->find($user->id);

        $attendanceSummary = DB::table('attendance')
        ->select('status', DB::raw('COUNT(*) as total'))
        ->where('staff_id', $user->id)
        ->groupBy('status')
        ->get();
        // Return user data without sensitive information
        return response()->json([
            'success' => true,
            'message' => 'Profile retrieved successfully',
            'data' => $userDetails,
            'attendanceSummary' => $attendanceSummary
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to retrieve profile',
            'error' => $e->getMessage()
        ], 500);
    }
}

    public function resetPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'password' => 'required|string|min:6|confirmed'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::find(Auth::guard('api')->user()->id);

            

            // // Check if reset code is expired
            // $expiryTime = $user->password_reset_code_sent_at->addMinutes(10);
            // if (now()->gt($expiryTime)) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Reset code has expired'
            //     ], 422);
            // }

            // Update password and clear reset code
            $user->password = Hash::make($request->password);
            $user->save();

            // Invalidate all existing tokens (optional)
            $user->tokens()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Password reset successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        if ($request->user()) { 
            if (method_exists($request->user(), 'currentAccessToken') && $request->user()->currentAccessToken()) {
                $request->user()->currentAccessToken()->delete();
            } elseif (method_exists($request->user(), 'token') && $request->user()->token()) {
                $request->user()->token()->revoke();
            }
        }
        return response()->json([
            'status' => true,
            'message' => 'Logged out successfully'
        ], 200);
    }

    public function login(Request $request)
    {
        if ($request->isMethod('POST')) {
            $validator = Validator::make(
                $request->all(),
                [
                    'email_or_phone' => 'required',
                    'password'       => 'required|min:8',
                ],
                [
                    'email_or_phone.required' => "This field is required.",
                    'password.required'       => "This field is required.",
                    'password.min'            => "Password must be at least 8 characters.",
                ]
            );

            if ($validator->fails()) {
                return response()->json([
                    "status" => "error",
                    "msg"    => "Input field is required.",
                    "errors" => $validator->errors()
                ], 422);
            }

            // Check whether input is email or phone
            $loginField = $request->email_or_phone;
            if (filter_var($loginField, FILTER_VALIDATE_EMAIL)) {
                // Login using email
                $user = User::where('email', $loginField)->first();
            } else {
                // Login using phone number
                $user = User::where('phone_number', $loginField)->first();
            }

            if (!empty($user) && Hash::check($request->password, $user->password)) {
                try {
                    $token = $user->createToken('AuthToken')->plainTextToken;
                    
                    return response()->json([
                        "status" => "success",
                        "msg"    => "You are now logged in.",
                        "token"  => $token,
                        "user"   => $user
                    ], 200);
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Personal access client not found') !== false) {
                        return response()->json([
                            "status" => "error",
                            "msg"    => "Authentication system not properly configured. Please contact support."
                        ], 500);
                    }

                    return response()->json([
                        "status" => "error",
                        "msg"    => "An error occurred during authentication."
                    ], 500);
                }
            }

            return response()->json([
                "status" => "error",
                "msg"    => "Your email/phone or password is incorrect."
            ], 401);
        }
    }



    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|exists:users,id',
            'phone_number' => 'nullable|string',
            'country_code' => 'nullable|string',
            'otp' => 'required|digits:6',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $user = null;
        if ($request->filled('user_id')) {
            $userQuery = User::where('id', $request->user_id);
            if ($request->filled('phone_number')) {
                $userQuery->where('phone_number', $request->phone_number);
            }
            $user = $userQuery->first();
        }

        if (!$user && $request->filled('phone_number')) {
            $user = User::where('phone_number', $request->phone_number)
                ->where('is_deleted', 0)
                ->latest('id')
                ->first();
        }

        if (!$user) {
            return response()->json([
                'error' => 'User not found for OTP verification. Please request a new OTP.',
            ], 404);
        }
        
        if ($user->is_deleted == 1) {
            return response()->json([
                'error' => 'This account has been deleted. Please contact support.'
            ], 403);
        }
        
        $otpIsFresh = false;
        if (!empty($user->verification_code) && !empty($user->verification_code_sent_time)) {
            try {
                $otpIsFresh = Carbon::parse($user->verification_code_sent_time)->diffInMinutes(now()) < self::OTP_VALID_MINUTES;
            } catch (\Throwable $th) {
                \Log::warning('Failed to parse OTP sent time during verification', [
                    'user_id' => $user->id ?? null,
                    'error' => $th->getMessage(),
                ]);
            }
        }

        if (!$otpIsFresh) {
            return response()->json([
                'error' => 'OTP has expired. Please resend OTP.',
                'otp' => $user->verification_code,
                'debug_stored' => $user->verification_code,
                'debug_user_id' => $user->id,
                'otp_valid_minutes' => self::OTP_VALID_MINUTES,
            ], 422);
        }

        if ((string)$request->otp === (string)$user->verification_code) {
            try {
                $user->update([
                    'is_verified' => 1,
                    'updated_at' => now(),
                ]);

                $user->refresh();
                $token = $user->createToken('AuthToken')->plainTextToken;

                return response()->json([
                    'message' => 'Logged in successfully',
                    'user' => $user,
                    'token' => $token,
                ]);

            } catch (\Exception $e) {
                \Log::error('OTP verification login failed', [
                    'user_id' => $user->id ?? null,
                    'error' => $e->getMessage(),
                ]);
                return response()->json([
                    'error' => 'Login failed. Please try again.',
                    'debug_error' => $e->getMessage(),
                ], 500);
            }
        }

        return response()->json([
            'error' => 'Invalid verification code',
            'otp' => $user->verification_code,
            'debug_sent' => $request->otp,
            'debug_stored' => $user->verification_code,
            'debug_user_id' => $user->id,
        ], 422);
    }


    public function aadharVerifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'otp' => 'required|digits:6'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        \Log::info('Aadhaar Verify Request Input:', [
            'user_id' => $request->user_id,
            'auth_user_id' => Auth::guard('api')->check() ? Auth::guard('api')->user()->id : 'not_authed',
            'has_otp' => $request->has('otp')
        ]);

        $targetUserId = $request->user_id ?? (Auth::guard('api')->check() ? Auth::guard('api')->id() : null);

        if (!$targetUserId) {
            return response()->json(['error' => 'Authentication required or user_id missing.'], 401);
        }

        $user = User::find($targetUserId);
        
        if (!$user) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        if (!$user->aadhar_reference_id) {
            return response()->json([
                'error' => 'No OTP request found for this user.',
                'debug_user_id' => $user->id,
                'debug_name' => $user->name,
                'message' => 'Please request a new OTP first.'
            ], 422);
        }

        try {
            $aadhaarService = new \App\Services\Admin\AadhaarVerificationService();
            try {
                $verifyResult = $aadhaarService->verifyOtp($request->otp, $user->aadhar_reference_id);
            } catch (\Illuminate\Http\Client\ConnectionException $connEx) {
                \Log::error('Aadhaar external API unreachable: ' . $connEx->getMessage());
                return response()->json([
                    'error' => 'Aadhaar verification service is temporarily unavailable. Please try again in a moment.',
                    'message' => 'External service timeout'
                ], 503);
            }
            
            if (!$verifyResult['success']) {
                return response()->json([
                    'error' => $verifyResult['message'] ?? 'Invalid OTP'
                ], 422);
            }
            
            // Update user with verified Aadhaar details
            $aadhaarData = $verifyResult['aadhaar_data'];
            
            $user->update([
                'aadhar__verify' => 1,
                'aadhar__verify_at' => now(),
                'aadhar_reference_id' => null,
                'aadhar_number_otp_expire_at' => null,
                'aadhar_name' => $aadhaarData['name'] ?? $user->aadhar_name,
            ]);
            
            // Optionally update user profile with Aadhaar data
            if (empty($user->name) || $user->name === 'Staff Member' || $user->name === 'User') {
                $user->name = $aadhaarData['name'] ?? $user->name;
            }

            // Also populate first_name and last_name if they are empty
            if (empty($user->first_name) && !empty($user->name)) {
                $parts = explode(' ', trim($user->name));
                $user->first_name = $parts[0];
                if (count($parts) > 1 && empty($user->last_name)) {
                    array_shift($parts);
                    $user->last_name = implode(' ', $parts);
                }
            }
            
            if (empty($user->dob) && !empty($aadhaarData['dob'])) {
                // Convert date format from DD-MM-YYYY to YYYY-MM-DD for MySQL
                try {
                    $dob = $aadhaarData['dob'];
                    // Check if date is in DD-MM-YYYY format
                    if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $dob)) {
                        $dateObj = \DateTime::createFromFormat('d-m-Y', $dob);
                        if ($dateObj && $dateObj->format('d-m-Y') === $dob) {
                            // Valid date conversion
                            $user->dob = $dateObj->format('Y-m-d');
                        } else {
                            \Log::warning('Invalid date format in Aadhaar data: ' . $dob);
                        }
                    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
                        // Already in YYYY-MM-DD format
                        $user->dob = $dob;
                    } else {
                        \Log::warning('Unrecognized date format in Aadhaar data: ' . $dob);
                    }
                } catch (\Exception $e) {
                    \Log::warning('Date conversion failed: ' . $e->getMessage());
                    // Skip dob update if conversion fails
                }
            }
            
            if (empty($user->gender) && !empty($aadhaarData['gender'])) {
                $user->gender = strtolower($aadhaarData['gender']);
            }

            // Save Aadhaar photo as profile image if user has no image
            if (empty($user->image) && !empty($aadhaarData['photo'])) {
                try {
                    $imageData = $aadhaarData['photo'];
                    // Ensure it's a data URI for Cloudinary or just raw base64
                    if (strpos($imageData, 'data:image') === false) {
                        $imageData = 'data:image/jpeg;base64,' . $imageData;
                    }
                    
                    $upload = \CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary::upload($imageData, [
                        'folder' => 'uploads/user_profile_images',
                        'resource_type' => 'auto',
                    ]);
                    
                    if ($upload) {
                        $user->image = $upload->getSecurePath();
                    }
                } catch (\Exception $e) {
                    \Log::warning('Failed to save Aadhaar photo to Cloudinary: ' . $e->getMessage());
                    // Fallback to local storage if Cloudinary fails
                    try {
                        $rawImageData = $aadhaarData['photo'];
                        if (strpos($rawImageData, ',') !== false) {
                            $rawImageData = explode(',', $rawImageData)[1];
                        }
                        $imageContent = base64_decode($rawImageData);
                        $fileName = 'profile_' . $user->id . '_' . time() . '.jpg';
                        $path = 'uploads/profile/' . $fileName;
                        Storage::disk('public')->put($path, $imageContent);
                        $user->image = asset('storage/' . $path);
                    } catch (\Exception $localEx) {
                        \Log::warning('Failed to save Aadhaar photo locally: ' . $localEx->getMessage());
                    }
                }
            }
            
            $user->save();

            // Auto-populate UserAddress from Aadhaar split_address if user doesn't have address
            try {
                $rawResponse = $verifyResult['raw_data'] ?? null;
                $splitAddress = $rawResponse['data']['split_address'] ?? null;
                if ($splitAddress) {
                    $streetParts = array_filter([
                        $splitAddress['house'] ?? null,
                        $splitAddress['street'] ?? null,
                        $splitAddress['locality'] ?? null,
                        $splitAddress['po'] ?? null
                    ]);
                    $street = implode(', ', $streetParts);
                    $city = $splitAddress['dist'] ?? $splitAddress['vtc'] ?? null;
                    $state = $splitAddress['state'] ?? null;
                    $pincode = $splitAddress['pincode'] ?? null;

                    if (!empty($street) || !empty($city) || !empty($state) || !empty($pincode)) {
                        $existingPrimary = \App\Models\UserAddress::where('user_id', $user->id)
                            ->where('is_primary', true)->first();
                        $existingWithGl = \App\Models\UserAddress::where('user_id', $user->id)
                            ->whereNotNull('google_location')->where('google_location', '!=', '')->first();

                        $updateData = [
                            'street' => $street,
                            'city' => $city,
                            'state' => $state,
                            'pincode' => $pincode,
                        ];

                        if ($existingPrimary && !$existingPrimary->google_location && $existingWithGl) {
                            $updateData['google_location'] = $existingWithGl->google_location;
                            $updateData['latitude'] = $existingWithGl->latitude;
                            $updateData['longitude'] = $existingWithGl->longitude;
                        }

                        if ($existingPrimary) {
                            $existingPrimary->update($updateData);
                        } else {
                            \App\Models\UserAddress::create(array_merge($updateData, [
                                'user_id' => $user->id,
                                'is_primary' => true,
                            ]));
                        }
                    }
                }
            } catch (\Exception $addrEx) {
                \Log::warning('Failed to save Aadhaar address: ' . $addrEx->getMessage());
            }

            // Eager load relations for the response to auto-fill forms
            $user->load([
                'addresses',
                'petDetails',
                'lastExp',
                'householdInformation',
                'kycInformation',
                'userWorkInfo',
                'addedByUser',
                'addedByUser.addresses',
                'addedByUser.petDetails',
                'addedByUser.lastExp',
                'addedByUser.householdInformation',
                'addedByUser.kycInformation',
                'addedByUser.userWorkInfo'
            ]);

            return response()->json([
                'message' => 'Aadhaar verified successfully',
                'user' => $user,
                'aadhaar_details' => $aadhaarData
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Aadhaar Verify Error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to verify OTP',
                'message' => $e->getMessage()
            ], 500);
        }
    }



    public function overview()
    {
        try {
            // Only active users that are not deleted
            $totalCustomers = User::where('is_deleted', 0)
                                ->where('is_active', 1)
                                ->count();

            $totalRevenue = 1200.00;   // static for now
            $reach = "5% ";           // static example
            $footfall = "50 / Day";    // static example

            return response()->json([
                'status' => 'success',
                'message' => 'Performance overview retrieved successfully',
                'data' => [
                    'revenue_this_month' => $totalRevenue,
                    'total_customers' => $totalCustomers,
                    'reach' => $reach,
                    'estimated_footfall' => $footfall,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }
        
    public function resendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string|max:20'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('phone_number', $request->phone_number)->where('is_deleted',0)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User with this phone number does not exist.'
            ], 404);
        }

        // Format phone number to E.164
        $to = '+91' . ltrim($request->phone_number, '0');

        // Dev/test numbers
        $devNumbers = [
            '+917339788361',
            '+917733884515',
            '+917020916535',
            '+919001136061',
            '+919509993036',
            // add your other test numbers here
        ];

        // Use fixed code 123456 for ALL numbers for now
        // $verificationCode = 123456;
        $otp = $this->getReusableOrNewOtp($user);
        $response = $this->sendOtp(str_replace('+', '', $to), $otp);
        if (!$response['success']) {
            \Log::warning('Resend OTP SMS failed', ['number' => $to, 'api' => $response['api']]);
        }
        

        // Update user with new code
        $user->update([
            'verification_code' => $otp,
            'verification_code_sent_time' => now(),
            'updated_at' => now()
        ]);
        // SMS Sending Logic (Commented Out)
        /*
        // ...
        */

        return response()->json([
            'message' => 'Verification code resent to your Phone Number',
            'user_id' => $user->id,
        ]);
    }

// public function updateProfile(Request $request)
// {
//     try {
//         $user = Auth::guard('api')->user();
        
//         if (!$user) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'User not found'
//             ], 404);
//         }

//         $validator = Validator::make($request->all(), [
//             // Basic Information
//             'first_name' => 'nullable|string|max:255',
//                         'user_role_id' => 'nullable',
//             'last_name' => 'nullable|string|max:255',
//             'name' => 'nullable|string|max:255',
//             'email' => 'nullable|email|unique:users,email,' . $user->id,
//             'phone_number' => 'nullable|string|max:20',
//             'gender' => 'nullable|string|in:male,female,other',
//             'dob' => 'nullable|date',
            
//             // Address Information (multiple addresses)
//             'addresses' => 'nullable|array',
//             'addresses.*.street' => 'required_with:addresses|string',
//             'addresses.*.city' => 'required_with:addresses|string',
//             'addresses.*.state' => 'required_with:addresses|string',
//             'addresses.*.pincode' => 'required_with:addresses|string',
//             'addresses.*.is_primary' => 'nullable|boolean',
            
//             // Household Information
//             'residence_type' => 'nullable|string|max:255',
//             'number_of_rooms' => 'nullable|integer|min:1',
//             'languages_spoken' => 'nullable|array',
//             'adults_count' => 'nullable|integer|min:0',
//             'children_count' => 'nullable|integer|min:0',
//             'elderly_count' => 'nullable|integer|min:0',
//             'special_requirements' => 'nullable|string|max:1000',
            
//             // Pet Details
//             'pet_details' => 'nullable|array',
//             'pet_details.*.pet_type' => 'required_with:pet_details|string|max:255',
//             'pet_details.*.pet_count' => 'required_with:pet_details|integer|min:1',
            
//         ]);

//         if ($validator->fails()) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Validation failed',
//                 'errors' => $validator->errors()
//             ], 422);
//         }

//         $data = $validator->validated();

//   $jsonResponse = json_encode($data, JSON_PRETTY_PRINT);

//     // File path inside storage folder
//     $filePath = storage_path('logs/api_response_log.txt');

//     // Open the file for appending (creates file if not exists)
//     $file = fopen($filePath, 'a');

//     if ($file) {
//         fwrite($file, "==== " . date('Y-m-d H:i:s') . " ====\n");
//         fwrite($file, $jsonResponse . "\n\n");
//         fclose($file);
//     } else {
//         // Handle error if file couldn't be opened
//         \Log::error('Could not open log file for writing.');
//     }
//         // Handle profile picture upload
//         if ($request->hasFile('profile_picture')) {
//             $directory = "uploads/user_profile_images";
            
//             if (!file_exists(public_path($directory))) {
//                 mkdir(public_path($directory), 0755, true);
//             }

//             $image = $request->file('profile_picture');
//             $extension = $image->getClientOriginalExtension();
//             $fileName = time() . '_' . uniqid() . '.' . $extension;
//             $image->move(public_path($directory), $fileName);

//             $path = $directory . '/' . $fileName;

//             // Delete old profile picture if exists
//             if ($user->image && file_exists(public_path($user->image))) {
//                 unlink(public_path($user->image));
//             }

//             $data['image'] = $path;
//         }
//         $data['step'] = 2;

//         // Update basic user information
//         $user->update($data);

//         if ($request->hasFile('verification_certificate') 
//         && $request->hasFile('aadhar_front') 
//         && $request->hasFile('aadhar_back')) 
//         {
//             $directory = "uploads/verification_certificate";
        
//             // Create directory if it doesn't exist
//             if (!file_exists(public_path($directory))) {
//                 mkdir(public_path($directory), 0755, true);
//             }
        
//             // Function to handle file upload
//             $uploadFile = function($file) use ($directory) {
//                 $extension = $file->getClientOriginalExtension();
//                 $fileName = time() . '_' . uniqid() . '.' . $extension;
//                 $file->move(public_path($directory), $fileName);
//                 return $directory . '/' . $fileName;
//             };
        
//             // Upload files
//             $verificationCertificatePath = $uploadFile($request->file('verification_certificate'));
//             $aadharFrontPath            = $uploadFile($request->file('aadhar_front'));
//             $aadharBackPath             = $uploadFile($request->file('aadhar_back'));
        
//             // Optionally delete old files if exist
//             if ($user->verification_certificate && file_exists(public_path($user->verification_certificate))) {
//                 unlink(public_path($user->verification_certificate));
//             }
//             if ($user->aadhar_front && file_exists(public_path($user->aadhar_front))) {
//                 unlink(public_path($user->aadhar_front));
//             }
//             if ($user->aadhar_back && file_exists(public_path($user->aadhar_back))) {
//                 unlink(public_path($user->aadhar_back));
//             }
        
//             // Update user with new file paths and step
//             $user->update([
//                 'verification_certificate' => $verificationCertificatePath,
//                 'aadhar_front'             => $aadharFrontPath,
//                 'aadhar_back'              => $aadharBackPath,
//                 'step'                     => 3,
//             ]);
//         }
    
    
//         // Handle multiple addresses
//         if ($request->has('addresses')) {
//             // Delete existing addresses
//             $user->addresses()->delete();
            
//             // Create new addresses
//             foreach ($request->addresses as $address) {
//                 $user->addresses()->create($address);
//             }
//             if($user->user_role_id == 2){
//                 $user->update(['step' => 4]);
//             }else{
//                 $user->update(['step' => 3]);
//             }

//         }

//         // Handle household information
//         if ($request->hasAny(['residence_type', 'number_of_rooms', 'languages_spoken', 'adults_count', 'children_count', 'elderly_count', 'special_requirements'])) {
//             $householdData = $request->only([
//                 'residence_type', 'number_of_rooms', 'languages_spoken', 
//                 'adults_count', 'children_count', 'elderly_count', 'special_requirements'
//             ]);
            
//             if ($user->householdInformation) {
//                 $user->householdInformation()->update($householdData);
//             } else {
//                 $user->householdInformation()->create($householdData);
//             }
//             $user->update(['step' => 4]);
//         }
      
//         // Handle pet details
//         if ($request->has('pet_details')) {
//             // Delete existing pet details
//             $user->petDetails()->delete();
            
//             // Create new pet details
//             foreach ($request->pet_details as $petDetail) {
//                 $user->petDetails()->create($petDetail);
//             }
//             $user->update(['step' => 4]);

//         }
//         // Handle portfolio images
      
//         $user->load(['addresses', 'petDetails', 'householdInformation']);

//         return response()->json([
//             'success' => true,
//             'message' => 'Profile updated successfully',
//             'data' => $user
//         ], 200);

//     } catch (\Exception $e) {
//         return response()->json([
//             'success' => false,
//             'message' => 'Failed to update profile',
//             'error' => $e->getMessage()
//         ], 500);
//     }
// }

public function updateProfile(Request $request)
{
    try {
        $user = Auth::guard('api')->user();
        if (!$user) return response()->json(['success' => false, 'message' => 'User not found'], 404);

        if ($request->has('dob') && !empty($request->dob)) {
            try {
                $dob = $request->dob;
                $formattedDob = \Carbon\Carbon::parse($dob)->format('Y-m-d');
                $request->merge(['dob' => $formattedDob]);
            } catch (\Exception $e) {
                try {
                    $formattedDob = \Carbon\Carbon::createFromFormat('d/m/y', $dob)->format('Y-m-d');
                    $request->merge(['dob' => $formattedDob]);
                } catch (\Exception $ex) {
                    try {
                        $formattedDob = \Carbon\Carbon::createFromFormat('d-m-Y', $dob)->format('Y-m-d');
                        $request->merge(['dob' => $formattedDob]);
                    } catch (\Exception $ex2) {
                        // Keep original if all parsing fails
                    }
                }
            }
        }
        $isEdit = $request->input('is_edit', 0);
        $validator = Validator::make($request->all(), [
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'phone_number' => 'nullable|string|max:20',
            'gender' => 'nullable|string|in:male,female,other',
            'dob' => 'nullable|date',
            'user_role_id' => 'nullable|integer|in:2,3',
            'addresses' => 'nullable|array',
            'addresses.*.street' => 'nullable|string',
            'addresses.*.city' => 'nullable|string',
            'addresses.*.state' => 'nullable|string',
            'addresses.*.pincode' => 'nullable|string',
            'addresses.*.is_primary' => 'nullable|boolean',
            'addresses.*.area_locality' => 'nullable|string',
            'addresses.*.google_location' => 'nullable|string',
            'addresses.*.lat' => 'nullable|string',
            'addresses.*.long' => 'nullable|string',
            'residence_type' => 'nullable|string|max:255',
            'number_of_rooms' => 'nullable|integer|min:1',
            'adults_count' => 'nullable|integer|min:0',
            'children_count' => 'nullable|integer|min:0',
            'elderly_count' => 'nullable|integer|min:0',
            'special_requirements' => 'nullable|string|max:1000',
            'pet_details' => 'nullable|array',
            'pet_details.*.pet_type' => 'nullable|string|max:255',
            'pet_details.*.pet_count' => 'nullable|integer|min:1',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'languages_spoken' => 'nullable|array',
            'auto_attendence' => 'nullable|boolean',
            'upi_id' => 'nullable|string|max:255',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_number' => 'nullable|string|max:255',
            'relation' => 'nullable|string|max:255',
            'preferred_work_location' => 'nullable|string|max:255'
        ]);
        if ($validator->fails()) {
            try {
                file_put_contents(storage_path('logs/debug_error.log'), date('Y-m-d H:i:s') . " - Validation failed in updateProfile: " . json_encode($validator->errors()->toArray()) . "\n" . "Request Data: " . json_encode($request->all()) . "\n\n", FILE_APPEND);
            } catch (\Exception $writeErr) {}
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        $data = $validator->validated();

        $approvedRoleId = null;
        $roleOnlyRequest = false;
        if ($request->has('user_role_id')) {
            $requestedRoleId = (int) $request->user_role_id;
            $roleOnlyRequest = empty(array_diff(
                array_keys($request->all()),
                ['user_role_id', 'is_edit']
            ));
            $hasOnboardingData = UserAddress::where('user_id', $user->id)->exists()
                || UserWorkInfo::where('user_id', $user->id)->exists()
                || UserHouseholdInformation::where('user_id', $user->id)->exists()
                || \App\Models\UserPetDetail::where('user_id', $user->id)->exists()
                || SubscriptionUser::where('user_id', $user->id)->exists();
            $canChooseOnboardingRole = (int) $isEdit !== 1
                && (int) $user->is_verified === 1
                && (int) ($user->step ?? 1) <= 2
                && !$hasOnboardingData;

            if ((int) $user->user_role_id === $requestedRoleId || $canChooseOnboardingRole) {
                $approvedRoleId = $requestedRoleId;
            } else {
                \Log::warning('Role change blocked outside onboarding', [
                    'user_id' => $user->id,
                    'requested_role_id' => $requestedRoleId,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Role can only be selected during initial account setup.',
                ], 403);
            }
        }
        
        // ✅ Profile picture upload
        if ($request->hasFile('profile_picture')) {
            $folderPath = "uploads/user_profile_images";
            $path = null; // initialize to avoid undefined variable if upload fails
            try {
                $path = $this->uploadCloudary($request,"profile_picture",$folderPath);
            } catch (\Throwable $th) {
                \Log::error('Profile picture upload failed: ' . $th->getMessage());
            }
            if ($path) {
                $data['image'] = $path;
            }
        }
        try {
            if ($request->hasFile('aadhar_front')) {
                $aadharFrontPath = $this->uploadCloudary($request,"aadhar_front","staff/aadhar");
                $data['aadhar_front'] = $aadharFrontPath;
            }
        } catch (\Exception $e) {
            \Log::error('Aadhar front photo upload failed: ' . $e->getMessage());
        }

        try {
            if ($request->hasFile('aadhar_back')) {
                $aadharBackPath = $this->uploadCloudary($request,"aadhar_back","staff/aadhar");
                $data['aadhar_back'] = $aadharBackPath;
            }
        } catch (\Exception $e) {
            \Log::error('Aadhar back photo upload failed: ' . $e->getMessage());
        }

        try {
            if ($request->hasFile('verification_certificate')) {
                $policeClearancePath = $this->uploadCloudary($request,"verification_certificate","staff/documents");
                $data['verification_certificate'] = $policeClearancePath;
            }
        } catch (\Exception $e) {
            \Log::error('Police clearance certificate upload failed: ' . $e->getMessage());
        }

        $allowedKeys = [
            'first_name', 'last_name', 'name', 'email', 'phone_number',
            'gender', 'dob', 'auto_attendence', 'upi_id', 'relation'
        ];

        $userUpdateFields = [];
        foreach ($allowedKeys as $key) {
            if ($request->has($key)) {
                $userUpdateFields[$key] = $data[$key];
            }
        }

        if ($approvedRoleId !== null) {
            $userUpdateFields['user_role_id'] = $approvedRoleId;
        }

        // Handle uploaded files
        if (isset($data['image'])) {
            $userUpdateFields['image'] = $data['image'];
        }
        if (isset($data['aadhar_front'])) {
            $userUpdateFields['aadhar_front'] = $data['aadhar_front'];
        }
        if (isset($data['aadhar_back'])) {
            $userUpdateFields['aadhar_back'] = $data['aadhar_back'];
        }
        if (isset($data['verification_certificate'])) {
            $userUpdateFields['verification_certificate'] = $data['verification_certificate'];
        }

        if ($isEdit != 1) {
            $userUpdateFields['step'] = 2;
        }

        try {
            if (!empty($userUpdateFields)) {
                $user->update($userUpdateFields);
            }
        } catch (\Throwable $th) {
            \Log::error('updateProfile user->update failed: ' . $th->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update basic profile fields',
                'error' => $th->getMessage()
            ], 500);
        }

        if ($roleOnlyRequest) {
            return response()->json([
                'success' => true,
                'message' => 'Role selected successfully',
                'data' => $user->fresh(),
            ]);
        }

        // saveWorkAndExperience is only for staff (role 2), not household employers

        // ✅ Sync uploaded documents to kyc_verifications table too
        try {
            $kycData = ['user_id' => $user->id];
            if (isset($data['aadhar_front'])) {
                $kycData['aadhaar_front_path'] = $data['aadhar_front'];
            }
            if (isset($data['aadhar_back'])) {
                $kycData['aadhaar_back_path'] = $data['aadhar_back'];
            }
            if (isset($data['verification_certificate'])) {
                $kycData['police_verification_path'] = $data['verification_certificate'];
            }
            if (count($kycData) > 1) {
                \App\Models\KycVerification::updateOrCreate(
                    ['user_id' => $user->id],
                    $kycData
                );
            }
        } catch (\Throwable $th) {
            \Log::warning('updateProfile KYC sync failed (non-fatal): ' . $th->getMessage());
        }

        $workFields = ['emergency_contact_name', 'emergency_contact_number', 'preferred_work_location', 'primary_role', 'skills', 'languages_spoken', 'total_experience', 'education', 'additional_info', 'voice_note'];
        if (($isEdit == 1 || $request->hasAny($workFields)) && $user->user_role_id == 2) {
            try {
                $this->saveWorkAndExperience($user, $request, $isEdit);
            } catch (\Illuminate\Validation\ValidationException $e) {
                \Log::error('saveWorkAndExperience ValidationException: ' . json_encode($e->errors()));
                try {
                     file_put_contents(storage_path('logs/debug_error.log'), date('Y-m-d H:i:s') . " - Validation Exception in saveWorkAndExperience: " . json_encode($e->errors()) . "\n", FILE_APPEND);
                } catch (\Exception $writeErr) {}
            } catch (\Throwable $th) {
                \Log::error('saveWorkAndExperience failed: ' . $th->getMessage());
                try {
                     file_put_contents(storage_path('logs/debug_error.log'), date('Y-m-d H:i:s') . " - Exception in saveWorkAndExperience: " . $th->getMessage() . "\n" . $th->getTraceAsString() . "\n\n", FILE_APPEND);
                } catch (\Exception $writeErr) {}
                // non-fatal
            }
        }

        $savedAddressCount = null;
        $savedNestedHousehold = false;
        $savedNestedPets = false;
        $savedGlobalHousehold = false;
        $savedGlobalPets = false;

        // ✅ Update addresses, pets, and household (per-address)
        if ($request->has('addresses')) {
            try {
                \DB::transaction(function () use ($user, $request, &$savedAddressCount, &$savedNestedHousehold, &$savedNestedPets) {
                    $savedAddressCount = 0;
                    $oldAddressIds = $user->addresses()->pluck('id');
                    if ($oldAddressIds->isNotEmpty()) {
                        \App\Models\UserHouseholdInformation::where('user_id', $user->id)
                            ->whereIn('address_id', $oldAddressIds)
                            ->delete();
                        \App\Models\UserPetDetail::where('user_id', $user->id)
                            ->whereIn('address_id', $oldAddressIds)
                            ->delete();
                    }
                    $user->addresses()->delete();
                    foreach ($request->addresses as $address) {
                        if (!is_array($address)) continue;
                        $hasData = !empty(array_filter([
                            $address['street'] ?? '',
                            $address['city'] ?? '',
                            $address['state'] ?? '',
                            $address['pincode'] ?? '',
                        ]));
                        if ($hasData) {
                            $safe = array_intersect_key($address, array_flip([
                                'street', 'city', 'state', 'pincode', 'is_primary', 'area_locality', 'google_location'
                            ]));
                            $safe['name'] = $address['title'] ?? $address['name'] ?? '';
                            if (isset($address['lat'])) $safe['latitude'] = $address['lat'];
                            if (isset($address['long'])) $safe['longitude'] = $address['long'];
                            $savedAddress = $user->addresses()->create($safe);
                            $savedAddressCount++;
                            $addressId = $savedAddress->id;

                            $householdRaw = $address['household'] ?? null;
                            if (is_string($householdRaw)) $householdRaw = json_decode($householdRaw, true);
                            if (is_array($householdRaw) && !empty(array_filter($householdRaw))) {
                                $hData = [
                                    'user_id' => $user->id,
                                    'address_id' => $addressId,
                                    'residence_type' => $householdRaw['residence_type'] ?? null,
                                    'number_of_rooms' => isset($householdRaw['number_of_rooms']) ? (int) $householdRaw['number_of_rooms'] : null,
                                    'languages_spoken' => $householdRaw['languages_spoken'] ?? [],
                                    'adults_count' => isset($householdRaw['adults_count']) ? (int) $householdRaw['adults_count'] : null,
                                    'children_count' => isset($householdRaw['children_count']) ? (int) $householdRaw['children_count'] : null,
                                    'elderly_count' => isset($householdRaw['elderly_count']) ? (int) $householdRaw['elderly_count'] : null,
                                    'special_requirements' => $householdRaw['special_requirements'] ?? null,
                                ];
                                \App\Models\UserHouseholdInformation::updateOrCreate(
                                    ['user_id' => $user->id, 'address_id' => $addressId],
                                    $hData
                                );
                                $savedNestedHousehold = true;
                            }

                            $petsRaw = $address['pets'] ?? null;
                            if (is_string($petsRaw)) $petsRaw = json_decode($petsRaw, true);
                            if (is_array($petsRaw)) {
                                \App\Models\UserPetDetail::where('user_id', $user->id)->where('address_id', $addressId)->delete();
                                foreach ($petsRaw as $pet) {
                                    if (!is_array($pet)) continue;
                                    $type = trim((string)($pet['pet_type'] ?? ''));
                                    $count = $pet['pet_count'] ?? null;
                                    if ($type === '') continue;
                                    \App\Models\UserPetDetail::create([
                                        'user_id' => $user->id,
                                        'address_id' => $addressId,
                                        'pet_type' => $type,
                                        'pet_count' => $count !== null && $count !== '' ? (int) $count : null,
                                    ]);
                                    $savedNestedPets = true;
                                }
                            }
                        }
                    }
                });
                if ($savedAddressCount === 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Please save a valid address before continuing.',
                        'errors' => [
                            'addresses' => ['Please complete and save your address first.']
                        ],
                    ], 422);
                }
            } catch (\Throwable $th) {
                \Log::error('updateProfile addresses save failed: ' . $th->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update addresses and household information',
                    'error' => $th->getMessage()
                ], 500);
            }
        }

        // Backward compat: global household fields (no address_id) for staff step flow
        if ($request->hasAny(['residence_type', 'number_of_rooms', 'adults_count', 'children_count', 'elderly_count', 'special_requirements', 'languages_spoken']) && !$request->has('addresses')) {
            try {
                $householdData = $request->only(['residence_type', 'number_of_rooms', 'adults_count', 'children_count', 'elderly_count', 'special_requirements','languages_spoken']);
                foreach (['number_of_rooms', 'adults_count', 'children_count', 'elderly_count'] as $k) {
                    if (array_key_exists($k, $householdData)) {
                        $householdData[$k] = $householdData[$k] === '' || $householdData[$k] === null
                            ? null
                            : (int) $householdData[$k];
                    }
                }
                $existingGlobal = \App\Models\UserHouseholdInformation::where('user_id', $user->id)->whereNull('address_id')->first();
                if ($existingGlobal) $existingGlobal->update($householdData);
                else \App\Models\UserHouseholdInformation::create(array_merge($householdData, ['user_id' => $user->id]));
                $savedGlobalHousehold = true;
            } catch (\Throwable $th) {
                \Log::error('updateProfile household info save failed: ' . $th->getMessage());
            }
        }

        // Backward compat: global pet details for staff step flow
        if ($request->has('pet_details') && !$request->has('addresses')) {
            try {
                $user->petDetails()->whereNull('address_id')->delete();
                foreach ($request->pet_details as $petDetail) {
                    if (!is_array($petDetail)) continue;
                    $type = trim((string)($petDetail['pet_type'] ?? ''));
                    $count = $petDetail['pet_count'] ?? null;
                    if ($type === '') continue;
                    $user->petDetails()->create([
                        'pet_type' => $type,
                        'pet_count' => $count !== null && $count !== '' ? (int) $count : null,
                    ]);
                    $savedGlobalPets = true;
                }
            } catch (\Throwable $th) {
                \Log::error('updateProfile pet_details save failed: ' . $th->getMessage());
            }
        }
        if ($request->has('auto_attendence')) {
            try {
                $user->update(["auto_attendence" => $request->auto_attendence]);
            } catch (\Throwable $th) {
                \Log::error('updateProfile auto_attendence update failed: ' . $th->getMessage());
            }
        }

        $user->load(['addresses.householdInformation', 'addresses.petDetails', 'petDetails', 'householdInformation','userWorkInfo']);
        
        // Calculate and set final step based on data provided (only if not in edit mode)
        if ($isEdit != 1) {
            $finalStep = 2; // Default: basic info
            $hasPersistedAddresses = $savedAddressCount !== null
                ? $savedAddressCount > 0
                : $user->addresses->isNotEmpty();
            $hasPersistedHousehold = $savedNestedHousehold
                || $savedGlobalHousehold
                || \App\Models\UserHouseholdInformation::where('user_id', $user->id)->exists();
            $hasPersistedPets = $savedNestedPets
                || $savedGlobalPets
                || \App\Models\UserPetDetail::where('user_id', $user->id)->exists();
            
            // Advance only from data that was actually saved, not just submitted.
            if ($hasPersistedAddresses) {
                $finalStep = $user->user_role_id == 2 ? 4 : 3;
            }
            
            if ($hasPersistedHousehold) {
                $finalStep = 4;
            }
            
            if ($hasPersistedPets) {
                $finalStep = 4;
            }
            
            // Work experience is the highest step (only for staff role 2)
            if ($user->user_role_id == 2 && ($request->has('primary_role') || $request->has('skills') || $request->has('total_experience'))) {
                $finalStep = 6;
            }
            
            $user->update(['step' => $finalStep]);
        }
        
        return response()->json(['success' => true, 'message' => 'Profile updated successfully', 'data' => $user->fresh(['addresses.householdInformation', 'addresses.petDetails', 'householdInformation', 'petDetails', 'userWorkInfo', 'kycInformation'])]);

    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Update Profile Fail: ' . $e->getMessage());
        try {
             file_put_contents(storage_path('logs/debug_error.log'), date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n", FILE_APPEND);
        } catch (\Exception $writeErr) {}

        return response()->json(['success' => false, 'message' => 'Failed to update profile', 'error' => $e->getMessage()], 500);
    }
}

private function saveWorkAndExperience($user, $request, $isEdit)
{
    // Work Info
    $workValidated = $request->validate([
        'primary_role' => 'nullable|string|max:255',
        'skills' => 'nullable',
        'languages_spoken' => 'nullable',
        'total_experience' => 'nullable',
        'education' => 'nullable|string|max:255',
        'additional_info' => 'nullable',
        'voice_note' => 'nullable|file|max:10240',
        'stay_type' => 'nullable|string|max:255',
        'emergency_contact_name' => 'nullable|string|max:255',
        'emergency_contact_number' => 'nullable|string|max:255',
            'relation' => 'nullable|string|max:255',
        'preferred_work_location' => 'nullable|string|max:255',
        'salary_closing_date' => 'nullable|integer|min:1|max:31',
    ]);

    $data = [];

    if ($request->has('stay_type')) {
        $data['stay_type'] = $workValidated['stay_type'];
    }

    if ($request->has('primary_role')) {
        $data['primary_role'] = $workValidated['primary_role'];
    }

    if ($request->has('skills')) {
        $skills = $request->input('skills');
        if (is_string($skills)) {
            $decoded = json_decode($skills, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $skills = $decoded;
            } else {
                $skills = array_map('trim', explode(',', $skills));
            }
        }
        $data['skills'] = $skills ?? [];
    }

    if ($request->has('languages_spoken')) {
        $languages = $request->input('languages_spoken');
        if (is_string($languages)) {
            $decoded = json_decode($languages, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $languages = $decoded;
            } else {
                $languages = array_map('trim', explode(',', $languages));
            }
        }
        $data['languages_spoken'] = $languages;

        UserHouseholdInformation::updateOrCreate(
            ['user_id' => $user->id],
            ['languages_spoken' => $languages]
        );
    }

    if ($request->has('total_experience')) {
        $totalExperience = $request->input('total_experience');
        if ($totalExperience !== null) {
            if (preg_match('/[0-9]+(\.[0-9]+)?/', $totalExperience, $matches)) {
                $totalExperience = (float)$matches[0];
            } else {
                $totalExperience = null;
            }
        }
        $data['total_experience'] = $totalExperience;
    }

    if ($request->has('education')) {
        $data['education'] = $workValidated['education'];
    }

    if ($request->has('additional_info')) {
        $data['additional_info'] = $workValidated['additional_info'];
    }

    if ($request->has('emergency_contact_name')) {
        $data['emergency_contact_name'] = $workValidated['emergency_contact_name'];
    }

    if ($request->has('emergency_contact_number')) {
        $data['emergency_contact_number'] = $workValidated['emergency_contact_number'];
    }

    if ($request->has('preferred_work_location')) {
        $data['preferred_work_location'] = $workValidated['preferred_work_location'];
    }

    if ($request->has('salary_closing_date')) {
        $data['salary_closing_date'] = $workValidated['salary_closing_date'];
    }

    if ($request->hasFile('voice_note')) {
        $directory = "uploads/user_voice_notes";
        $path = $this->uploadCloudary($request, "voice_note", $directory);
        $data['voice_note'] = $path;
    }

    if (!empty($data)) {
        UserWorkInfo::updateOrCreate(['user_id' => $user->id], $data);
    }

    // Save relation to users table (not on user_work_infos)
    if ($request->has('relation') && !empty($workValidated['relation'] ?? null)) {
        $user->update(['relation' => $workValidated['relation']]);
    }

    // Last Work Experience - only update if relevant experience fields are sent
    $expFields = ['role', 'join_date', 'end_date', 'salary', 'working_hours', 'house_sold', 'owner_name', 'contact_number', 'state', 'city'];
    if ($request->hasAny($expFields)) {
        $expValidated = $request->validate([
            'id' => 'nullable',
            'role' => 'nullable',
            'join_date' => 'nullable',
            'end_date' => 'nullable',
            'salary' => 'nullable',
            'working_hours' => 'nullable',
            'house_sold' => 'nullable',
            'owner_name' => 'nullable',
            'contact_number' => 'nullable',
            'state' => 'nullable',
            'city' => 'nullable',
        ]);

        $expData = ['user_id' => $user->id];

        if ($request->has('role')) $expData['role'] = $expValidated['role'];
        if ($request->has('join_date')) $expData['join_date'] = $this->parseDateToYmd($expValidated['join_date']);
        if ($request->has('end_date')) $expData['end_date'] = $this->parseDateToYmd($expValidated['end_date']);
        if ($request->has('salary')) $expData['salary'] = $expValidated['salary'];
        if ($request->has('working_hours')) $expData['working_hours'] = $expValidated['working_hours'];
        if ($request->has('house_sold')) $expData['house_sold'] = $expValidated['house_sold'] ?? 0;
        if ($request->has('owner_name')) $expData['owner_name'] = $expValidated['owner_name'];
        if ($request->has('contact_number')) $expData['contact_number'] = $expValidated['contact_number'];
        if ($request->has('state')) $expData['state'] = $expValidated['state'];
        if ($request->has('city')) $expData['city'] = $expValidated['city'];

        LastWorkExperience::updateOrCreate(
            ['id' => $expValidated['id'] ?? null, 'user_id' => $user->id],
            $expData
        );
    }

    // Step will be set by parent function (updateProfile)
}


    public function destroy($id)
    {
        try {
            $category = Category::find($id);
            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Category not found'
                ], 404);
            }

            $category->is_deleted = 1;
            $category->save();

            return response()->json([
                'success' => true,
                'message' => 'Category deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

public function categoryUpdate(Request $request, $id)
{
    try {
        // Find the category by ID
        $category = Category::find($id);
        
        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        // Validate the request
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255|unique:categories,name,' . $id,
            'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
            // Add other fields as needed
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // Handle image upload if provided
        if ($request->hasFile('image')) {
            $directory = "uploads/categories";
            
            // Create directory if it doesn't exist
            // if (!file_exists(public_path($directory))) {
            //     mkdir(public_path($directory), 0755, true);
            // }

            // $image = $request->file('image');
            // $extension = $image->getClientOriginalExtension();
            // $fileName = time() . '_' . uniqid() . '.' . $extension;
            // $image->move(public_path($directory), $fileName);

            // $path = $directory . '/' . $fileName;

            // // Delete old image if exists
            // if ($category->image && file_exists(public_path($category->image))) {
            //     unlink(public_path($category->image));
            // }
            $path = $this->uploadCloudary($request,"image",$directory);

            // Add the new image path to data
            $data['image'] = $path;
        }

        // Update category data
        $category->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'data' => $category
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to update category',
            'error' => $e->getMessage()
        ], 500);
    }
}







// public function updateProfileCustomer(Request $request)
// {
//     try {
//         $user = Auth::guard('api')->user();
        
//         if (!$user) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'User not found'
//             ], 404);
//         }

//         $validator = Validator::make($request->all(), [
//             'name' => 'sometimes|string|max:255',
//             'email' => 'nullable|email|unique:users,email,' . $user->id,
//             'phone' => 'nullable|string|max:20',
//             'address' => 'nullable|string|max:500',
//             'city' => 'nullable|string|max:100',
//             'state' => 'nullable|string|max:100',
//             'country' => 'nullable|string|max:100',
//             'zip_code' => 'nullable|string|max:20',
//             'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // File upload validation
//             'location' => 'nullable',
//              'lat' => 'nullable',
//         'long' => 'nullable',
//         'user_role_id' => 'nullable',
//         ]);

//         if ($validator->fails()) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Validation failed',
//                 'errors' => $validator->errors()
//             ], 422);
//         }

//         $data = $validator->validated();

//         // Handle profile picture upload
//         if ($request->hasFile('profile_picture')) {
//             $directory = "uploads/user_profile_images";
            
//             // Create directory if it doesn't exist
//             if (!file_exists(public_path($directory))) {
//                 mkdir(public_path($directory), 0755, true);
//             }

//             $image = $request->file('profile_picture');
//             $extension = $image->getClientOriginalExtension();
//             $fileName = time() . '_' . uniqid() . '.' . $extension;
//             $image->move(public_path($directory), $fileName);

//             $path = $directory . '/' . $fileName;

//             // Delete old profile picture if exists
//             if ($user->profile_picture && file_exists(public_path($user->profile_picture))) {
//                 unlink(public_path($user->profile_picture));
//             }

//             // Add the new profile picture path to data
//             $data['image'] = $path;
           
//         }
//  $data['steps'] = 2;
//         // Update user data
//         $user->update($data);

//         return response()->json([
//             'success' => true,
//             'message' => 'Profile updated successfully',
//             'data' => $user
//         ], 200);

//     } catch (\Exception $e) {
//         return response()->json([
//             'success' => false,
//             'message' => 'Failed to update profile',
//             'error' => $e->getMessage()
//         ], 500);
//     }
// }


public function updateProfileCustomer(Request $request)
{
    try {
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        $isEdit = $request->input('is_edit', 0);

        if ($request->has('dob') && !empty($request->dob)) {
            try {
                $dob = $request->dob;
                $formattedDob = \Carbon\Carbon::parse($dob)->format('Y-m-d');
                $request->merge(['dob' => $formattedDob]);
            } catch (\Exception $e) {
                try {
                    $formattedDob = \Carbon\Carbon::createFromFormat('d/m/y', $dob)->format('Y-m-d');
                    $request->merge(['dob' => $formattedDob]);
                } catch (\Exception $ex) {
                    try {
                        $formattedDob = \Carbon\Carbon::createFromFormat('d-m-Y', $dob)->format('Y-m-d');
                        $request->merge(['dob' => $formattedDob]);
                    } catch (\Exception $ex2) {
                        // Keep original if all parsing fails
                    }
                }
            }
        }

        $validator = Validator::make($request->all(), [
            'name'            => 'sometimes|string|max:255',
            'first_name'      => 'nullable|string|max:255',
            'last_name'       => 'nullable|string|max:255',
            'email'           => 'nullable|email|unique:users,email,' . $user->id,
            'phone'           => 'nullable|string|max:20',
            'gender'          => 'nullable|string|in:male,female,other',
            'dob'             => 'nullable|string|max:20',
            'location'        => 'nullable',
            'lat'             => 'nullable',
            'long'            => 'nullable',
            'user_role_id'    => 'nullable',
            'auto_attendence' => 'nullable|in:0,1',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'languages_spoken'=> 'nullable|array',
            'upi_id'          => 'nullable|string|max:255',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_number' => 'nullable|string|max:255',
            'relation' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $data = array_filter($validator->validated(), fn($v) => !is_null($v));

        // ✅ Handle profile picture
        if ($request->hasFile('profile_picture')) {
            $directory = "uploads/user_profile_images";
            $path = $this->uploadCloudary($request,"profile_picture",$directory);
            $data['image'] = $path;
        }

        // ✅ Build safe user update data — only columns that exist in users table
        $userUpdateData = array_filter([
            'name'       => $request->name,
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
            'email'      => $request->email,
            'gender'     => $request->gender,
            'dob'        => $request->dob,
            'location'   => $request->location,
            'lat'        => $request->lat,
            'long'       => $request->long,
            'upi_id'     => $request->upi_id,
            'relation'   => $request->relation,
        ], fn($v) => !is_null($v));

        // Handle auto_attendence separately so value 0 is NOT removed by array_filter
        if ($request->has('auto_attendence')) {
            $userUpdateData['auto_attendence'] = (int) $request->auto_attendence;
        }

        // Attach uploaded image path if present
        if (isset($data['image'])) {
            $userUpdateData['image'] = $data['image'];
        }

        // ✅ Update user profile
        if ($isEdit != 1) {
            $userUpdateData['step'] = 2;
        }
        $user->update($userUpdateData);

        // ✅ Merge Work Info logic
        $workValidated = $request->validate([
            'primary_role' => 'nullable|string|max:255',
            'skills' => 'nullable',
            'languages_spoken' => 'nullable',
            'total_experience' => 'nullable',
            'education' => 'nullable|string|max:255',
            'additional_info' => 'nullable',
            'voice_note' => 'nullable|file|max:10240',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_number' => 'nullable|string|max:255',
            'relation' => 'nullable|string|max:255',
        ]);
        $workInfo = UserWorkInfo::where('user_id', $user->id)->first();

        // Update household information
        $householdData = array_filter([
            'residence_type'     => $request->residence_type,
            'number_of_rooms'    => $request->number_of_rooms,
            'languages_spoken'   => $request->languages_spoken,
            'adults_count'       => $request->adults_count,
            'children_count'     => $request->children_count,
            'elderly_count'      => $request->elderly_count,
            'special_requirements' => $request->special_requirements,
        ], fn($v) => !is_null($v));
        if (!empty($householdData)) {
            UserHouseholdInformation::updateOrCreate(['user_id' => $user->id], $householdData);
        }

        // Update pet details
        if ($request->has('pet_details') && is_array($request->pet_details)) {
            \App\Models\UserPetDetail::where('user_id', $user->id)->delete();
            foreach ($request->pet_details as $pet) {
                if (!empty($pet['pet_type'])) {
                    $petCount = $pet['pet_count'] ?? null;
                    \App\Models\UserPetDetail::create([
                        'user_id'   => $user->id,
                        'pet_type'  => $pet['pet_type'],
                        'pet_count' => $petCount !== null && $petCount !== '' ? $petCount : null,
                    ]);
                }
            }
        }

        // Update addresses (with per-address household + pets)
        if ($request->has('addresses') && is_array($request->addresses)) {
            \App\Models\UserAddress::where('user_id', $user->id)->delete();
            foreach ($request->addresses as $addr) {
                if (!empty(array_filter($addr))) {
                    $newAddress = \App\Models\UserAddress::create([
                        'user_id'        => $user->id,
                        'name'           => $addr['title'] ?? $addr['name'] ?? '',
                        'street'         => $addr['street'] ?? '',
                        'city'           => $addr['city'] ?? '',
                        'state'          => $addr['state'] ?? '',
                        'pincode'        => $addr['pincode'] ?? '',
                        'area_locality'  => $addr['area_locality'] ?? '',
                        'google_location'=> $addr['google_location'] ?? '',
                        'latitude'       => $addr['lat'] ?? $addr['latitude'] ?? null,
                        'longitude'      => $addr['long'] ?? $addr['longitude'] ?? null,
                    ]);

                    // Parse per-address household info
                    $hhRaw = $addr['household'] ?? null;
                    if (is_string($hhRaw)) {
                        $hhRaw = json_decode($hhRaw, true) ?: null;
                    }
                    if (!empty($hhRaw) && is_array($hhRaw)) {
                        UserHouseholdInformation::create([
                            'user_id'              => $user->id,
                            'address_id'           => $newAddress->id,
                            'residence_type'       => $hhRaw['residence_type'] ?? null,
                            'number_of_rooms'      => $hhRaw['number_of_rooms'] ?? null,
                            'languages_spoken'     => $hhRaw['languages_spoken'] ?? [],
                            'adults_count'         => $hhRaw['adults_count'] ?? null,
                            'children_count'       => $hhRaw['children_count'] ?? null,
                            'elderly_count'        => $hhRaw['elderly_count'] ?? null,
                            'special_requirements' => $hhRaw['special_requirements'] ?? null,
                        ]);
                    }

                    // Parse per-address pets
                    $petsRaw = $addr['pets'] ?? null;
                    if (is_string($petsRaw)) {
                        $petsRaw = json_decode($petsRaw, true) ?: null;
                    }
                    if (!empty($petsRaw) && is_array($petsRaw)) {
                        foreach ($petsRaw as $pet) {
                            if (!empty($pet['pet_type'])) {
                                $petCount = $pet['pet_count'] ?? null;
                                \App\Models\UserPetDetail::create([
                                    'user_id'   => $user->id,
                                    'address_id'=> $newAddress->id,
                                    'pet_type'  => $pet['pet_type'],
                                    'pet_count' => $petCount !== null && $petCount !== '' ? $petCount : null,
                                ]);
                            }
                        }
                    }
                }
            }
        }

        $skills = $request->input('skills');
        if (is_string($skills)) {
            $decoded = json_decode($skills, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $skills = $decoded;
            } else {
                $skills = array_map('trim', explode(',', $skills));
            }
        }

        $languages = $request->input('languages_spoken');
        if (is_string($languages)) {
            $decoded = json_decode($languages, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $languages = $decoded;
            } else {
                $languages = array_map('trim', explode(',', $languages));
            }
        }

        $totalExperience = $request->input('total_experience');
        if ($totalExperience !== null) {
            if (preg_match('/[0-9]+(\.[0-9]+)?/', $totalExperience, $matches)) {
                $totalExperience = (float)$matches[0];
            } else {
                $totalExperience = null;
            }
        }

        $workData = [
            'primary_role' => $workValidated['primary_role'] ?? null,
            'skills' => $skills ?? [],
            'languages_spoken' => $languages ?? null,
            'total_experience' => $totalExperience ?? null,
            'education' => $workValidated['education'] ?? null,
            'additional_info' => $workValidated['additional_info'] ?? null,
            'emergency_contact_name' => $workValidated['emergency_contact_name'] ?? null,
            'emergency_contact_number' => $workValidated['emergency_contact_number'] ?? null,
        ];

        $data = $validator->validated();
         $jsonResponse = json_encode($data, JSON_PRETTY_PRINT);

    // File path inside storage folder
    $filePath = storage_path('logs/sss.txt');

    // Open the file for appending (creates file if not exists)
    $file = fopen($filePath, 'a');

    if ($file) {
        fwrite($file, "==== " . date('Y-m-d H:i:s') . " ====\n");
        fwrite($file, $jsonResponse . "\n\n");
        fclose($file);
    } else {
        // Handle error if file couldn't be opened
        \Log::error('Could not open log file for writing.');
    }
        if ($request->hasFile('voice_note')) {
            $directory = "uploads/user_voice_notes";
            // if (!file_exists(public_path($directory))) mkdir(public_path($directory), 0755, true);
            // $file = $request->file('voice_note');
            // $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            // $file->move(public_path($directory), $fileName);
            // $path = $directory . '/' . $fileName;
            // if ($workInfo && $workInfo->voice_note && file_exists(public_path($workInfo->voice_note))) unlink(public_path($workInfo->voice_note));
            $path = $this->uploadCloudary($request,"voice_note",$directory);
            $workData['voice_note'] = $path;
        }

        UserWorkInfo::updateOrCreate(['user_id' => $user->id], $workData);

        // ✅ Merge Last Work Experience
        $expValidated = $request->validate([
            'id' => 'nullable',
            'role' => 'nullable',
            'join_date' => 'nullable',
            'end_date' => 'nullable',
            'salary' => 'nullable',
            'working_hours' => 'nullable',
            'house_sold' => 'nullable',
            'owner_name' => 'nullable',
            'contact_number' => 'nullable',
            'state' => 'nullable',
            'city' => 'nullable',
        ]);

        $expData = [
            'user_id' => $user->id,
            'role' => $expValidated['role'] ?? null,
            'join_date' => $expValidated['join_date'] ?? null,
            'end_date' => $expValidated['end_date'] ?? null,
            'salary' => $expValidated['salary'] ?? null,
            'working_hours' => $expValidated['working_hours'] ?? null,
            'house_sold' => $expValidated['house_sold'] ?? 0,
            'owner_name' => $expValidated['owner_name'] ?? null,
            'contact_number' => $expValidated['contact_number'] ?? null,
            'state' => $expValidated['state'] ?? null,
            'city' => $expValidated['city'] ?? null,
        ];

        LastWorkExperience::updateOrCreate(['id' => $expValidated['id'] ?? null, 'user_id' => $user->id], $expData);

        // Calculate and set final step based on data provided (only if not in edit mode)
        if ($isEdit != 1) {
            $finalStep = 2; // Default: basic info
            
            // Check what data was provided and set appropriate step
            if ($request->has('addresses') && !empty($request->addresses)) {
                $finalStep = 3;
            }
            
            if ($request->hasAny(['residence_type', 'number_of_rooms', 'adults_count', 'children_count', 'elderly_count', 'special_requirements'])) {
                $finalStep = 4;
            }
            
            if ($request->has('pet_details') && !empty($request->pet_details)) {
                $finalStep = 4;
            }
            
            // Work experience is the highest step
            if ($request->has('primary_role') || $request->has('skills') || $request->has('total_experience')) {
                $finalStep = 6;
            }
            
            $user->update(['step' => $finalStep]);
        }

        return response()->json([
            'success' => true,
            'message' => $isEdit == 1 ? 'Profile edited successfully' : 'Profile updated successfully',
            'data' => $user->fresh(['addresses', 'householdInformation', 'petDetails', 'userWorkInfo', 'kycInformation'])
        ]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => 'Failed to update profile', 'error' => $e->getMessage()], 500);
    }
}


public function categoryList(Request $request){
    try {
        // Only return top-level roles (parent_id = null); skills are fetched via listSubcategories
        $category = Category::where('is_deleted', 0)->whereNull('parent_id')->get();
        return response()->json([
            'success' => true,
            'message' => 'Category Fetch successfully',
            'data' => $category
        ], 200);
    } catch (\Exception $e) {
        \Log::error('Category List Error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch categories',
            'error' => $e->getMessage()
        ], 500);
    }
}


public function getCmsData(Request $request)
{
    $query = DB::table('cms');

    // If slug is provided, apply filter
    if ($request->has('slug') && !empty($request->slug)) {
        $query->where('slug', $request->slug);
    }

    $cmsData = $query->get();

    if ($cmsData->isEmpty()) {
        return response()->json([
            'success' => false,
            'message' => 'No CMS data found for the given slug',
            'data'    => []
        ], 404);
    }

    return response()->json([
        'success' => true,
        'message' => 'CMS data fetched successfully',
        'data'    => $cmsData
    ], 200);
}


public function getSubscriptionList(Request $request)
{
    $cmsObj = DB::table('subscriptions')->where('type','vendor')->get();
    return response()->json([
        'success' => true,
        'message' => 'Subscription data fetch successfully',
        'data' => $cmsObj
    ], 200);
}

public function completeBusinessProfile(Request $request)
{
    try {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'photo_verification' => 'required|file|mimes:jpg,jpeg,png|max:2048',
            'business_proof' => 'required|file|max:2048',
            'adhaar_card_verification' => 'required|file|max:2048',
            'pan_card' => 'required|file|max:2048',
            'business_description' => 'required|string',
            'years_of_experience' => 'required|integer|min:0',
            'exact_location' => 'required|string',
            'business_website' => 'nullable|url',
            'gstin_number' => 'nullable|string',
                        'portfolio_images.*' => 'nullable|file|max:2048', // multiple files
                        'lat' => 'nullable',
                        'long' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        };
        if($request->user_id){
        $user = User::find($request->user_id);
        }
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found'
            ], 404);
        }

        $updateData = [
            'business_description' => $request->business_description,
            'years_of_experience' => $request->years_of_experience,
            'exact_location' => $request->exact_location,
            'business_website' => $request->business_website,
            'gstin_number' => $request->gstin_number,
            'step' => '2',
            'lat' => $request->lat,
            'long' => $request->long,
        ];

        // Handle photo verification upload
        if ($request->hasFile('photo_verification')) {
            $directory = 'uploads/users/verification';
            // $image = $request->file('photo_verification');
            
            
            // if (!file_exists(public_path($directory))) {
            //     mkdir(public_path($directory), 0755, true);
            // }
            
            // $fileName = 'photo_verification_' . time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            // $image->move(public_path($directory), $fileName);
            $path = $this->uploadCloudary($request,"photo_verification",$directory);
            $updateData['photo_verification'] = $path;
        }

        // Handle business proof upload
        if ($request->hasFile('business_proof')) {
            $directory = 'uploads/users/verification';
            // $image = $request->file('business_proof');
            
            
            // if (!file_exists(public_path($directory))) {
            //     mkdir(public_path($directory), 0755, true);
            // }
            
            // $fileName = 'business_proof_' . time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            // $image->move(public_path($directory), $fileName);
            $path = $this->uploadCloudary($request,"business_proof",$directory);
            $updateData['business_proof'] = $path;
        }

        // Handle adhaar card verification upload
        if ($request->hasFile('adhaar_card_verification')) {
            $directory = 'uploads/users/verification';
            
            // $image = $request->file('adhaar_card_verification');
            
            // if (!file_exists(public_path($directory))) {
            //     mkdir(public_path($directory), 0755, true);
            // }
            
            // $fileName = 'adhaar_verification_' . time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            // $image->move(public_path($directory), $fileName);
            $path = $this->uploadCloudary($request,"adhaar_card_verification",$directory);
            $updateData['adhaar_card_verification'] = $path;
        }

        // Handle PAN card upload
        if ($request->hasFile('pan_card')) {
            $directory = 'uploads/users/verification';
            
            // $image = $request->file('pan_card');
            
            // if (!file_exists(public_path($directory))) {
            //     mkdir(public_path($directory), 0755, true);
            // }
            
            // $fileName = 'pan_card_' . time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            // $image->move(public_path($directory), $fileName);
            $path = $this->uploadCloudary($request,"pan_card",$directory);
            $updateData['pan_card'] = $path;
        }

        $user->update($updateData);
   if ($request->hasFile('portfolio_images')) {
            $portfolioDir = 'uploads/users/portfolio';

            if (!file_exists(public_path($portfolioDir))) {
                mkdir(public_path($portfolioDir), 0755, true);
            }

            foreach ($request->file('portfolio_images') as $file) {
                // $fileName = 'portfolio_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                // $file->move(public_path($portfolioDir), $fileName);
                $path = $this->uploadCloudary($request,"portfolio_images",$portfolioDir);
                PortfolioImage::create([
                    'user_id' => $user->id,
                    'image' => $path
                ]);
            }
        }

        // Fetch user with portfolio images
        $portfolioImages = PortfolioImage::where('user_id', $user->id)->get();
        return response()->json([
            'status' => true,
            'message' => 'Business profile completed successfully',
            'data' => $user,
            'portfolio_images' => $portfolioImages, 
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ], 500);
    }
}

public function setBusinessAvailability(Request $request)
{
    try {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'working_days' => 'required|array',
            'working_days.*' => 'string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'daily_start_time' => 'required|date_format:H:i',
            'daily_end_time' => 'required|date_format:H:i|after:daily_start_time',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }

        $user = User::find($request->user_id);
        
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found'
            ], 404);
        }

        $user->update([
            'working_days' => $request->working_days,
            'daily_start_time' => $request->daily_start_time,
            'daily_end_time' => $request->daily_end_time,
            'step' => '3'
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Business availability set successfully',
            'data' => $user
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ], 500);
    }
}

public function notificationAdd(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'status' => 'nullable|string'
        ]);

        // $userId = Auth::id(); // when authentication is used
        $notification = Notification::create([
            'user_id' => 1, // replace with $userId in real case
            'title' => $request->title,
            'message' => $request->message,
            'status' => $request->status ?? 'unread',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Notification added successfully',
            'data' => $notification
        ]);
    }

    public function notificationList(Request $request)
    {
        $userId = Auth::user()->id;
        $notifications = Notification::where('user_id', $userId) // replace with $userId
            ->orderBy('created_at', 'desc')
            ->get();

        $notifications->transform(function ($notification) use ($userId) {
            if (($notification->type ?? '') === 'job_application' && empty($notification->job_id)) {
                $message = (string) ($notification->message ?? '');
                $parts = preg_split('/has applied for the job:\s*/i', $message);
                $jobTitle = isset($parts[1]) ? trim($parts[1]) : '';

                if ($jobTitle !== '') {
                    $matchedJob = \App\Models\Job::where('created_by', $userId)
                        ->where('title', $jobTitle)
                        ->latest('id')
                        ->first();

                    if ($matchedJob) {
                        $notification->job_id = $matchedJob->id;
                    }
                }
            }

            return $notification;
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Notifications retrieved successfully',
            'data' => $notifications
        ]);
    }

    public function notificationUnreadCount(Request $request)
    {
        $userId = Auth::user()->id;
        $count = Notification::where('user_id', $userId)
            ->where('status', 'unread')
            ->count();

        return response()->json([
            'status' => 'success',
            'message' => 'Unread notifications count retrieved successfully',
            'unread_count' => $count
        ]);
    }

    public function notificationMarkAsReadPost(Request $request)
    {
        $request->validate([
            'notification_id' => 'required|exists:notifications,id'
        ]);

        $notification = Notification::where('id', $request->notification_id)
            ->where('user_id', Auth::user()->id)
            ->firstOrFail();

        $notification->update([
            'status' => 'read',
            'read_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Notification marked as read',
            'data' => $notification
        ]);
    }

    public function notificationMarkAsRead($id)
    {
        $notification = Notification::findOrFail($id);
        $notification->update([
            'status' => 'read',
            'read_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Notification marked as read',
            'data' => $notification
        ]);
    }

    public function updateDeviceToken(Request $request)
    {
        $request->validate([
            'device_token' => 'required|string',
            'device_type' => 'nullable|string|in:android,ios,web',
        ]);

        if (!\Schema::hasTable('user_device_tokens')) {
            \Log::warning('user_device_tokens table missing; skipped device token update');
            return response()->json([
                'status' => 'success',
                'message' => 'Device token skipped until database migration runs',
            ]);
        }

        $userId = Auth::guard('api')->user()->id;

        \App\Models\UserDeviceToken::updateOrCreate(
            ['user_id' => $userId],
            [
                'user_id'      => $userId,
                'device_token' => $request->device_token,
                'device_type'  => $request->device_type ?? 'android',
                'device_id'    => $request->device_id ?? '',
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Device token updated successfully',
        ]);
    }

    public function readAll(Request $request)
{
    $request->validate([
        'type' => 'required|in:is_single_read,is_all_read',
        'id'   => 'required_if:type,is_single_read|exists:notifications,id'
    ]);

    $userId = Auth::guard('api')->user()->id;

    if ($request->type === 'is_single_read') {
        // Mark a single notification as read
        $notification = Notification::where('id', $request->id)
            ->where('user_id', $userId)
            ->first();

        if ($notification) {
            $notification->update([
                'status' => 'read',
                'read_at' => now(),
            ]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Notification marked as read',
        ]);
    }

    if ($request->type === 'is_all_read') {
        // Mark all notifications for the user as read
        Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->update([
                'status' => 'read',
                'read_at' => now(),
            ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'All notifications marked as read',
        ]);
    }
}

public function serviceCategoryList(Request $request){
    $list = Category::all();
     return response()->json([
            'status'  => 'success',
            'message' => 'All Category List Fatch',
            'data' => $list,
        ]);
}

public function orderList(Request $request){
        $userId = Auth::guard('api')->user()->id;
    $list = Order::with('user','service')->where('user_id',$userId)->get();
     return response()->json([
            'status'  => 'success',
            'message' => 'All Order List Fatch',
            'data' => $list,
        ]);
}

public function userList(Request $request)
{
    try {
        $users = User::where('user_role_id', 2)
            ->with(['portfolioImages']) // eager load portfolio images
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'User list fetched successfully',
            'data' => $users
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ], 500);
    }
}

public function vendorList(Request $request)
{
    
    try {
        $users = User::where('user_role_id', 1)
            ->with(['portfolioImages']) // eager load portfolio images
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'User list fetched successfully',
            'data' => $users
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ], 500);
    }
}

public function socialLoginCallback(Request $request)
	{
		$social_user = $request->data;
		$provider 	 = $request->provider;
		$language_id                       =   $this->current_language_id() ?? 1;
	    if (!empty($social_user['email'])) {
        $existingUser = User::where('email', $social_user['email'])
                          ->where('is_deleted', 0)
                          ->first();

        if ($existingUser) {
            $requestedRole = $social_user['role'] ?? 2;
            $existingRole = $existingUser->user_role_id;
            if ($existingRole != $requestedRole) {
                $roleName = $existingRole == 1 ? 'vendor' : 'customer';
                $response['status'] = 'error';
                $response["msg"] = "You already have a $roleName account with this email. Please delete it first to login as a " . ($requestedRole == 1 ? 'vendor' : 'user');
                $response['data'] = (object) [];
                return response()->json($response);
            }
        }
    }

		if($provider == 'apple'){
			$dataArr = [
				'name'        	=> $social_user['user'],
				'social_type' 	=> $provider,
				'social_id'   	=> $social_user['id'],
				'user_role_id'	=> 2,
				'is_active'   	=> 1,
				'language'   	=> $language_id,
				// 'is_approved' 	=> 1,
				'is_verified' 	=> 1,
                'url_image'     => $social_user['image'],
			];
			
			$user = User::firstOrCreate($dataArr);
		}else{
			if (!empty($social_user) && $social_user['email']) {
				$condition = ['email' => $social_user['email'], 'is_deleted' => 0];
			} else {
				$condition = ['social_type' => $provider, 'social_id' => $social_user['id'], 'is_deleted' => 0,'user_role_id'=>2];
			}
			$user = User::firstOrNew($condition);
			$user->name 			= $social_user['name'];
			$user->first_name 		= $social_user['first_name'] ?? '';
			$user->last_name 		= $social_user['last_name'] ?? '';
                           $user->url_image     = $social_user['image'];
			$user->language 		= $language_id;
			$user->social_type 	= $provider;
			$user->social_id 		= $social_user['id'];
			$user->user_role_id 	= 2;
			$user->is_active 		= 1;
			// $user->is_approved 	= 1;
			$user->is_verified 		= 1;
			$user->save();
		}
	
		if ($user) {
			if ($user->image) {
				$user->image = $user->image ?? '';
			}
			$user_data = $user->only(['id',
				'user_role_id',
				'name',
				'first_name',
				'last_name',
				'email',
				'url_image',
				'address',
				'gender',
				'dob',
                'step',
				'is_approved',
				'is_verified',
				'is_active',
				'government_id',
				'emergency_contact',
				'image'
			]);
			$response['token'] = $user->createToken('authToken')->plainTextToken;
			$response['status'] = 'success';
			$response["msg"]	= "Sign Up successfully";
			$response['data'] = $user_data;
		} else {
			$response['status'] = 'success';
			$response["msg"]	= "Something went worng";
			$response['data'] = (object) [];
		}
		return response()->json($response);
	}

 public function categoryDetails(Request $request, $id)
{
    $category = Category::find($id);

    if (!$category) {
        return response()->json([
            'status'  => false,
            'message' => 'Category not found.',
            'data'    => null
        ], 404);
    }

    // Fetch services and subservices for this category
    $services = Service::where('category_id', $id)->get();
    $subServices = SubService::where('category_id', $id)->get();

    $responseData = [
        'category'    => $category,
        'services'    => $services,
        'subServices' => $subServices,
    ];

    return response()->json([
        'status'  => true,
        'message' => 'Category details fetched successfully.',
        'data'    => $responseData
    ], 200);
}

public function vendorDetails(Request $request,$id){
    $user = User::with('subServices','services','category')->find($id);
    return response()->json([
        'status'  => true,
        'message' => 'Shop details fetched successfully.',
        'data'    => $user
    ], 200);
}

public function vendorListAuth(Request $request)
{
    $category = $request->service_category;
    $name = $request->name;
    $perPage = $request->per_page ?? 10; // Number of items per page, default 10

    try {
        $query = User::where('user_role_id', 1)
            ->when($category, function ($query, $category) {
                return $query->where('service_category', $category);
            })
            ->when($name, function ($query, $name) {
                return $query->where('name', 'like', "%{$name}%");
            })
            ->with(['portfolioImages', 'subServices', 'services', 'category']);
if ($request->has('lat') && $request->has('long')) {
    $latitude = $request->lat;
    $longitude = $request->long;
    $radius = 500; 

    $query->selectRaw("users.*, 
        (6371 * acos(cos(radians(?)) * cos(radians(lat)) 
        * cos(radians(`long`) - radians(?)) 
        + sin(radians(?)) * sin(radians(lat)))) AS distance", 
        [$latitude, $longitude, $latitude])
        ->having("distance", "<=", $radius)
        ->orderBy("distance", "asc");
}

        $users = $query->paginate($perPage);

        // Add wishlist_status to each vendor
        $users->getCollection()->transform(function ($user) {
            $wishlist = Wishlist::where('vendorId', $user->id)->exists();
            $user->wishlist_status = $wishlist ? 1 : 0;
            return $user;
        });

        return response()->json([
            'status'  => true,
            'message' => 'Shop list fetched successfully',
            'data'    => $users
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status'  => false,
            'message' => 'Server error: ' . $e->getMessage()
        ], 500);
    }
}

public function appointmentList(Request $request)
{
    try {
        $userId = Auth::guard('api')->user()->id;
        $list = Booking::with(['customer', 'vendor', 'service'])
            ->where('vendor_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status'  => true,
            'message' => $list->isEmpty() ? 'No appointments found' : 'Appointments fetched successfully',
            'data'    => $list
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status'  => false,
            'message' => 'Something went wrong',
            'error'   => $e->getMessage()
        ], 500);
    }
}


// public function saveAadharAndSendOtp(Request $request)
// {
//     try {
//         $user = User::find($request->user_id) ?? Auth::guard('api')->user();
//         $request->validate([
//             'aadhar_number' => 'required|digits:12',
//         ]);
//         if($request->is_staff_add == 1){
//             $user->aadhar__verify_otp = '123456';
//             $user->aadhar_number_otp_expire_at = Carbon::now()->addMinutes(10);
//             $user->save();

//             return response()->json([
//                 'status' => true,
//                 'message' => 'OTP resent successfully',
//             ], 200);
//         }else{
//             if (!empty($user->aadhar_number)) {
//                 if ($user->aadhar_number !== $request->aadhar_number) {
//                     return response()->json([
//                         'status' => false,
//                         'message' => 'Aadhaar number cannot be changed once saved.'
//                     ], 400);
//                 }
//                 $user->aadhar__verify_otp = '123456';
//                 $user->aadhar_number_otp_expire_at = Carbon::now()->addMinutes(10);
//                 $user->save();
    
//                 return response()->json([
//                     'status' => true,
//                     'message' => 'OTP resent successfully',
//                 ], 200);
//             }
//         }
       
//         $exists = User::where('aadhar_number', $request->aadhar_number)
//             ->where('id', '!=', $user->id)
//             ->exists();

//         if ($exists) {
//             return response()->json([
//                 'status' => false,
//                 'message' => 'Aadhaar number already registered with another user.'
//             ], 422);
//         }
//         $user->aadhar_number = $request->aadhar_number;
//         $user->aadhar__verify_otp = '123456';
//         $user->aadhar_number_otp_expire_at = Carbon::now()->addMinutes(10);
//         $user->aadhar__verify = false;
//         $user->aadhar__verify_at = null;
//         $user->save();

//         return response()->json([
//             'status' => true,
//             'message' => 'Aadhaar number saved and OTP sent successfully',
//             'data' => [
//                 'aadhar_number' => $user->aadhar_number,
//             ]
//         ], 200);

//     } catch (\Illuminate\Validation\ValidationException $e) {
//         return response()->json([
//             'status' => false,
//             'message' => 'Validation failed',
//             'errors' => $e->errors()
//         ], 422);

//     } catch (\Exception $e) {
//         return response()->json([
//             'status' => false,
//             'message' => 'Failed to save or send Aadhaar OTP',
//             'error' => $e->getMessage()
//         ], 500);
//     }
// }

// public function saveAadharAndSendOtp(Request $request)
// {
//     try {
//         $user = User::find($request->user_id) ?? Auth::guard('api')->user();

//         $request->validate([
//             'aadhar_number' => 'required|digits:12',
//         ]);

//         // =============================
//         // CASE 1: STAFF ADDING NEW STAFF
//         // =============================
//         if ($request->is_staff_add == 1) {

//             // Check if Aadhaar already belongs to someone → return details
//             $existingUser = User::where('aadhar_number', $request->aadhar_number)->first();
//             if ($user->aadhar_number == $request->aadhar_number) {
//                         return response()->json([
//                             'status' => false,
//         'message' => 'You cannot add this staff member because the Aadhaar number matches your own.'
//                         ], 400);
//                 }
//             if ($existingUser) {
//                 return response()->json([
//                     'status' => true,
//                     'message' => 'Aadhaar already registered. Existing user details fetched.',
//                     'data' => [
//                         'user_id' => $existingUser->id,
//                         'name'    => $existingUser->name,
//                         'phone'   => $existingUser->phone,
//                         'email'   => $existingUser->email,
//                         'address' => $existingUser->address,
//                         'city'    => $existingUser->city,
//                         'state'   => $existingUser->state,
//                         'country' => $existingUser->country,
//                     ]
//                 ], 200);
//             }

//             // Aadhaar NOT registered → send OTP, create record for staff
//             $user->aadhar_number = $request->aadhar_number;
//             $user->aadhar__verify_otp = '123456';
//             $user->aadhar_number_otp_expire_at = Carbon::now()->addMinutes(10);
//             $user->aadhar__verify = false;
//             $user->save();

//             return response()->json([
//                 'status' => true,
//                 'message' => 'New staff Aadhaar saved & OTP sent',
//             ], 200);
//         }


//         // ==========================================
//         // CASE 2: NORMAL USER — Aadhaar cannot change
//         // ==========================================
//         if (!empty($user->aadhar_number)) {

//             // User already has Aadhaar saved, cannot change it
//             if ($user->aadhar_number !== $request->aadhar_number) {
//                 return response()->json([
//                     'status' => false,
//                     'message' => 'Aadhaar number cannot be changed once saved.'
//                 ], 400);
//             }

//             // Resend OTP only
//             $user->aadhar__verify_otp = '123456';
//             $user->aadhar_number_otp_expire_at = Carbon::now()->addMinutes(10);
//             $user->save();

//             return response()->json([
//                 'status' => true,
//                 'message' => 'OTP resent successfully',
//             ], 200);
//         }


//         // ==========================================
//         // CHECK IF THIS AADHAAR BELONGS TO ANOTHER USER
//         // ==========================================
//         $existingUser = User::where('aadhar_number', $request->aadhar_number)
//             ->where('id', '!=', $user->id)
//             ->first();

//         if ($existingUser) {
//             return response()->json([
//                 'status' => true,
//                 'message' => 'Aadhaar already registered. Existing user details fetched.',
//                 'data' => [
//                     'user_id' => $existingUser->id,
//                     'name'    => $existingUser->name,
//                     'phone'   => $existingUser->phone,
//                     'email'   => $existingUser->email,
//                     'address' => $existingUser->address,
//                     'city'    => $existingUser->city,
//                     'state'   => $existingUser->state,
//                     'country' => $existingUser->country,
//                 ]
//             ], 200);
//         }


//         // ==========================================
//         // SAVE NEW AADHAAR FOR CURRENT USER
//         // ==========================================
//         $user->aadhar_number      = $request->aadhar_number;
//         $user->aadhar__verify_otp = '123456';
//         $user->aadhar_number_otp_expire_at = Carbon::now()->addMinutes(10);
//         $user->aadhar__verify     = false;
//         $user->aadhar__verify_at  = null;
//         $user->save();

//         return response()->json([
//             'status' => true,
//             'message' => 'Aadhaar number saved and OTP sent successfully',
//             'data' => [
//                 'aadhar_number' => $user->aadhar_number,
//             ]
//         ], 200);


//     } catch (\Illuminate\Validation\ValidationException $e) {
//         return response()->json([
//             'status' => false,
//             'message' => 'Validation failed',
//             'errors' => $e->errors()
//         ], 422);

//     } catch (\Exception $e) {
//         return response()->json([
//             'status' => false,
//             'message' => 'Failed to save or send Aadhaar OTP',
//             'error' => $e->getMessage()
//         ], 500);
//     }
// }



public function saveAadharAndSendOtp(Request $request)
{
    try {
        $authUser = Auth::guard('api')->user();
        $user = User::find($request->user_id) ?? $authUser;

        $request->validate([
            'aadhar_number' => 'required|digits:12',
        ]);

        $aadhaarService = new \App\Services\Admin\AadhaarVerificationService();

        // =============================
        // CASE 1: STAFF ADDING NEW STAFF
        // =============================
        if ($request->is_staff_add == 1) {

            // Check if Aadhaar already belongs to someone → return details
            $existingUser = User::where('aadhar_number', $request->aadhar_number)->first();
            
            if ($authUser->aadhar_number == $request->aadhar_number) {
                return response()->json([
                    'status' => false,
                    'message' => 'You cannot add this staff member because the Aadhaar number matches your own.'
                ], 400);
            }
            
            if ($existingUser) {
                // Send real OTP via API
                $otpResult = $aadhaarService->sendOtp($request->aadhar_number);
                
                if ($otpResult['success']) {
                    $existingUser->aadhar_reference_id = $otpResult['reference_id'];
                    $existingUser->aadhar_number_otp_expire_at = Carbon::now()->addMinutes(10);
                    $existingUser->save();
                    
                    return response()->json([
                        'status' => true,
                        'message' => 'Aadhaar already registered. OTP sent to registered mobile.',
                        'reference_id' => $otpResult['reference_id'],
                        'data' => User::with(['addresses','petDetails','lastExp','householdInformation','kycInformation','userWorkInfo','addedByUser', 'addedByUser.addresses',
    'addedByUser.petDetails',
    'addedByUser.lastExp',
    'addedByUser.householdInformation',
    'addedByUser.kycInformation',
    'addedByUser.userWorkInfo'])->find($existingUser->id),
                    ], 200);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => $otpResult['message'] ?? 'Failed to send OTP'
                    ], 400);
                }
            }

            // ==========================================
            // Aadhaar NOT registered → Send OTP first
            // ==========================================
            $otpResult = $aadhaarService->sendOtp($request->aadhar_number);
            
            if (!$otpResult['success']) {
                return response()->json([
                    'status' => false,
                    'message' => $otpResult['message'] ?? 'Failed to send OTP'
                ], 400);
            }
            
            $newUser = new User();
            $newUser->name = 'Staff Member';
            $newUser->aadhar_number = $request->aadhar_number;
            $newUser->aadhar_reference_id = $otpResult['reference_id'];
            $newUser->aadhar_number_otp_expire_at = Carbon::now()->addMinutes(10);
            $newUser->aadhar__verify = false;
            $newUser->is_staff_added = 1; // Mark as staff added
            $newUser->step = 4;
            $newUser->user_role_id = 2;
            $newUser->save();

            return response()->json([
                'status' => true,
                'message' => 'OTP sent to Aadhaar registered mobile number',
                'reference_id' => $otpResult['reference_id'],
                'data' => [
                    'user_id' => $newUser->id,
                    'aadhar_number' => $newUser->aadhar_number,
                ]
            ], 200);
        }
        
        

        // ==========================================
        // CASE 2: NORMAL USER — Aadhaar cannot change
        // ==========================================
        if (!empty($user->aadhar_number)) {

            // User already has Aadhaar saved, cannot change it
            if ($user->aadhar_number !== $request->aadhar_number) {
                return response()->json([
                    'status' => false,
                    'message' => 'Aadhaar number cannot be changed once saved.'
                ], 400);
            }

            // Resend OTP via real API
            $otpResult = $aadhaarService->sendOtp($request->aadhar_number);
            
            if (!$otpResult['success']) {
                return response()->json([
                    'status' => false,
                    'message' => $otpResult['message'] ?? 'Failed to send OTP'
                ], 400);
            }
            
            $user->aadhar_reference_id = $otpResult['reference_id'];
            $user->aadhar_number_otp_expire_at = Carbon::now()->addMinutes(10);
            $user->save();

            return response()->json([
                'status' => true,
                'message' => 'OTP sent successfully',
                'reference_id' => $otpResult['reference_id'],
            ], 200);
        }


        // ==========================================
        // CHECK IF THIS AADHAAR BELONGS TO ANOTHER USER
        // ==========================================
        $existingUser = User::where('aadhar_number', $request->aadhar_number)
            ->where('id', '!=', $user->id)
            ->first();

        if ($existingUser) {
            // Send OTP for existing user
            $otpResult = $aadhaarService->sendOtp($request->aadhar_number);
            
            if ($otpResult['success']) {
                $existingUser->aadhar_reference_id = $otpResult['reference_id'];
                $existingUser->aadhar_number_otp_expire_at = Carbon::now()->addMinutes(10);
                $existingUser->save();
            }
            
            return response()->json([
                'status' => true,
                'message' => 'Aadhaar already registered. OTP sent.',
                'reference_id' => $otpResult['reference_id'] ?? null,
                'data' => User::with(['addresses','petDetails','lastExp','householdInformation','kycInformation','userWorkInfo','addedByUser', 'addedByUser.addresses',
    'addedByUser.petDetails',
    'addedByUser.lastExp',
    'addedByUser.householdInformation',
    'addedByUser.kycInformation',
    'addedByUser.userWorkInfo'])->find($existingUser->id),
            ], 200);
        }


        // ==========================================
        // SAVE NEW AADHAAR FOR CURRENT USER
        // ==========================================
        $otpResult = $aadhaarService->sendOtp($request->aadhar_number);
        
        if (!$otpResult['success']) {
            return response()->json([
                'status' => false,
                'message' => $otpResult['message'] ?? 'Failed to send OTP'
            ], 400);
        }
        
        $user->aadhar_number      = $request->aadhar_number;
        $user->aadhar_reference_id = $otpResult['reference_id'];
        $user->aadhar_number_otp_expire_at = Carbon::now()->addMinutes(10);
        $user->aadhar__verify     = false;
        $user->aadhar__verify_at  = null;
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'OTP sent to Aadhaar registered mobile number',
            'reference_id' => $otpResult['reference_id'],
            'data' => [
                'aadhar_number' => $user->aadhar_number,
            ]
        ], 200);


    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'status' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);

    } catch (\Exception $e) {
        \Log::error('Aadhaar Save Error: ' . $e->getMessage());
        return response()->json([
            'status' => false,
            'message' => 'Failed to save or send Aadhaar OTP',
            'error' => $e->getMessage()
        ], 500);
    }
}





    /**
     * Verify Aadhar OTP
     */
    public function verifyAadharOtp(Request $request)
    {
        try {
            $authUser = Auth::guard('api')->user();

            $request->validate([
                'otp' => 'required|digits:6'
            ]);

            // If user_id sent (House Owner verifying staff), use that user's record
            // Otherwise verify the logged-in user's own Aadhaar
            if ($request->has('user_id') && $request->user_id) {
                $user = User::find($request->user_id);
                if (!$user) {
                    return response()->json(['status' => false, 'message' => 'Staff user not found.'], 404);
                }
            } else {
                $user = $authUser;
            }

            if (!$user->aadhar_number) {
                return response()->json([
                    'status' => false,
                    'message' => 'Aadhar number not found. Please save Aadhar number first.'
                ], 400);
            }

            if ($user->aadhar__verify) {
                return response()->json([
                    'status' => false,
                    'message' => 'Aadhar number is already verified'
                ], 400);
            }

            // Use real API if reference_id exists
            if ($user->aadhar_reference_id) {
                $aadhaarService = new \App\Services\Admin\AadhaarVerificationService();
                $verifyResult = $aadhaarService->verifyOtp($request->otp, $user->aadhar_reference_id);

                if (!$verifyResult['success']) {
                    return response()->json([
                        'status' => false,
                        'message' => $verifyResult['message'] ?? 'Invalid OTP. Please check and try again.',
                        'error'   => $verifyResult['error'] ?? 'OTP verification failed'
                    ], 400);
                }

                // Auto-fill details from Aadhaar if user record is incomplete
                if (!empty($verifyResult['aadhaar_data'])) {
                    $details = $verifyResult['aadhaar_data'];
                    if (empty($user->name) || $user->name == 'Staff Member') $user->name = $details['name'] ?? $user->name;
                    if (empty($user->gender) && !empty($details['gender'])) $user->gender = strtolower($details['gender']);
                    if (empty($user->dob) && !empty($details['dob'])) $user->dob = $details['dob'];
                }
            } else {
                // Fallback: local stored OTP (testing/legacy)
                if (!$user->aadhar__verify_otp || $user->aadhar__verify_otp !== $request->otp) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Invalid OTP. Please try again.'
                    ], 400);
                }
                if ($user->aadhar_number_otp_expire_at && Carbon::now()->gt($user->aadhar_number_otp_expire_at)) {
                    return response()->json([
                        'status' => false,
                        'message' => 'OTP has expired. Please resend OTP.'
                    ], 400);
                }
            }

            // Mark verified
            $user->aadhar__verify = true;
            $user->aadhar__verify_at = Carbon::now();
            $user->aadhar__verify_otp = null;
            $user->aadhar_reference_id = null;
            $user->aadhar_number_otp_expire_at = null;
            $user->save();

            $maskedAadhar = 'XXXX-XXXX-' . substr($user->aadhar_number, -4);

            return response()->json([
                'status'  => true,
                'message' => 'Aadhar number verified successfully',
                'data'    => [
                    'aadhar_number' => $maskedAadhar,
                    'verified_at'   => $user->aadhar__verify_at->format('Y-m-d H:i:s'),
                    'user'          => $user->fresh()
                ]
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed',
                'errors'  => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Aadhaar Verify Error: ' . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => 'Failed to verify Aadhar OTP',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Resend Aadhar OTP
     */
    public function resendAadharOtp(Request $request)
    {
        try {
            $user = Auth::guard('api')->user();
            
            // Check if Aadhar number exists
            if (!$user->aadhar_number) {
                return response()->json([
                    'status' => false,
                    'message' => 'Aadhar number not found. Please save Aadhar number first.'
                ], 400);
            }
            
            // Check if already verified
            if ($user->aadhar__verify) {
                return response()->json([
                    'status' => false,
                    'message' => 'Aadhar number is already verified'
                ], 400);
            }
            
            // Send OTP via real API
            $aadhaarService = new \App\Services\Admin\AadhaarVerificationService();
            $otpResult = $aadhaarService->sendOtp($user->aadhar_number);
            
            if (!$otpResult['success']) {
                return response()->json([
                    'status' => false,
                    'message' => $otpResult['message'] ?? 'Failed to send OTP'
                ], 400);
            }
            
            $user->aadhar_reference_id = $otpResult['reference_id'];
            $user->aadhar_number_otp_expire_at = Carbon::now()->addMinutes(10);
            $user->save();
            
            return response()->json([
                'status' => true,
                'message' => 'OTP resent successfully',
                'reference_id' => $otpResult['reference_id'],
                'data' => [
                    'aadhar_number' => $user->aadhar_number,
                ]
            ], 200);
            
        } catch (\Exception $e) {
            \Log::error('Aadhaar Resend OTP Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to resend OTP',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get Aadhar verification status
     */
    public function getAadharStatus(Request $request)
    {
        try {
            $user = Auth::guard('api')->user();
            
            return response()->json([
                'status' => true,
                'message' => 'Aadhar status retrieved successfully',
                'data' => [
                    'aadhar_number' => $user->aadhar_number ?? Null,
                    'is_verified' => $user->aadhar__verify,
                    'verified_at' => $user->aadhar__verify_at ? $user->aadhar__verify_at->format('Y-m-d H:i:s') : null,
                    'has_pending_otp' => !empty($user->aadhar__verify_otp) && Carbon::now()->lt($user->aadhar_number_otp_expire_at)
                ]
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to get Aadhar status',
                'error' => $e->getMessage()
            ], 500);
        }
    }


   public function maskAadharNumber($aadharNumber)
{
    if (strlen($aadharNumber) === 12) {
        return substr($aadharNumber, 0, 4) . 'XXXX' . substr($aadharNumber, -4);
    }
    return $aadharNumber;
}

public function addressUpdate(Request $request)
{
    $user = Auth::guard('api')->user();
    $userData = User::find($user->id);

    $data = $request->all();

    // Transform grouped fields into array of addresses
    $addresses = [];
    $count = count($data['pincode'] ?? []);

    for ($i = 0; $i < $count; $i++) {
        $addresses[] = [
            'name' => $data['name'][$i] ?? null,
            'street' => $data['street'][$i] ?? null,
            'city' => $data['city'][$i] ?? null,
            'state' => $data['state'][$i] ?? null,
            'pincode' => $data['pincode'][$i] ?? null,
            'is_primary' => isset($data['is_primary'][$i]) ? (bool)$data['is_primary'][$i] : false,
            'id' => $data['id'][$i] ?? null,
            'area_locality' => $data['area_locality'][$i] ?? null,
            'google_location' => $data['google_location'][$i] ?? null,
            'latitude' => $data['lat'][$i] ?? $data['latitude'][$i] ?? null,
            'longitude' => $data['long'][$i] ?? $data['longitude'][$i] ?? null,
        ];
    }

    $updatedAddresses = [];

    foreach ($addresses as $addr) {
        $validated = validator($addr, [
            'id' => 'nullable|integer|exists:user_addresses,id',
            'name' => 'nullable|string|max:255',
            'street' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'pincode' => 'required|string|max:10',
            'area_locality' => 'required|string|max:255',
            'google_location' => 'required|string',
            'is_primary' => 'sometimes|boolean',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric'
        ])->validate();

        $id = $validated['id'] ?? null;

        if ($validated['is_primary'] ?? false) {
            UserAddress::where('user_id', $user->id)
                ->when($id, fn($q) => $q->where('id', '!=', $id))
                ->update(['is_primary' => false]);
        }

        $address = $id
            ? UserAddress::where('user_id', $user->id)->where('id', $id)->first()
            : null;

        if ($address) {
            $address->update($validated);
            $message = 'Address updated successfully';
        } else {
            $address = UserAddress::create(array_merge($validated, ['user_id' => $user->id]));
            $message = 'Address added successfully';
        }

        $updatedAddresses[] = [
            'message' => $message,
            'data' => $address
        ];
    }
        $userData->update(['step' => 4]);

    return response()->json([
        'success' => true,
        'message' => 'Addresses processed successfully',
        'addresses' => $updatedAddresses,
        'userData' => $userData,
    ]);
}

 public function addressIndex()
    {
        $user = Auth::guard('api')->user();
        
        $addresses = UserAddress::where('user_id', $user->id)
            ->orderBy('is_primary', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $addresses
        ]);
    }


    public function updateOrCreateWorkInfo(Request $request)
{
    $user = Auth::guard('api')->user();

    $validated = $request->validate([
        'primary_role' => 'nullable|string|max:255',
        'skills' => 'nullable|array',
        'skills.*' => 'string|max:255',
        'languages_spoken' => 'nullable|array',
        'total_experience' => 'nullable|numeric|min:0|max:100',
        'education' => 'nullable|string|max:255',
        'additional_info' => 'nullable',
        'voice_note' => 'nullable|file', // 10MB max
        'emergency_contact_name' => 'nullable|string|max:255',
        'emergency_contact_number' => 'nullable|string|max:255',
            'relation' => 'nullable|string|max:255',
        'preferred_work_location' => 'nullable|string|max:255',
        'salary_closing_date' => 'nullable|integer|min:1|max:31',
        'upi_id' => 'nullable|string|max:255',
    ]);
 $workInfo = UserWorkInfo::where('user_id', $user->id)->first();
 UserHouseholdInformation::updateOrCreate(
    ['user_id' => $user->id],     // condition
    ['languages_spoken' => $request->languages_spoken] // fields to update
);

if (isset($validated['upi_id'])) {
    $user->upi_id = $validated['upi_id'];
    $user->save();
}

    $data = [
        'primary_role' => $validated['primary_role'] ?? null,
        'skills' => $validated['skills'] ?? [],
        'languages_spoken' => $validated['languages_spoken'] ?? null,
        'total_experience' => $validated['total_experience'] ?? null,
        'education' => $validated['education'] ?? null,
        'additional_info' => $validated['additional_info'] ?? null,
        'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
        'emergency_contact_number' => $validated['emergency_contact_number'] ?? null,
        'preferred_work_location' => $validated['preferred_work_location'] ?? null,
        'salary_closing_date' => $validated['salary_closing_date'] ?? null,
    ];
    if ($request->hasFile('voice_note')) {
        $directory = "uploads/user_voice_notes";
        // if (!file_exists(public_path($directory))) {
        //     mkdir(public_path($directory), 0755, true);
        // }
        // $file = $request->file('voice_note');
        // $extension = $file->getClientOriginalExtension();
        // $fileName = time() . '_' . uniqid() . '.' . $extension;
        // $file->move(public_path($directory), $fileName);
        // $path = $directory . '/' . $fileName;
        // if ($workInfo && $workInfo->voice_note && file_exists(public_path($workInfo->voice_note))) {
        //     unlink(public_path($workInfo->voice_note));
        // }
        $path = $this->uploadCloudary($request,"voice_note",$directory);
        $data['voice_note'] = $path;
    }
    $workInfo = UserWorkInfo::updateOrCreate(
        ['user_id' => $user->id],
        $data
    );

    // Save relation to users table (not on user_work_infos)
    if (isset($validated['relation']) && $validated['relation'] !== null) {
        $user->update(['relation' => $validated['relation']]);
    }

    return response()->json([
        'success' => true,
        'message' => 'Work information saved successfully',
        'data' => $workInfo
    ]);
}
public function listSubcategories(Request $request)
    {
        $validated = $request->validate([
            'parent_id' => 'required|exists:categories,id',
        ]);

        $subcategories = Category::where('parent_id', $validated['parent_id'])
            ->with('children')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Subcategories fetched successfully',
            'data' => $subcategories
        ]);
    }

 public function storeOrUpdate(Request $request)
{
    $user = Auth::user();

    $validated = $request->validate([
        'id'        => 'nullable|exists:categories,id',
        'name'      => 'required|string|max:255',
        'image'     => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
        'parent_id' => 'nullable|exists:categories,id', // for skills (subcategories)
    ]);

    $data = [
        'name'      => $validated['name'],
        'is_active' => $request->input('is_active', 1),
    ];

    // Set parent_id if provided (makes this entry a skill/subcategory)
    if (!empty($validated['parent_id'])) {
        $data['parent_id'] = $validated['parent_id'];
    }

    // If updating, get existing category
    $category = null;
    if (!empty($validated['id'])) {
        $category = Category::findOrFail($validated['id']);
    }

    // Upload new image
    if ($request->hasFile('image')) {

        $file = $request->file('image');

        // Folder structure (no filename here)
        $folderPath = 'uploads/category_images/';
        try {
            $imagepathfull = $this->uploadCloudary($request,"image",$folderPath);
        } catch (\Throwable $th) {
            //throw $th;
            // dd($th->getMessage());
        }
        $data['image'] = $imagepathfull;
    }

    // Create or Update
    if ($category) {
        $category->update($data);
    } else {
        $category = Category::create($data);
    }

    return response()->json([
        'success' => true,
        'message' => !empty($validated['id']) 
                ? 'Category updated successfully' 
                : 'Category created successfully',
        'data' => $category
    ]);
}


public function deleteSelfAccount(Request $request)
{
    try {
        $user = Auth::guard('api')->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        }

        // Mark as deleted (soft delete with is_deleted flag)
        $user->is_deleted = 1;
        $user->deleted_at = now();
        $user->save();

        // Revoke all tokens (logout from all devices)
        $user->tokens()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Your account has been deleted successfully'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to delete account',
            'error' => $e->getMessage()
        ], 500);
    }
}

/**
 * Delete user account by admin
 */
public function deleteUserByAdmin(Request $request)
{
    try {
        // Check if the current user is admin (you might want to add admin role check)
        $adminUser = Auth::guard('api')->user();
        
        // Add admin role validation if you have roles implemented
        // if ($adminUser->user_role_id != 3) { // Assuming 3 is admin role
        //     return response()->json([
        //         'status' => 'error',
        //         'message' => 'Unauthorized. Admin access required.'
        //     ], 403);
        // }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $userToDelete = User::find($request->user_id);

        if (!$userToDelete) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        }

        if ($userToDelete->is_deleted == 1) {
            return response()->json([
                'status' => 'error',
                'message' => 'User account is already deleted'
            ], 400);
        }

        // Mark as deleted
        $userToDelete->is_deleted = 1;
        $userToDelete->deleted_at = now();
        $userToDelete->deleted_by = $adminUser->id; // Track who deleted the account
        $userToDelete->save();

        // Revoke all tokens
        $userToDelete->tokens()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'User account deleted successfully',
            'deleted_user_id' => $userToDelete->id
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to delete user account',
            'error' => $e->getMessage()
        ], 500);
    }
}

/**
 * Get deleted users list (for admin)
 */
public function getDeletedUsers(Request $request)
{
    try {
        $adminUser = Auth::guard('api')->user();
        
        // Add admin role validation if needed
        // if ($adminUser->user_role_id != 3) {
        //     return response()->json([
        //         'status' => 'error',
        //         'message' => 'Unauthorized. Admin access required.'
        //     ], 403);
        // }

        $perPage = $request->per_page ?? 15;
        
        $deletedUsers = User::where('is_deleted', 1)
            ->with(['deletedBy']) // If you have a relationship for deleted_by
            ->orderBy('deleted_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Deleted users retrieved successfully',
            'data' => $deletedUsers
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to retrieve deleted users',
            'error' => $e->getMessage()
        ], 500);
    }
}


public function storeNewMember(Request $request)
{
    try {
        $addedByUserId = Auth::guard('api')->user()->id;
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'mobile_number' => 'required|string|max:15',
        ]);
        $phoneData = [
            'number' => $request->mobile_number ?? null,
            'prefix' => '+91',
        ];

        $dob = null;
        if (!empty($request->dob)) {
            try {
                $dob = \Carbon\Carbon::parse($request->dob)->format('Y-m-d');
            } catch (\Exception $e) {
                $dob = $request->dob;
            }
        }

        $userData = [
            'name' => trim($request->full_name),
            'phone_number' => $phoneData['number'],
            'phone_number_prefix' => $phoneData['prefix'],
            'gender' => $request->gender !== 'Select Gender' ? $request->gender : null,
            'dob' => $dob,
            'relation' => $request->relation !== 'Select Relation' ? $request->relation : null,
            'added_by' => $addedByUserId,
            'user_role_id' => 1,
            'is_active' => true,
            'is_deleted' => false,
            'step' => 6,
        ];

        $user = User::create($userData);

        return response()->json([
            'status' => true,
            'message' => 'Member added successfully.',
            'data' => $user
        ], 201);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'status' => false,
            'message' => 'Validation failed.',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        \Log::error('storeNewMember failed: ' . $e->getMessage());
        return response()->json([
            'status' => false,
            'message' => 'Failed to add member.',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function updateMember(Request $request, $id)
{
    try {
        $addedByUserId = Auth::guard('api')->user()->id;
        $user = User::where('id', $id)
                    ->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Member not found or you do not have permission to update this member.'
            ], 404);
        }
        $validated = $request->validate([
            'full_name' => 'sometimes|string|max:255',
            'mobile_number' => 'sometimes|string|max:15',
        ]);
        $phoneData = [
            'number' => $request->mobile_number ?? $user->phone_number,
            'prefix' => '+91', 
        ];

        $dob = $user->dob;
        if (!empty($request->dob)) {
            try {
                $dob = \Carbon\Carbon::parse($request->dob)->format('Y-m-d');
            } catch (\Exception $e) {
                $dob = $request->dob;
            }
        }

        $userData = [
            'name' => trim($request->full_name) ?? $user->name,
            'phone_number' => $phoneData['number'],
            'phone_number_prefix' => $phoneData['prefix'],
            'gender' => $request->gender !== 'Select Gender' ? $request->gender : $user->gender,
            'dob' => $dob,
            'relation' => $request->relation !== 'Select Relation' ? $request->relation : $user->relation,
        ];
        $userData = array_filter($userData, function($value) {
            return $value !== null;
        });
        $user->update($userData);

        return response()->json([
            'status' => true,
            'message' => 'Member updated successfully.',
            'data' => $user
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'status' => false,
            'message' => 'Validation failed.',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        \Log::error('updateMember failed: ' . $e->getMessage());
        return response()->json([
            'status' => false,
            'message' => 'Failed to update member.',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function editMember($id)
{
    try {
        $user = User::where('id', $id)->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Member not found or you do not have permission to edit this member.'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Member data retrieved successfully.',
            'data' => $user
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to retrieve member data.',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function memberList(Request $request)
{
            $addedByUserId = Auth::guard('api')->user()->id;
    try {
        $members = User::where('added_by', $addedByUserId)
            ->where('is_deleted', false)
            ->where('user_role_id',1)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Member list fetched successfully.',
            'data' => $members
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to fetch member list.',
            'error' => $e->getMessage()
        ], 500);
    }
}



//  public function addStaff(Request $request)
//     {
//         DB::beginTransaction();
        
//         try {
//             // Validate the request
//             $validator = Validator::make($request->all(), [
//                 'first_name' => 'required|string|max:255',
//                 'last_name' => 'required|string|max:255',
//                 'email' => 'required|email|unique:users,email',
//                 'phone_number' => 'required|string|max:15|unique:users,phone_number',
//                 'phone_number_country_code' => 'required|string|max:5',
//                 'gender' => 'required|in:male,female,other',
//                 'dob' => 'required|date',
                
//                 // Address fields
//                 'street' => 'required|string|max:255',
//                 'city' => 'required|string|max:255',
//                 'state' => 'required|string|max:255',
//                 'pincode' => 'required|string|max:10',
                
//                 // Emergency contact
//                 'emergency_contact_name' => 'required|string|max:255',
//                 'emergency_contact_number' => 'required|string|max:15',
                
//                 // Work details
//                 'role_designation' => 'required|string|max:255',
//                 'joining_date' => 'required|date',
//                 'salary' => 'required|numeric',
//                 'pay_frequency' => 'required|in:weekly,monthly,bi-weekly',
//                 'working_days' => 'required|array',
//                 //|unique:users,aadhar_number
//                 // Aadhar details
//                 'aadhar_number' => 'required',
                
//                 // Document files (optional)
//                 'staff_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
//                 'aadhar_front' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
//                 'aadhar_back' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
//                 'police_clearance_certificate' => 'nullable|file|mimes:pdf,jpeg,png,jpg|max:2048',
//             ]);

//             if ($validator->fails()) {
//                 return response()->json([
//                     'success' => false,
//                     'message' => 'Validation error',
//                     'errors' => $validator->errors()
//                 ], 422);
//             }

//             // Handle file uploads
//             $staffPhotoPath = null;
//             $aadharFrontPath = null;
//             $aadharBackPath = null;
//             $policeClearancePath = null;

//             if ($request->hasFile('staff_photo')) {
//                 $staffPhotoPath = $request->file('staff_photo')->store('staff/photos', 'public');
//             }

//             if ($request->hasFile('aadhar_front')) {
//                 $aadharFrontPath = $request->file('aadhar_front')->store('staff/aadhar', 'public');
//             }

//             if ($request->hasFile('aadhar_back')) {
//                 $aadharBackPath = $request->file('aadhar_back')->store('staff/aadhar', 'public');
//             }

//             if ($request->hasFile('police_clearance_certificate')) {
//                 $policeClearancePath = $request->file('police_clearance_certificate')->store('staff/documents', 'public');
//             }

//             // Create staff user
//             $staff = User::create([
//                 'user_role_id' => 2, 
//                 'first_name' => $request->first_name,
//                 'last_name' => $request->last_name,
//                 'name' => $request->first_name . ' ' . $request->last_name,
//                 'email' => $request->email,
//                 'phone_number' => $request->phone_number,
//                 'phone_number_country_code' => $request->phone_number_country_code,
//                 'phone_number_prefix' => $request->phone_number_country_code,
//                 'password' => Hash::make('temp_password_123'), // Set temporary password
//                 'gender' => $request->gender,
//                 'dob' => $request->dob,
//                 'dob' => $request->dob,
                
//                 // Aadhar information
//                 'aadhar_number' => $request->aadhar_number,
                
//                 // Work information
                
//                 // Document paths
//                 'image' => $staffPhotoPath,
//                 'aadhar_front' => $aadharFrontPath,
//                 'aadhar_back' => $aadharBackPath,
//                 'verification_certificate' => $policeClearancePath,
                
//                 // Staff specific flags
//                 'is_staff_added' => 1,
//                 'added_by' => Auth::guard('api')->user()->id,
//                 'is_active' => 1,
//                 'is_verified' => 1,
                
//                 // Emergency contact
//                 'relation' => $request->emergency_contact_name,
//             ]);

//             // Create address record
//             if ($staff) {
//                 UserAddress::create([
//                     'user_id' => $staff->id,
//                     'street' => $request->street,
//                     'city' => $request->city,
//                     'state' => $request->state,
//                     'pincode' => $request->pincode,
//                     'is_primary' => true
//                 ]);
//             }

//             // Create work info record
//             if ($staff) {
//                 UserWorkInfo::create([
//                     'user_id' => $staff->id,
//                     'primary_role' => $request->role_designation,
//                     'joining_date' => $request->joining_date,
//                     'salary' => $request->salary,
//                     'pay_frequency' => $request->pay_frequency,
//                     'working_days' => $request->working_days,
//                     'emergency_contact_name' => $request->emergency_contact_name,
//                     'emergency_contact_number' => $request->emergency_contact_number,
//                 ]);
//             }

//             DB::commit();

//             return response()->json([
//                 'success' => true,
//                 'message' => 'Staff member added successfully',
//                 'data' => $staff->load(['addresses', 'userWorkInfo'])
//             ], 201);

//         } catch (\Exception $e) {
//             DB::rollBack();
            
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Failed to add staff member',
//                 'error' => $e->getMessage()
//             ], 500);
//         }
//     }


// public function addStaff(Request $request)
// {
//     DB::beginTransaction();
    
//     try {
//         // Validate the request
//         $validator = Validator::make($request->all(), [
//             'first_name' => 'required|string|max:255',
//             'last_name' => 'required|string|max:255',
//             'email' => 'required|email|unique:users,email',
//             'phone_number' => 'required|string|max:15|unique:users,phone_number',
//             'phone_number_country_code' => 'required|string|max:5',
//             'gender' => 'required|in:male,female,other',
//             'dob' => 'required|date',
            
//             // Address fields
//             'street' => 'required|string|max:255',
//             'city' => 'required|string|max:255',
//             'state' => 'required|string|max:255',
//             'pincode' => 'required|string|max:10',
            
//             // Emergency contact
//             'emergency_contact_name' => 'required|string|max:255',
//             'emergency_contact_number' => 'required|string|max:15',
            
//             // Work details
//             'role_designation' => 'required|string|max:255',
//             'joining_date' => 'required|date',
//             'salary' => 'required|numeric',
//             'pay_frequency' => 'required|in:weekly,monthly,bi-weekly',
//             'working_days' => 'required|array',
            
//             // Aadhar details
//             'aadhar_number' => 'required',
            
//             // Document files (optional)
//             'staff_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
//             'aadhar_front' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
//             'aadhar_back' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
//             'police_clearance_certificate' => 'nullable|file|mimes:pdf,jpeg,png,jpg|max:2048',
//         ]);

//         if ($validator->fails()) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Validation error',
//                 'errors' => $validator->errors()
//             ], 422);
//         }

//         // Check if Aadhar number already exists
//         $existingUser = User::where('aadhar_number', $request->aadhar_number)->first();

//         if ($existingUser) {
//             // Update existing user
//             return $this->updateExistingStaff($existingUser, $request);
//         }

//         // Handle file uploads
//         $staffPhotoPath = null;
//         $aadharFrontPath = null;
//         $aadharBackPath = null;
//         $policeClearancePath = null;

//         if ($request->hasFile('staff_photo')) {
//             $staffPhotoPath = $request->file('staff_photo')->store('staff/photos', 'public');
//         }

//         if ($request->hasFile('aadhar_front')) {
//             $aadharFrontPath = $request->file('aadhar_front')->store('staff/aadhar', 'public');
//         }

//         if ($request->hasFile('aadhar_back')) {
//             $aadharBackPath = $request->file('aadhar_back')->store('staff/aadhar', 'public');
//         }

//         if ($request->hasFile('police_clearance_certificate')) {
//             $policeClearancePath = $request->file('police_clearance_certificate')->store('staff/documents', 'public');
//         }

//         // Create staff user
//         $staff = User::create([
//             'user_role_id' => 2, 
//             'first_name' => $request->first_name,
//             'last_name' => $request->last_name,
//             'name' => $request->first_name . ' ' . $request->last_name,
//             'email' => $request->email,
//             'phone_number' => $request->phone_number,
//             'phone_number_country_code' => $request->phone_number_country_code,
//             'phone_number_prefix' => $request->phone_number_country_code,
//             'password' => Hash::make('temp_password_123'), // Set temporary password
//             'gender' => $request->gender,
//             'dob' => $request->dob,
            
//             // Aadhar information
//             'aadhar_number' => $request->aadhar_number,
            
//             // Document paths
//             'image' => $staffPhotoPath,
//             'aadhar_front' => $aadharFrontPath,
//             'aadhar_back' => $aadharBackPath,
//             'verification_certificate' => $policeClearancePath,
            
//             // Staff specific flags
//             'is_staff_added' => 1,
//             'added_by' => Auth::guard('api')->user()->id,
//             'is_active' => 1,
//             'is_verified' => 1,
            
//             // Emergency contact
//             'relation' => $request->emergency_contact_name,
//         ]);

//         // Create address record
//         if ($staff) {
//             UserAddress::create([
//                 'user_id' => $staff->id,
//                 'street' => $request->street,
//                 'city' => $request->city,
//                 'state' => $request->state,
//                 'pincode' => $request->pincode,
//                 'is_primary' => true
//             ]);
//         }

//         // Create work info record
//         if ($staff) {
//             UserWorkInfo::create([
//                 'user_id' => $staff->id,
//                 'primary_role' => $request->role_designation,
//                 'joining_date' => $request->joining_date,
//                 'salary' => $request->salary,
//                 'pay_frequency' => $request->pay_frequency,
//                 'working_days' => $request->working_days,
//                 'emergency_contact_name' => $request->emergency_contact_name,
//                 'emergency_contact_number' => $request->emergency_contact_number,
//             ]);
//         }

//         DB::commit();

//         return response()->json([
//             'success' => true,
//             'message' => 'Staff member added successfully',
//             'data' => $staff->load(['addresses', 'userWorkInfo'])
//         ], 201);

//     } catch (\Exception $e) {
//         DB::rollBack();
        
//         return response()->json([
//             'success' => false,
//             'message' => 'Failed to add staff member',
//             'error' => $e->getMessage()
//         ], 500);
//     }
// }

public function addStaff(Request $request)
{
    DB::beginTransaction();
    
    // Get authenticated user for logging
    $authUser = Auth::guard('api')->user();
    $logAction = 'STAFF_ADD';
    
    try {
        // Enforce Staff Limit for House Owners
        if ($authUser && $authUser->user_role_id == 3) {
            $activeSub = \App\Models\SubscriptionUser::where('user_id', $authUser->id)
                ->where('status', 'active')
                ->where('end_date', '>', now())
                ->latest()
                ->first();
                
            $staffLimit = $activeSub ? $activeSub->staff_user_limit : 2; // Default 2 if no active plan just in case
            $extraStaff = $activeSub ? ($activeSub->extra_staff ?? 0) : 0;
            $allowedLimit = $staffLimit + $extraStaff;
            
            // Get current active staff added by this house owner
            $currentStaffCount = \App\Models\User::where('user_role_id', 2)
                ->where('is_deleted', 0)
                ->where('added_by', $authUser->id)
                ->count();
                
            if ($currentStaffCount >= $allowedLimit) {
                $plan = $activeSub ? \App\Models\Subscription::find($activeSub->subscription_id) : null;
                $extraStaffPrice = $plan ? ($plan->extra_staff_price ?? 500) : 500;

                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'error_code' => 'LIMIT_EXCEEDED',
                    'message' => 'Staff limit reached. Please upgrade your plan or buy more limits to add more staff.',
                    'limit_reached' => true,
                    'current_staff_count' => $currentStaffCount,
                    'staff_limit' => $allowedLimit,
                    'extra_staff_price' => $extraStaffPrice
                ], 403);
            }
        }

        if ($request->has('dob') && !empty($request->dob)) {
            $request->merge(['dob' => $this->parseDateToYmd($request->dob)]);
        }
        if ($request->has('joining_date') && !empty($request->joining_date)) {
            $request->merge(['joining_date' => $this->parseDateToYmd($request->joining_date)]);
        }

        // ── SECURITY: Aadhaar OTP verification is MANDATORY ──────────────────
        // Staff can ONLY be added after Aadhaar OTP has been verified.
        // This prevents owners from adding staff without the staff's knowledge.
        if (!empty($request->aadhar_number)) {
            $existingByAadhar = User::where('aadhar_number', $request->aadhar_number)->first();
            if ($existingByAadhar && !$existingByAadhar->aadhar__verify) {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => 'Aadhaar OTP verification is required before adding staff. Please complete the OTP verification process first.',
                ], 403);
            }
        } else {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Aadhaar number is required to add staff.',
            ], 422);
        }
        // ────────────────────────────────────────────────────────────────────

        // ── PRE-VALIDATION: check by Aadhar OR phone FIRST ──────────────────
        // $existingByAadhar already set above from the OTP gate check.
        $existingByPhone  = User::where('phone_number', $request->phone_number)->first();

        // If found by aadhar → re-hire regardless of phone
        if ($existingByAadhar) {
            $existingByAadhar->update(['user_role_id' => 2]);
            DB::commit();
            return $this->updateExistingStaff($existingByAadhar, $request);
        }

        // If found by phone only (no aadhar match) → could be a different person.
        // Still try to re-hire: treat as the same person being re-added.
        // If it turns out to be a conflict the employer will see the updated record.
        if ($existingByPhone) {
            $existingByPhone->update(['user_role_id' => 2]);
            DB::commit();
            return $this->updateExistingStaff($existingByPhone, $request);
        }
        // ────────────────────────────────────────────────────────────────────

        // New person — run full validation (phone unique is safe now because
        // we already handled the existing-user cases above).
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:users,email',
            'phone_number' => 'required|string|max:15|unique:users,phone_number',
            'phone_number_country_code' => 'required|string|max:5',
            'gender' => 'required|in:male,female,other',
            'dob' => 'required|date',
            // Address fields
            'street' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'pincode' => 'required|string|max:10',
            // Work details
            'role_designation' => 'array',
            'joining_date' => 'nullable|date',
            'salary' => 'nullable|numeric',
            'pay_frequency' => 'nullable|in:weekly,monthly,bi-weekly,daily',
            'working_days' => 'nullable|array',
            // Aadhar details
            'aadhar_number' => 'required',
            // Document files (optional)
            'staff_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
            'aadhar_front' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
            'aadhar_back' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
            'police_clearance_certificate' => 'nullable|file|mimes:pdf,jpeg,png,jpg|max:10240',
        ]);

        if ($validator->fails()) {
            DB::rollBack();
            \Log::warning('Staff addition validation failed', [
                'action' => $logAction,
                'requested_by' => $authUser ? $authUser->id : 'unknown',
                'validation_errors' => $validator->errors()->toArray(),
                'timestamp' => now()->toDateTimeString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        \Log::info('No existing staff found with Aadhar number, creating new staff record', [
            'action' => $logAction,
            'requested_by' => $authUser ? $authUser->id : 'unknown',
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'timestamp' => now()->toDateTimeString()
        ]);

        // Handle file uploads
        $staffPhotoPath = null;
        $aadharFrontPath = null;
        $aadharBackPath = null;
        $policeClearancePath = null;

        try {
            if ($request->hasFile('staff_photo')) {
                // $staffPhotoPath = $request->file('staff_photo')->store('staff/photos', 'public');
                $staffPhotoPath = $this->uploadCloudary($request,"staff_photo","staff/photos");
                \Log::info('Staff photo uploaded successfully', [
                    'action' => $logAction,
                    'file_path' => $staffPhotoPath,
                    'timestamp' => now()->toDateTimeString()
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Staff photo upload failed', [
                'action' => $logAction,
                'error' => $e->getMessage(),
                'timestamp' => now()->toDateTimeString()
            ]);
        }

        try {
            if ($request->hasFile('aadhar_front')) {
                // $aadharFrontPath = $request->file('aadhar_front')->store('staff/aadhar', 'public');
                $aadharFrontPath = $this->uploadCloudary($request,"aadhar_front","staff/aadhar");
                
                \Log::info('Aadhar front photo uploaded successfully', [
                    'action' => $logAction,
                    'file_path' => $aadharFrontPath,
                    'timestamp' => now()->toDateTimeString()
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Aadhar front photo upload failed', [
                'action' => $logAction,
                'error' => $e->getMessage(),
                'timestamp' => now()->toDateTimeString()
            ]);
        }

        try {
            if ($request->hasFile('aadhar_back')) {
                // $aadharBackPath = $request->file('aadhar_back')->store('staff/aadhar', 'public');
                $aadharBackPath = $this->uploadCloudary($request,"aadhar_back","staff/aadhar");
                \Log::info('Aadhar back photo uploaded successfully', [
                    'action' => $logAction,
                    'file_path' => $aadharBackPath,
                    'timestamp' => now()->toDateTimeString()
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Aadhar back photo upload failed', [
                'action' => $logAction,
                'error' => $e->getMessage(),
                'timestamp' => now()->toDateTimeString()
            ]);
        }

        try {
            if ($request->hasFile('police_clearance_certificate')) {
                // $policeClearancePath = $request->file('police_clearance_certificate')->store('staff/documents', 'public');
                $policeClearancePath = $this->uploadCloudary($request,"police_clearance_certificate","staff/documents");
                
                \Log::info('Police clearance certificate uploaded successfully', [
                    'action' => $logAction,
                    'file_path' => $policeClearancePath,
                    'timestamp' => now()->toDateTimeString()
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Police clearance certificate upload failed', [
                'action' => $logAction,
                'error' => $e->getMessage(),
                'timestamp' => now()->toDateTimeString()
            ]);
        }

        // Create staff user
        try {
            \Log::info('Creating staff user record', [
                'action' => $logAction,
                'user_data' => [
                    'name' => $request->first_name . ' ' . $request->last_name,
                    'email' => $request->email,
                    'phone_number' => $request->phone_number,
                    'role_designation' => $request->role_designation,
                    'joining_date' => $request->joining_date ?? null,
                    'salary' => $request->salary ?? '',
                    'pay_frequency' => $request->pay_frequency ?? '',
                ],
                'timestamp' => now()->toDateTimeString()
            ]);

            $staff = User::create([
                'user_role_id' => 2, 
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'name' => $request->first_name . ' ' . $request->last_name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
                'phone_number_country_code' => $request->phone_number_country_code,
                'phone_number_prefix' => $request->phone_number_country_code,
                'password' => Hash::make('temp_password_123'),
                'gender' => $request->gender,
                'dob' => $request->dob,
                'aadhar_number' => $request->aadhar_number,
                'image' => $staffPhotoPath,
                'aadhar_front' => $aadharFrontPath,
                'aadhar_back' => $aadharBackPath,
                'verification_certificate' => $policeClearancePath,
                'is_staff_added' => 1,
                'added_by' => $authUser->id,
                'is_active' => 1,
                'is_verified' => 1,
                'relation' => $request->relation,
                'upi_id' => $request->upi_id ?? null,
                'is_deleted' => 0,
                'status' => 'active',
            ]);
            
            \Log::info('Staff user record created successfully', [
                'action' => $logAction,
                'staff_id' => $staff->id,
                'staff_name' => $staff->name,
                'created_at' => $staff->created_at,
                'timestamp' => now()->toDateTimeString()
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to create staff user record', [
                'action' => $logAction,
                'error' => $e->getMessage(),
                'timestamp' => now()->toDateTimeString()
            ]);
            throw $e;
        }

        // Create address record
        try {
            if ($staff) {
                \Log::info('Creating staff address record', [
                    'action' => $logAction,
                    'staff_id' => $staff->id,
                    'address_data' => [
                        'city' => $request->city,
                        'state' => $request->state,
                        'pincode' => $request->pincode
                    ],
                    'timestamp' => now()->toDateTimeString()
                ]);

                $address = UserAddress::create([
                    'user_id' => $staff->id,
                    'street' => $request->street,
                    'city' => $request->city,
                    'state' => $request->state,
                    'pincode' => $request->pincode,
                    'area_locality' => $request->area_locality,
                    'google_location' => $request->google_location,
                    'latitude' => $request->lat,
                    'longitude' => $request->long,
                    'is_primary' => true
                ]);

                \Log::info('Staff address record created successfully', [
                    'action' => $logAction,
                    'staff_id' => $staff->id,
                    'address_id' => $address->id,
                    'timestamp' => now()->toDateTimeString()
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to create staff address record', [
                'action' => $logAction,
                'staff_id' => $staff->id,
                'error' => $e->getMessage(),
                'timestamp' => now()->toDateTimeString()
            ]);
            throw $e;
        }

        // Create work info record
        try {
            if ($staff) {
                \Log::info('Creating staff work info record', [
                    'action' => $logAction,
                    'staff_id' => $staff->id,
                    'work_info' => [
                        'primary_role' => $request->role_designation,
                        'joining_date' => $request->joining_date ?? null,
                        'salary' => $request->salary ?? null,
                        'pay_frequency' => $request->pay_frequency ?? null,
                        'working_days_count' => count($request->working_days ?? [])
                    ],
                    'timestamp' => now()->toDateTimeString()
                ]);
                
                $workInfo = UserWorkInfo::create([
                    'user_id' => $staff->id,
                    'primary_role' => $request->role_designation,
                    'joining_date' => $request->joining_date ?? null,
                    'salary' => $request->salary ?? null,
                    'pay_frequency' => $request->pay_frequency ?? null,
                    'salary_closing_date' => $request->salary_closing_date ?? null,
                    'working_days' => $request->working_days ?? null,
                    'emergency_contact_name' => $request->emergency_contact_name,
                    'emergency_contact_number' => $request->emergency_contact_number,
                ]);

                \Log::info('Staff work info record created successfully', [
                    'action' => $logAction,
                    'staff_id' => $staff->id,
                    'work_info_id' => $workInfo->id,
                    'timestamp' => now()->toDateTimeString()
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to create staff work info record', [
                'action' => $logAction,
                'staff_id' => $staff->id,
                'error' => $e->getMessage(),
                'timestamp' => now()->toDateTimeString()
            ]);
            throw $e;
        }

        // Create household information record (if languages_spoken is provided)
        try {
            if ($staff && $request->has('languages_spoken')) {
                \Log::info('Creating/updating staff household information record', [
                    'action' => $logAction,
                    'staff_id' => $staff->id,
                    'languages_spoken' => $request->languages_spoken,
                    'timestamp' => now()->toDateTimeString()
                ]);

                // Make sure to import UserHouseholdInformation model at the top of your file
                // Add this to your imports: use App\Models\UserHouseholdInformation;
                UserHouseholdInformation::updateOrCreate(
                    ['user_id' => $staff->id],
                    ['languages_spoken' => $request->languages_spoken]
                );

                \Log::info('Staff household information record processed successfully', [
                    'action' => $logAction,
                    'staff_id' => $staff->id,
                    'timestamp' => now()->toDateTimeString()
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to process staff household information', [
                'action' => $logAction,
                'staff_id' => $staff->id,
                'error' => $e->getMessage(),
                'timestamp' => now()->toDateTimeString()
            ]);
            // Don't throw for household info as it's optional
        }

        DB::commit();

        \App\Services\NotificationService::staffAdded($staff->id, $authUser->name);

        \Log::info('Staff addition completed successfully', [
            'action' => $logAction,
            'staff_id' => $staff->id,
            'staff_name' => $staff->name,
            'added_by' => $authUser->id,
            'added_by_name' => $authUser->name,
            'transaction_committed' => true,
            'timestamp' => now()->toDateTimeString()
        ]);

        

        return response()->json([
            'success' => true,
            'message' => 'Staff member added successfully',
            'data' => $staff->load(['addresses', 'userWorkInfo'])
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();

        \Log::error('Staff addition failed - Transaction rolled back', [
            'action' => $logAction,
            'error_message' => $e->getMessage(),
            'error_trace' => $e->getTraceAsString(),
            'requested_by' => $authUser ? $authUser->id : 'unknown',
            'transaction_rolled_back' => true,
            'timestamp' => now()->toDateTimeString()
        ]);
        try {
             file_put_contents(storage_path('logs/debug_error.log'), date('Y-m-d H:i:s') . " - Error in addStaff: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n", FILE_APPEND);
        } catch (\Exception $writeErr) {}

        return response()->json([
            'success' => false,
            'message' => 'Failed to add staff member',
            'error' => env('APP_DEBUG') ? $e->getMessage() : 'Internal server error'
        ], 500);
    }
}/**
 * Update existing staff member
 */
private function updateExistingStaff(User $existingUser, Request $request)
{
    // DB::beginTransaction();
    
    $authUser = Auth::guard('api')->user();
    $logAction = 'STAFF_UPDATE_EXISTING';
    
    // try {
        \Log::info('Starting update for existing staff', [
            'action' => $logAction,
            'existing_user_id' => $existingUser->id,
            'existing_email' => $existingUser->email,
            'existing_phone' => $existingUser->phone_number,
            'requested_by' => $authUser ? $authUser->id : 'unknown',
            'timestamp' => now()->toDateTimeString(),
            'update_data_summary' => [
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'role_designation' => $request->role_designation
            ]
        ]);

        $validator = Validator::make($request->all(), [
            'street' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'pincode' => 'nullable|string|max:10',
            'area_locality' => 'nullable|string|max:255',
            'google_location' => 'nullable|string',
            'lat' => 'nullable|numeric',
            'long' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Handle file uploads
        $fileUpdateLog = [
            'staff_photo' => 'not_updated',
            'aadhar_front' => 'not_updated',
            'aadhar_back' => 'not_updated',
            'police_clearance_certificate' => 'not_updated'
        ];

        $staffPhotoPath = $existingUser->image;
        $aadharFrontPath = $existingUser->aadhar_front;
        $aadharBackPath = $existingUser->aadhar_back;
        $policeClearancePath = $existingUser->verification_certificate;

        try {
            if ($request->hasFile('staff_photo')) {
                \Log::info('Processing staff photo update', [
                    'action' => $logAction,
                    'user_id' => $existingUser->id,
                    'old_photo_exists' => !empty($existingUser->image),
                    'timestamp' => now()->toDateTimeString()
                ]);

                // Delete old photo if exists
                if ($existingUser->image && Storage::disk('public')->exists($existingUser->image)) {
                    Storage::disk('public')->delete($existingUser->image);
                    \Log::info('Old staff photo deleted', [
                        'action' => $logAction,
                        'user_id' => $existingUser->id,
                        'old_path' => $existingUser->image,
                        'timestamp' => now()->toDateTimeString()
                    ]);
                }
                $staffPhotoPath = $this->uploadCloudary($request,"staff_photo","staff/photos");
                // $staffPhotoPath = $request->file('staff_photo')->store('staff/photos', 'public');
                $fileUpdateLog['staff_photo'] = 'updated';
                
                \Log::info('Staff photo updated successfully', [
                    'action' => $logAction,
                    'user_id' => $existingUser->id,
                    'new_path' => $staffPhotoPath,
                    'timestamp' => now()->toDateTimeString()
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to update staff photo', [
                'action' => $logAction,
                'user_id' => $existingUser->id,
                'error' => $e->getMessage(),
                'timestamp' => now()->toDateTimeString()
            ]);
            $fileUpdateLog['staff_photo'] = 'failed';
        }

        try {
            if ($request->hasFile('aadhar_front')) {
                \Log::info('Processing Aadhar front update', [
                    'action' => $logAction,
                    'user_id' => $existingUser->id,
                    'old_file_exists' => !empty($existingUser->aadhar_front),
                    'timestamp' => now()->toDateTimeString()
                ]);

                if ($existingUser->aadhar_front && Storage::disk('public')->exists($existingUser->aadhar_front)) {
                    Storage::disk('public')->delete($existingUser->aadhar_front);
                    \Log::info('Old Aadhar front deleted', [
                        'action' => $logAction,
                        'user_id' => $existingUser->id,
                        'old_path' => $existingUser->aadhar_front,
                        'timestamp' => now()->toDateTimeString()
                    ]);
                }
                $aadharFrontPath = $this->uploadCloudary($request,"aadhar_front","staff/aadhar");
                // $aadharFrontPath = $request->file('aadhar_front')->store('staff/aadhar', 'public');
                $fileUpdateLog['aadhar_front'] = 'updated';
                
                \Log::info('Aadhar front updated successfully', [
                    'action' => $logAction,
                    'user_id' => $existingUser->id,
                    'new_path' => $aadharFrontPath,
                    'timestamp' => now()->toDateTimeString()
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to update Aadhar front', [
                'action' => $logAction,
                'user_id' => $existingUser->id,
                'error' => $e->getMessage(),
                'timestamp' => now()->toDateTimeString()
            ]);
            $fileUpdateLog['aadhar_front'] = 'failed';
        }

        try {
            if ($request->hasFile('aadhar_back')) {
                \Log::info('Processing Aadhar back update', [
                    'action' => $logAction,
                    'user_id' => $existingUser->id,
                    'old_file_exists' => !empty($existingUser->aadhar_back),
                    'timestamp' => now()->toDateTimeString()
                ]);

                if ($existingUser->aadhar_back && Storage::disk('public')->exists($existingUser->aadhar_back)) {
                    Storage::disk('public')->delete($existingUser->aadhar_back);
                    \Log::info('Old Aadhar back deleted', [
                        'action' => $logAction,
                        'user_id' => $existingUser->id,
                        'old_path' => $existingUser->aadhar_back,
                        'timestamp' => now()->toDateTimeString()
                    ]);
                }
                $aadharBackPath = $this->uploadCloudary($request,"aadhar_back","staff/aadhar");
                
                // $aadharBackPath = $request->file('aadhar_back')->store('staff/aadhar', 'public');
                $fileUpdateLog['aadhar_back'] = 'updated';
                
                \Log::info('Aadhar back updated successfully', [
                    'action' => $logAction,
                    'user_id' => $existingUser->id,
                    'new_path' => $aadharBackPath,
                    'timestamp' => now()->toDateTimeString()
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to update Aadhar back', [
                'action' => $logAction,
                'user_id' => $existingUser->id,
                'error' => $e->getMessage(),
                'timestamp' => now()->toDateTimeString()
            ]);
            $fileUpdateLog['aadhar_back'] = 'failed';
        }

        try {
            if ($request->hasFile('police_clearance_certificate')) {
                \Log::info('Processing police clearance certificate update', [
                    'action' => $logAction,
                    'user_id' => $existingUser->id,
                    'old_file_exists' => !empty($existingUser->verification_certificate),
                    'timestamp' => now()->toDateTimeString()
                ]);

                if ($existingUser->verification_certificate && Storage::disk('public')->exists($existingUser->verification_certificate)) {
                    Storage::disk('public')->delete($existingUser->verification_certificate);
                    \Log::info('Old police clearance certificate deleted', [
                        'action' => $logAction,
                        'user_id' => $existingUser->id,
                        'old_path' => $existingUser->verification_certificate,
                        'timestamp' => now()->toDateTimeString()
                    ]);
                }
                
                $policeClearancePath = $this->uploadCloudary($request,"police_clearance_certificate","staff/documents");
                
                // $policeClearancePath = $request->file('police_clearance_certificate')->store('staff/documents', 'public');
                $fileUpdateLog['police_clearance_certificate'] = 'updated';
                
                \Log::info('Police clearance certificate updated successfully', [
                    'action' => $logAction,
                    'user_id' => $existingUser->id,
                    'new_path' => $policeClearancePath,
                    'timestamp' => now()->toDateTimeString()
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to update police clearance certificate', [
                'action' => $logAction,
                'user_id' => $existingUser->id,
                'error' => $e->getMessage(),
                'timestamp' => now()->toDateTimeString()
            ]);
            $fileUpdateLog['police_clearance_certificate'] = 'failed';
        }

        \Log::info('File update summary', [
            'action' => $logAction,
            'user_id' => $existingUser->id,
            'file_updates' => $fileUpdateLog,
            'timestamp' => now()->toDateTimeString()
        ]);

        // Update user details
        try {
            \Log::info('Updating user record', [
                'action' => $logAction,
                'user_id' => $existingUser->id,
                'updates' => [
                    'name' => $request->first_name . ' ' . $request->last_name,
                    'email_old' => $existingUser->email,
                    'email_new' => $request->email,
                    'phone_old' => $existingUser->phone_number,
                    'phone_new' => $request->phone_number,
                    'role_designation' => $request->role_designation
                ],
                'timestamp' => now()->toDateTimeString()
            ]);
            // Prepare update data - match database column names exactly
    $updateData = [
        'first_name' => $request->first_name,
        'last_name' => $request->last_name,
        'name' => $request->first_name . ' ' . $request->last_name,
        'email' => $request->email,
        'phone_number' => $request->phone_number,
        'phone_number_country_code' => $request->phone_number_country_code,
        'phone_number_prefix' => $request->phone_number_country_code,
        'gender' => $request->gender,
        'dob' => $request->dob,
        'image' => $staffPhotoPath,
        'aadhar_front' => $aadharFrontPath,
        'aadhar_back' => $aadharBackPath,
        'verification_certificate' => $policeClearancePath,
        'is_staff_added' => 1,
        'added_by' => $authUser->id,
        'is_active' => 1,
        'is_verified' => 1,
        'step' => 6,
        'relation' => $request->relation,
        'upi_id' => $request->upi_id,
        'is_deleted' => 0, // ← RESTORE: Ensure not deleted
        'status' => 'active', // ← RESTORE: Ensure active status
    ];
    
    \Log::info('Update Data Prepared', ['data' => $updateData]);
    
    // Use save() method instead of update() to bypass fillable restrictions
    foreach ($updateData as $key => $value) {
        if ($value !== null) {
            $existingUser->{$key} = $value;
        }
    }
    
    // Save the changes
    $saveResult = $existingUser->save();
    
    // Refresh and log after update
    $existingUser->refresh();
// dd($existingUser);
            \Log::info('User record updated successfully', [
                'action' => $logAction,
                'user_id' => $existingUser->id,
                'updated_at' => $existingUser->updated_at,
                'timestamp' => now()->toDateTimeString()
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to update user record', [
                'action' => $logAction,
                'user_id' => $existingUser->id,
                'error' => $e->getMessage(),
                'timestamp' => now()->toDateTimeString()
            ]);
            throw $e;
        }

        // Update or create address record
        try {
            \Log::info('Processing address record update/creation', [
                'action' => $logAction,
                'user_id' => $existingUser->id,
                'address_data' => [
                    'city' => $request->city,
                    'state' => $request->state,
                    'pincode' => $request->pincode
                ],
                'timestamp' => now()->toDateTimeString()
            ]);

            $existingAddress = UserAddress::where('user_id', $existingUser->id)->first();
            
            if ($existingAddress) {
                \Log::info('Existing address found, updating', [
                    'action' => $logAction,
                    'user_id' => $existingUser->id,
                    'address_id' => $existingAddress->id,
                    'timestamp' => now()->toDateTimeString()
                ]);

                $existingAddress->update([
                    'street' => $request->street,
                    'city' => $request->city,
                    'state' => $request->state,
                    'pincode' => $request->pincode,
                    'area_locality' => $request->area_locality,
                    'google_location' => $request->google_location,
                    'latitude' => $request->lat,
                    'longitude' => $request->long,
                    'is_primary' => true
                ]);

                \Log::info('Address updated successfully', [
                    'action' => $logAction,
                    'user_id' => $existingUser->id,
                    'address_id' => $existingAddress->id,
                    'timestamp' => now()->toDateTimeString()
                ]);
            } else {
                \Log::info('No existing address found, creating new', [
                    'action' => $logAction,
                    'user_id' => $existingUser->id,
                    'timestamp' => now()->toDateTimeString()
                ]);

                $newAddress = UserAddress::create([
                    'user_id' => $existingUser->id,
                    'street' => $request->street,
                    'city' => $request->city,
                    'state' => $request->state,
                    'pincode' => $request->pincode,
                    'area_locality' => $request->area_locality,
                    'google_location' => $request->google_location,
                    'latitude' => $request->lat,
                    'longitude' => $request->long,
                    'is_primary' => true
                ]);

                \Log::info('New address created successfully', [
                    'action' => $logAction,
                    'user_id' => $existingUser->id,
                    'address_id' => $newAddress->id,
                    'timestamp' => now()->toDateTimeString()
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to process address record', [
                'action' => $logAction,
                'user_id' => $existingUser->id,
                'error' => $e->getMessage(),
                'timestamp' => now()->toDateTimeString()
            ]);
            throw $e;
        }

        // Update or create work info record
        try {
            \Log::info('Processing work info record update/creation', [
                'action' => $logAction,
                'user_id' => $existingUser->id,
                'work_info' => [
                    'primary_role' => $request->role_designation,
                    'joining_date' => $request->joining_date,
                    'salary' => $request->salary,
                    'pay_frequency' => $request->pay_frequency,
                    'working_days_count' => count($request->working_days)
                ],
                'timestamp' => now()->toDateTimeString()
            ]);

            $existingWorkInfo = UserWorkInfo::where('user_id', $existingUser->id)->first();
            
            if ($existingWorkInfo) {
                \Log::info('Existing work info found, updating', [
                    'action' => $logAction,
                    'user_id' => $existingUser->id,
                    'work_info_id' => $existingWorkInfo->id,
                    'timestamp' => now()->toDateTimeString()
                ]);

                $existingWorkInfo->update([
                    'primary_role' => $request->role_designation,
                    'joining_date' => $request->joining_date,
                    'salary' => $request->salary,
                    'pay_frequency' => $request->pay_frequency,
                    'working_days' => $request->working_days,
                    'emergency_contact_name' => $request->emergency_contact_name,
                    'emergency_contact_number' => $request->emergency_contact_number,
                ]);

                \Log::info('Work info updated successfully', [
                    'action' => $logAction,
                    'user_id' => $existingUser->id,
                    'work_info_id' => $existingWorkInfo->id,
                    'timestamp' => now()->toDateTimeString()
                ]);
            } else {
                \Log::info('No existing work info found, creating new', [
                    'action' => $logAction,
                    'user_id' => $existingUser->id,
                    'timestamp' => now()->toDateTimeString()
                ]);

                $newWorkInfo = UserWorkInfo::create([
                    'user_id' => $existingUser->id,
                    'primary_role' => $request->role_designation,
                    'joining_date' => $request->joining_date,
                    'salary' => $request->salary,
                    'pay_frequency' => $request->pay_frequency,
                    'working_days' => $request->working_days,
                    'emergency_contact_name' => $request->emergency_contact_name,
                    'emergency_contact_number' => $request->emergency_contact_number,
                ]);

                \Log::info('New work info created successfully', [
                    'action' => $logAction,
                    'user_id' => $existingUser->id,
                    'work_info_id' => $newWorkInfo->id,
                    'timestamp' => now()->toDateTimeString()
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to process work info record', [
                'action' => $logAction,
                'user_id' => $existingUser->id,
                'error' => $e->getMessage(),
                'timestamp' => now()->toDateTimeString()
            ]);
            throw $e;
        }

        // Update or create household information record
        try {
            if ($request->has('languages_spoken')) {
                \Log::info('Processing household information update/creation', [
                    'action' => $logAction,
                    'user_id' => $existingUser->id,
                    'languages_spoken' => $request->languages_spoken,
                    'timestamp' => now()->toDateTimeString()
                ]);

                UserHouseholdInformation::updateOrCreate(
                    ['user_id' => $existingUser->id],
                    ['languages_spoken' => $request->languages_spoken]
                );

                \Log::info('Household information processed successfully', [
                    'action' => $logAction,
                    'user_id' => $existingUser->id,
                    'timestamp' => now()->toDateTimeString()
                ]);
            } else {
                \Log::info('No languages_spoken provided, skipping household information update', [
                    'action' => $logAction,
                    'user_id' => $existingUser->id,
                    'timestamp' => now()->toDateTimeString()
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to process household information', [
                'action' => $logAction,
                'user_id' => $existingUser->id,
                'error' => $e->getMessage(),
                'timestamp' => now()->toDateTimeString()
            ]);
            // Don't throw for household info as it's optional
        }

        DB::commit();

        \Log::info('Existing staff update completed successfully', [
            'action' => $logAction,
            'user_id' => $existingUser->id,
            'updated_by' => $authUser->id,
            'updated_by_name' => $authUser->name,
            'transaction_committed' => true,
            'timestamp' => now()->toDateTimeString(),
            'final_user_status' => [
                'is_staff_added' => $existingUser->is_staff_added,
                'is_active' => $existingUser->is_active,
                'is_verified' => $existingUser->is_verified,
                'step' => $existingUser->step
            ]
        ]);
// dd($existingUser);
        return response()->json([
            'success' => true,
            'message' => 'Staff member updated successfully',
            'data' => $existingUser->load(['addresses', 'userWorkInfo', 'kycInformation'])
        ], 200);

    // } catch (\Exception $e) {
    //     DB::rollBack();
        
    //     \Log::error('Existing staff update failed - Transaction rolled back', [
    //         'action' => $logAction,
    //         'user_id' => $existingUser->id,
    //         'error_message' => $e->getMessage(),
    //         'error_trace' => $e->getTraceAsString(),
    //         'requested_by' => $authUser ? $authUser->id : 'unknown',
    //         'transaction_rolled_back' => true,
    //         'timestamp' => now()->toDateTimeString()
    //     ]);

    //     return response()->json([
    //         'success' => false,
    //         'message' => 'Failed to update staff member',
    //         'error' => env('APP_DEBUG') ? $e->getMessage() : 'Internal server error'
    //     ], 500);
    // }
}
    /**
     * Get list of all staff members
     */
    public function getStaffList(Request $request)
    {
        try {
            $user = Auth::guard('api')->user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $perPage = $request->get('per_page', 10);
            $search = $request->get('search', '');
            
            // Get all staff members hired by this user through JobApplications
            $hiredStatuses = ['accepted', 'approved', 'active', 'hired', 'terminated', 'inactive'];
            $hiredStaffIds = JobApplication::whereIn('application_status', $hiredStatuses)
                ->whereHas('job', function($query) use ($user) {
                    $query->where('created_by', $user->id);
                })
                ->pluck('user_id')
                ->toArray();

            // Include staff added directly
            $directlyAddedStaffIds = User::where('user_role_id', 2)
                ->where('added_by', $user->id)
                ->pluck('id')
                ->toArray();

            $allStaffIds = array_unique(array_merge($hiredStaffIds, $directlyAddedStaffIds));

            $staffQuery = User::whereIn('id', $allStaffIds)
                ->where('is_deleted', 0)
                ->with(['addresses', 'userWorkInfo', 'addedByUser'])
                ->orderBy('created_at', 'desc');

            // Search functionality
            if (!empty($search)) {
                $staffQuery->where(function($query) use ($search) {
                    $query->where('first_name', 'like', "%{$search}%")
                          ->orWhere('last_name', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%")
                          ->orWhere('phone_number', 'like', "%{$search}%")
                          ->orWhere('aadhar_number', 'like', "%{$search}%");
                });
            }

            $staff = $staffQuery->paginate($perPage);

            $staff->getCollection()->transform(function ($item) use ($user) {
                if ($item->added_by != $user->id) {
                    $item->status = 'inactive';
                }
                return $item;
            });

            return response()->json([
                'success' => true,
                'message' => 'Staff list retrieved successfully',
                'data' => $staff
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve staff list',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get staff member details by ID
     */
    public function getStaffDetails($id)
    {
        try {
            $user = Auth::guard('api')->user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            // Get all staff members hired by this user through JobApplications or added directly
            $hiredStatuses = ['accepted', 'approved', 'active', 'hired', 'terminated', 'inactive'];
            $hiredStaffIds = JobApplication::whereIn('application_status', $hiredStatuses)
                ->whereHas('job', function($query) use ($user) {
                    $query->where('created_by', $user->id);
                })
                ->pluck('user_id')
                ->toArray();

            $directlyAddedStaffIds = User::where('user_role_id', 2)
                ->where('added_by', $user->id)
                ->pluck('id')
                ->toArray();

            $allStaffIds = array_unique(array_merge($hiredStaffIds, $directlyAddedStaffIds));

            if (!in_array($id, $allStaffIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Staff member not found'
                ], 404);
            }

            $staff = User::where('id', $id)
                ->where('is_deleted', 0)
                ->with(['addresses', 'userWorkInfo', 'addedByUser'])
                ->first();

            if (!$staff) {
                return response()->json([
                    'success' => false,
                    'message' => 'Staff member not found'
                ], 404);
            }

            if ($staff->added_by != $user->id) {
                $staff->status = 'inactive';
            }

            return response()->json([
                'success' => true,
                'message' => 'Staff details retrieved successfully',
                'data' => $staff
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve staff details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get details of any available/registered staff member
     */
    private function availableStaffProfileRelations(): array
    {
        $relations = [
            'addresses',
            'userWorkInfo',
            'addedByUser',
            'lastExp',
            'kycInformation',
            'petDetails',
            'householdInformation',
        ];

        // Reviews are optional in older production databases. Eager-loading
        // the missing table must not prevent paid owners from viewing staff.
        if (Schema::hasTable('reviews')) {
            $relations[] = 'reviewsReceived';
        }

        return $relations;
    }

    public function getAvailableStaffDetails($id)
    {
        try {
            $staff = User::where('user_role_id', 2)
                ->where('id', $id)
                ->with($this->availableStaffProfileRelations())
                ->first();

            if (!$staff) {
                return response()->json([
                    'success' => false,
                    'message' => 'Available staff member not found'
                ], 404);
            }

            $user = Auth::guard('api')->user();
            $hasPaidContactAccess = false;
            $isHiredOrAdded = false;

            if ($user) {
                $hiredStatuses = ['accepted', 'approved', 'active', 'hired', 'terminated', 'inactive'];
                $hiredStaffIds = JobApplication::whereIn('application_status', $hiredStatuses)
                    ->whereHas('job', function($query) use ($user) {
                        $query->where('created_by', $user->id);
                    })
                    ->pluck('user_id')
                    ->toArray();

                $directlyAddedStaffIds = User::where('user_role_id', 2)
                    ->where('added_by', $user->id)
                    ->pluck('id')
                    ->toArray();

                $allStaffIds = array_unique(array_merge($hiredStaffIds, $directlyAddedStaffIds));

                if (in_array($id, $allStaffIds)) {
                    $isHiredOrAdded = true;
                    if ($staff->added_by != $user->id) {
                        $staff->status = 'inactive';
                    }
                }

                $activeSubscriptions = \App\Models\SubscriptionUser::with('subscription')
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->where('end_date', '>', now())
                    ->whereNull('deleted_at')
                    ->latest()
                    ->get();
                $hasPaidContactAccess = $activeSubscriptions->contains(
                    fn ($subscription) => $subscription->hasActivePaidAccess()
                );
            }

            $contactViewLocked = false;
            if (!$isHiredOrAdded && !$hasPaidContactAccess) {
                $contactViewLocked = true;
                if ($staff->phone_number) {
                    $len = strlen($staff->phone_number);
                    if ($len > 4) {
                        $staff->phone_number = substr($staff->phone_number, 0, 2) . str_repeat('*', $len - 4) . substr($staff->phone_number, -2);
                    } else {
                        $staff->phone_number = '******';
                    }
                }
                $staff->email = '***@***.com';
                $staff->phone_number_country_code = null;
            }

            return response()->json([
                'success' => true,
                'message' => 'Available staff details retrieved successfully',
                'contact_view_locked' => $contactViewLocked,
                'data' => $staff
            ])->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve available staff details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update staff member
     */
   public function updateStaff(Request $request, $id)
{
    DB::beginTransaction();
    
    $authUser = Auth::guard('api')->user();
    $logAction = 'STAFF_UPDATE_BY_ID';
    
    try {
        if ($request->has('dob') && !empty($request->dob)) {
            $request->merge(['dob' => $this->parseDateToYmd($request->dob)]);
        }
        if ($request->has('joining_date') && !empty($request->joining_date)) {
            $request->merge(['joining_date' => $this->parseDateToYmd($request->joining_date)]);
        }

        \Log::info('Starting staff update by ID', [
            'action' => $logAction,
            'staff_id' => $id,
            'requested_by' => $authUser ? $authUser->id : 'unknown',
            'requested_by_name' => $authUser ? $authUser->name : 'unknown',
            'timestamp' => now()->toDateTimeString(),
            'request_data_summary' => $request->except(['staff_photo']) // Exclude file data
        ]);

        // Find staff member
        try {
            \Log::info('Looking for staff member', [
                'action' => $logAction,
                'staff_id' => $id,
                'criteria' => [
                    'is_staff_added' => 1,
                    'added_by' => $authUser->id
                ],
                'timestamp' => now()->toDateTimeString()
            ]);

            $staff = User::where('is_staff_added', 1)
                ->where('added_by', $authUser->id)
                ->where('id', $id)
                ->first();

            if (!$staff) {
                \Log::warning('Staff member not found or unauthorized', [
                    'action' => $logAction,
                    'staff_id' => $id,
                    'searched_by_user' => $authUser->id,
                    'timestamp' => now()->toDateTimeString(),
                    'note' => 'Staff not found or user does not have permission to update this staff'
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Staff member not found'
                ], 404);
            }

            \Log::info('Staff member found', [
                'action' => $logAction,
                'staff_id' => $staff->id,
                'staff_name' => $staff->name,
                'staff_email' => $staff->email,
                'timestamp' => now()->toDateTimeString()
            ]);
        } catch (\Exception $e) {
            \Log::error('Error finding staff member', [
                'action' => $logAction,
                'staff_id' => $id,
                'error' => $e->getMessage(),
                'timestamp' => now()->toDateTimeString()
            ]);
            throw $e;
        }

        // Validate request
        try {
            \Log::info('Starting validation for staff update', [
                'action' => $logAction,
                'staff_id' => $staff->id,
                'validation_rules_count' => 16, // Count of validation rules
                'timestamp' => now()->toDateTimeString()
            ]);

            $validator = Validator::make($request->all(), [
                'first_name' => 'sometimes|required|string|max:255',
                'last_name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|nullable|email|unique:users,email,' . $id,
                'phone_number' => 'sometimes|required|string|max:15|unique:users,phone_number,' . $id,
                'gender' => 'sometimes|required|in:male,female,other',
                'dob' => 'sometimes|required|date',
                
                // Address fields
                'street' => 'sometimes|required|string|max:255',
                'city' => 'sometimes|required|string|max:255',
                'state' => 'sometimes|required|string|max:255',
                'pincode' => 'sometimes|required|string|max:10',
                'area_locality' => 'sometimes|required|string|max:255',
                'google_location' => 'sometimes|required|string',
                'lat' => 'sometimes|required|string',
                'long' => 'sometimes|required|string',
                
                // Work details
                'role_designation' => 'sometimes|required|string|max:255',
                'joining_date' => 'sometimes|required|date',
                'salary' => 'sometimes|required|numeric',
                'pay_frequency' => 'sometimes|required|in:weekly,monthly,bi-weekly',
                
                // Emergency contact
                'emergency_contact_name' => 'sometimes|required|string|max:255',
                'emergency_contact_number' => 'sometimes|required|string|max:15',
                'relation' => 'sometimes|nullable|string|max:255',
                'salary_closing_date' => 'sometimes|nullable|integer|min:1|max:31',
                
                'aadhar_number' => 'sometimes|required|string|max:12|unique:users,aadhar_number,' . $id,
                'upi_id' => 'nullable|string|max:255',
                'staff_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
                
                // Private Documents
                'employer_aadhar_front' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
                'employer_aadhar_back' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
                'employer_police_verification' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
                'employer_other_doc' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
                'fir_document' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
            ]);

            if ($validator->fails()) {
                \Log::warning('Staff update validation failed', [
                    'action' => $logAction,
                    'staff_id' => $staff->id,
                    'validation_errors' => $validator->errors()->toArray(),
                    'timestamp' => now()->toDateTimeString()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            \Log::info('Staff update validation passed', [
                'action' => $logAction,
                'staff_id' => $staff->id,
                'timestamp' => now()->toDateTimeString()
            ]);
        } catch (\Exception $e) {
            \Log::error('Error during validation', [
                'action' => $logAction,
                'staff_id' => $staff->id,
                'error' => $e->getMessage(),
                'timestamp' => now()->toDateTimeString()
            ]);
            throw $e;
        }

        // Prepare update data
        $updateData = [];
        $updatedFields = [];
        $userFields = [
            'first_name', 'last_name', 'email', 'phone_number', 'gender',
            'dob', 'aadhar_number', 'upi_id', 'relation'
        ];

        try {
            \Log::info('Preparing user data for update', [
                'action' => $logAction,
                'staff_id' => $staff->id,
                'available_fields' => $userFields,
                'timestamp' => now()->toDateTimeString()
            ]);

            foreach ($userFields as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $request->$field;
                    $updatedFields[] = $field;
                }
            }

            // Update composite name if either first_name or last_name is provided
            if ($request->has('first_name') || $request->has('last_name')) {
                $firstName = $request->has('first_name') ? $request->first_name : $staff->first_name;
                $lastName = $request->has('last_name') ? $request->last_name : $staff->last_name;
                $updateData['name'] = trim($firstName . ' ' . $lastName);
                $updatedFields[] = 'name';
            }

            // Handle file uploads
            if ($request->hasFile('staff_photo')) {
                \Log::info('Processing staff photo upload', [
                    'action' => $logAction,
                    'staff_id' => $staff->id,
                    'old_photo_exists' => !empty($staff->image),
                    'timestamp' => now()->toDateTimeString()
                ]);
                
                $updateData['image'] = $this->uploadCloudary($request,"staff_photo","staff/photos");
                
                // $updateData['image'] = $request->file('staff_photo')->store('staff/photos', 'public');
                $updatedFields[] = 'image';
                
                \Log::info('Staff photo uploaded successfully', [
                    'action' => $logAction,
                    'staff_id' => $staff->id,
                    'new_path' => $updateData['image'],
                    'timestamp' => now()->toDateTimeString()
                ]);
            }

            // Handle Private Document uploads
            $docFields = [
                'employer_aadhar_front', 
                'employer_aadhar_back', 
                'employer_police_verification', 
                'employer_other_doc',
                'fir_document'
            ];

            foreach ($docFields as $docField) {
                if ($request->hasFile($docField)) {
                    \Log::info("Processing {$docField} upload", [
                        'action' => $logAction,
                        'staff_id' => $staff->id,
                        'timestamp' => now()->toDateTimeString()
                    ]);
                    
                    $updateData[$docField] = $this->uploadCloudary($request, $docField, "staff/private_docs");
                    $updatedFields[] = $docField;
                }
            }

            \Log::info('User update data prepared', [
                'action' => $logAction,
                'staff_id' => $staff->id,
                'fields_to_update' => $updatedFields,
                'update_count' => count($updatedFields),
                'timestamp' => now()->toDateTimeString()
            ]);
        } catch (\Exception $e) {
            \Log::error('Error preparing update data', [
                'action' => $logAction,
                'staff_id' => $staff->id,
                'error' => $e->getMessage(),
                'timestamp' => now()->toDateTimeString()
            ]);
            throw $e;
        }

        // Update staff user
        try {
            if (!empty($updateData)) {
                \Log::info('Updating staff user record', [
                    'action' => $logAction,
                    'staff_id' => $staff->id,
                    'update_data' => $updateData,
                    'timestamp' => now()->toDateTimeString()
                ]);

                $staff->update($updateData);

                \Log::info('Staff user record updated successfully', [
                    'action' => $logAction,
                    'staff_id' => $staff->id,
                    'updated_fields' => $updatedFields,
                    'timestamp' => now()->toDateTimeString()
                ]);
            } else {
                \Log::info('No user data to update', [
                    'action' => $logAction,
                    'staff_id' => $staff->id,
                    'timestamp' => now()->toDateTimeString()
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Error updating staff user record', [
                'action' => $logAction,
                'staff_id' => $staff->id,
                'error' => $e->getMessage(),
                'timestamp' => now()->toDateTimeString()
            ]);
            throw $e;
        }

        // Update address
        try {
            if ($request->hasAny(['street', 'city', 'state', 'pincode'])) {
                \Log::info('Processing address update', [
                    'action' => $logAction,
                    'staff_id' => $staff->id,
                    'address_fields_provided' => [
                        'street' => $request->has('street'),
                        'city' => $request->has('city'),
                        'state' => $request->has('state'),
                        'pincode' => $request->has('pincode')
                    ],
                    'timestamp' => now()->toDateTimeString()
                ]);

                $primaryAddress = $staff->addresses()->where('is_primary', true)->first();
                
                if ($primaryAddress) {
                    \Log::info('Existing primary address found, updating', [
                        'action' => $logAction,
                        'staff_id' => $staff->id,
                        'address_id' => $primaryAddress->id,
                        'timestamp' => now()->toDateTimeString()
                    ]);

                    $addressUpdateData = [
                        'street' => $request->street ?? $primaryAddress->street,
                        'city' => $request->city ?? $primaryAddress->city,
                        'state' => $request->state ?? $primaryAddress->state,
                        'pincode' => $request->pincode ?? $primaryAddress->pincode,
                        'area_locality' => $request->area_locality ?? $primaryAddress->area_locality,
                        'google_location' => $request->google_location ?? $primaryAddress->google_location,
                        'latitude' => $request->lat ?? $primaryAddress->latitude,
                        'longitude' => $request->long ?? $primaryAddress->longitude,
                    ];

                    $primaryAddress->update($addressUpdateData);

                    \Log::info('Address updated successfully', [
                        'action' => $logAction,
                        'staff_id' => $staff->id,
                        'address_id' => $primaryAddress->id,
                        'updated_fields' => array_keys($addressUpdateData),
                        'timestamp' => now()->toDateTimeString()
                    ]);
                } else {
                    \Log::info('No primary address found, creating new', [
                        'action' => $logAction,
                        'staff_id' => $staff->id,
                        'timestamp' => now()->toDateTimeString()
                    ]);

                    $newAddress = UserAddress::create([
                        'user_id' => $staff->id,
                        'street' => $request->street,
                        'city' => $request->city,
                        'state' => $request->state,
                        'pincode' => $request->pincode,
                        'area_locality' => $request->area_locality,
                        'google_location' => $request->google_location,
                        'latitude' => $request->lat,
                        'longitude' => $request->long,
                        'is_primary' => true
                    ]);

                    \Log::info('New address created successfully', [
                        'action' => $logAction,
                        'staff_id' => $staff->id,
                        'address_id' => $newAddress->id,
                        'timestamp' => now()->toDateTimeString()
                    ]);
                }
            } else {
                \Log::info('No address data provided, skipping address update', [
                    'action' => $logAction,
                    'staff_id' => $staff->id,
                    'timestamp' => now()->toDateTimeString()
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Error updating address', [
                'action' => $logAction,
                'staff_id' => $staff->id,
                'error' => $e->getMessage(),
                'timestamp' => now()->toDateTimeString()
            ]);
            throw $e;
        }

        // Update work info
        try {
            if ($request->hasAny(['role_designation', 'joining_date', 'salary', 'pay_frequency', 'working_days', 'emergency_contact_name', 'emergency_contact_number', 'salary_closing_date'])) {
                \Log::info('Processing work info update', [
                    'action' => $logAction,
                    'staff_id' => $staff->id,
                    'work_info_fields_provided' => [
                        'role_designation' => $request->has('role_designation'),
                        'joining_date' => $request->has('joining_date'),
                        'salary' => $request->has('salary'),
                        'pay_frequency' => $request->has('pay_frequency'),
                        'working_days' => $request->has('working_days'),
                        'emergency_contact_name' => $request->has('emergency_contact_name'),
                        'emergency_contact_number' => $request->has('emergency_contact_number'),
                        'salary_closing_date' => $request->has('salary_closing_date')
                    ],
                    'timestamp' => now()->toDateTimeString()
                ]);

                $workInfo = $staff->userWorkInfo;
                
                if ($workInfo) {
                    \Log::info('Existing work info found, updating', [
                        'action' => $logAction,
                        'staff_id' => $staff->id,
                        'work_info_id' => $workInfo->id,
                        'timestamp' => now()->toDateTimeString()
                    ]);

                    $workInfoUpdateData = [
                        'primary_role' => $request->role_designation ?? $workInfo->primary_role,
                        'joining_date' => $request->joining_date ?? $workInfo->joining_date,
                        'salary' => $request->salary ?? $workInfo->salary,
                        'pay_frequency' => $request->pay_frequency ?? $workInfo->pay_frequency,
                        'salary_closing_date' => $request->salary_closing_date ?? $workInfo->salary_closing_date,
                        'working_days' => $request->working_days ?? $workInfo->working_days,
                        'emergency_contact_name' => $request->emergency_contact_name ?? $workInfo->emergency_contact_name,
                        'emergency_contact_number' => $request->emergency_contact_number ?? $workInfo->emergency_contact_number,
                    ];

                    $workInfo->update($workInfoUpdateData);

                    \Log::info('Work info updated successfully', [
                        'action' => $logAction,
                        'staff_id' => $staff->id,
                        'work_info_id' => $workInfo->id,
                        'updated_fields' => array_keys($workInfoUpdateData),
                        'timestamp' => now()->toDateTimeString()
                    ]);
                } else {
                    \Log::info('No work info found, creating new', [
                        'action' => $logAction,
                        'staff_id' => $staff->id,
                        'timestamp' => now()->toDateTimeString()
                    ]);

                    // Create work info if doesn't exist
                    $newWorkInfo = UserWorkInfo::create([
                        'user_id' => $staff->id,
                        'primary_role' => $request->role_designation,
                        'joining_date' => $request->joining_date,
                        'salary' => $request->salary,
                        'pay_frequency' => $request->pay_frequency,
                        'working_days' => $request->working_days,
                        'emergency_contact_name' => $request->emergency_contact_name,
                        'emergency_contact_number' => $request->emergency_contact_number,
                    ]);

                    \Log::info('New work info created successfully', [
                        'action' => $logAction,
                        'staff_id' => $staff->id,
                        'work_info_id' => $newWorkInfo->id,
                        'timestamp' => now()->toDateTimeString()
                    ]);
                }
            } else {
                \Log::info('No work info data provided, skipping work info update', [
                    'action' => $logAction,
                    'staff_id' => $staff->id,
                    'timestamp' => now()->toDateTimeString()
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Error updating work info', [
                'action' => $logAction,
                'staff_id' => $staff->id,
                'error' => $e->getMessage(),
                'timestamp' => now()->toDateTimeString()
            ]);
            throw $e;
        }

        // Update household information
        try {
            if ($request->has('languages_spoken')) {
                \Log::info('Processing household information update', [
                    'action' => $logAction,
                    'staff_id' => $staff->id,
                    'has_languages_spoken' => true,
                    'timestamp' => now()->toDateTimeString()
                ]);

                UserHouseholdInformation::updateOrCreate(
                    ['user_id' => $staff->id],
                    ['languages_spoken' => $request->languages_spoken]
                );

                \Log::info('Household information updated successfully', [
                    'action' => $logAction,
                    'staff_id' => $staff->id,
                    'timestamp' => now()->toDateTimeString()
                ]);
            } else {
                \Log::info('No languages_spoken provided, skipping household information update', [
                    'action' => $logAction,
                    'staff_id' => $staff->id,
                    'timestamp' => now()->toDateTimeString()
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Error updating household information', [
                'action' => $logAction,
                'staff_id' => $staff->id,
                'error' => $e->getMessage(),
                'timestamp' => now()->toDateTimeString()
            ]);
            // Don't throw as household info is optional
        }

        DB::commit();

        \Log::info('Staff update by ID completed successfully', [
            'action' => $logAction,
            'staff_id' => $staff->id,
            'staff_name' => $staff->name,
            'updated_by' => $authUser->id,
            'updated_by_name' => $authUser->name,
            'transaction_committed' => true,
            'timestamp' => now()->toDateTimeString(),
            'summary' => [
                'user_fields_updated' => $updatedFields,
                'address_updated' => $request->hasAny(['street', 'city', 'state', 'pincode']),
                'work_info_updated' => $request->hasAny(['role_designation', 'joining_date', 'salary', 'pay_frequency', 'working_days', 'emergency_contact_name', 'emergency_contact_number']),
                'household_info_updated' => $request->has('languages_spoken')
            ]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Staff member updated successfully',
            'data' => $staff->fresh(['addresses', 'userWorkInfo', 'kycInformation'])
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        
        \Log::error('Staff update by ID failed - Transaction rolled back', [
            'action' => $logAction,
            'staff_id' => $id,
            'error_message' => $e->getMessage(),
            'error_trace' => $e->getTraceAsString(),
            'requested_by' => $authUser ? $authUser->id : 'unknown',
            'transaction_rolled_back' => true,
            'timestamp' => now()->toDateTimeString()
        ]);
        try {
             file_put_contents(storage_path('logs/debug_error.log'), date('Y-m-d H:i:s') . " - Error in updateStaff (ID {$id}): " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n", FILE_APPEND);
        } catch (\Exception $writeErr) {}

        return response()->json([
            'success' => false,
            'message' => 'Failed to update staff member',
            'error' => env('APP_DEBUG') ? $e->getMessage() : 'Internal server error'
        ], 500);
    }
}





    /**
     * Delete staff member (soft delete)
     */
    public function deleteStaff($id)
    {
        DB::beginTransaction();
        
        try {
            $staff = User::where('is_staff_added', 1)
                ->where('added_by', Auth::guard('api')->user()->id)
                ->where('id', $id)
                ->first();

            if (!$staff) {
                return response()->json([
                    'success' => false,
                    'message' => 'Staff member not found'
                ], 404);
            }

            // Soft delete the staff
            $staff->update([
                'is_active' => 0,
                'is_deleted' => 1,
                'deleted_at' => now(),
                'deleted_by' => Auth::guard('api')->user()->id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Staff member deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete staff member',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activate/Deactivate staff member
     */
    public function toggleStaffStatus($id)
    {
        try {
            $staff = User::where('is_staff_added', 1)
                ->where('added_by', Auth::guard('api')->user()->id)
                ->where('id', $id)
                ->first();

            if (!$staff) {
                return response()->json([
                    'success' => false,
                    'message' => 'Staff member not found'
                ], 404);
            }

            $newStatus = !$staff->is_active;
            $staff->update(['is_active' => $newStatus]);

            return response()->json([
                'success' => true,
                'message' => $newStatus ? 'Staff member activated successfully' : 'Staff member deactivated successfully',
                'data' => [
                    'is_active' => $newStatus
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update staff status',
                'error' => $e->getMessage()
            ], 500);
        }
    }


     public function designationsIndex()
    {
        $designations = Designation::where('status', 1)
            ->orderBy('designation_name', 'ASC')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $designations
        ]);
    }



    public function loginAdmin(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'email'    => 'required|email',
                'password' => 'required|min:8',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)
            ->where('is_deleted', 0)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'msg'    => 'Invalid email or password.'
            ], 401);
        }

        if ($user->is_active === false || (int) $user->is_active === 0) {
            return response()->json([
                'status' => 'error',
                'msg'    => 'This admin account is inactive.'
            ], 403);
        }
        
        try {
            // auth:api routes in this project expect Passport access tokens
            $token = $user->createToken('AuthToken')->plainTextToken;
            $permissions = is_array($user->admin_permissions) ? $user->admin_permissions : [];
            return response()->json([
                'status' => 'success',
                'msg'    => 'Login successful.',
                'token'  => $token,
                'user'   => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->is_admin_panel_user ? 'Sub Admin' : 'Admin',
                    'is_admin_panel_user' => (bool) $user->is_admin_panel_user,
                    'permissions' => !empty($permissions) ? $permissions : [
                        'dashboard',
                        'house_owners',
                        'staff',
                        'jobs',
                        'roles',
                        'membership',
                        'reports',
                        'blacklist',
                        'sub_admins',
                        'settings',
                    ],
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'msg'    => 'Authentication system not configured properly.'
            ], 500);
        }
    }

    public function getMyWork(Request $request)
    {
        try {
            $user = Auth::guard('api')->user();
            $userDetails = User::with([
                'addresses','lastExp','lastsalary','userWorkInfo','lastExp','leaveRequests','addedByUser','addedByUser.householdInformation','addedByUser.addresses'
            ])->find($user->id);

            $attendanceSummary = DB::table('attendance')
                ->select('status', DB::raw('COUNT(*) as total'))
                ->where('staff_id', $user->id)
                ->whereMonth('date', date('m'))
                ->whereYear('date', date('Y'))
                ->groupBy('status')
                ->get();
            $leaveSummary = LeaveRequest::select(
                'leave_types.name as leave_type_name',
                DB::raw('COUNT(leave_requests.id) as total')
            )
            ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->where('leave_requests.created_by', $user->id)
            ->groupBy('leave_types.name')
            ->get();

            $jobApplications = DB::table('job_applications')
            ->where('user_id', $user->id)
            ->whereIn('application_status', ['accepted', 'approved', 'active', 'hired'])
            ->get();

   
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }
            // Return user data without sensitive information
            return response()->json([
                'success' => true,
                'message' => 'Get my work successfully',
                'data' => $userDetails,
                'attendanceSummary' => $attendanceSummary,
                'leaveSummary' => $leaveSummary,
                "jobApplications" => $jobApplications
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's referral code and details
     */
    public function getReferralCode(ReferralPointService $referralPoints)
    {
        try {
            $user = Auth::guard('api')->user();

            // Generate/Refresh referral code if not exists, if expired, or if expiry date is missing (for old users)
            if (empty($user->referral_code) || empty($user->referral_code_expires_at) || $user->referral_code_expires_at->isPast()) {

                do {
                    $code = strtoupper(Str::random(8));
                } while (
                    User::where('referral_code', $code)->exists()
                );

                $user->referral_code = $code;
                $user->referral_code_expires_at = now()->addDays(7); // Valid for 7 days
                $user->save();
            }

            $referralCount = ReferralReward::where('referrer_id', $user->id)->count();
            $totalEarnings = $user->referral_earnings ?? 0;
            $isStaff = (int) $user->user_role_id === 2;
            $pointsPerReferral = $isStaff ? $referralPoints->pointsPerReferral() : 0;
            $pointsPerCredit = $isStaff ? $referralPoints->pointsPerCredit() : 0;
            $conversion = $isStaff
                ? $referralPoints->conversion((float) $totalEarnings, $pointsPerCredit)
                : ['credits' => 0];
            $redeemedCredits = $isStaff
                ? (int) ReferralRedemption::where('user_id', $user->id)->sum('credits_granted')
                : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'referral_code' => $user->referral_code,
                    'referral_code_expires_at' => $user->referral_code_expires_at ? $user->referral_code_expires_at->toDateTimeString() : null,
                    'referral_link' => config('app.url') . '/signup?ref=' . $user->referral_code,
                    'referral_count' => $referralCount,
                    'total_earnings' => $totalEarnings,
                    'points_balance' => (float) $totalEarnings,
                    'points_per_referral' => $pointsPerReferral,
                    'points_per_credit' => $pointsPerCredit,
                    'redeemable_credits' => $conversion['credits'],
                    'redeemed_credits' => $redeemedCredits,
                    'wallet_balance' => (float) ($user->wallet_balance ?? 0),
                    'is_staff' => $isStaff,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get referral code',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Apply referral code during signup
     */
    public function applyReferralCode(Request $request, ReferralPointService $referralPoints)
    {
        // Trim and uppercase the referral code
        $referralCode = strtoupper(trim($request->referral_code ?? ''));
        
        $validator = Validator::make(['referral_code' => $referralCode], [
            'referral_code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Referral code is required',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $authenticatedUser = Auth::guard('api')->user();

            $result = DB::transaction(function () use ($authenticatedUser, $referralCode, $referralPoints) {
                $user = User::whereKey($authenticatedUser->id)->lockForUpdate()->firstOrFail();

                if (!empty($user->referred_by)) {
                    return ['error' => 'Referral code already applied', 'status' => 400];
                }

                $referrer = User::whereRaw('UPPER(referral_code) = ?', [$referralCode])
                    ->lockForUpdate()
                    ->first();

                if (!$referrer || ($referrer->referral_code_expires_at && $referrer->referral_code_expires_at->isPast())) {
                    return ['error' => 'Invalid or expired referral code', 'status' => 404];
                }

                if ($referrer->id === $user->id) {
                    return ['error' => 'Cannot use your own referral code', 'status' => 400];
                }

                $user->referred_by = $referrer->id;
                $user->save();

                if ((int) $referrer->user_role_id === 2) {
                    $rewardAmount = $referralPoints->pointsPerReferral();
                } else {
                    $points = setting('points_per_action');
                    $rewardAmount = (float) ($points['value'] ?? 10);
                }

                $referrer->increment('referral_earnings', $rewardAmount);

                ReferralReward::create([
                    'referrer_id' => $referrer->id,
                    'referred_id' => $user->id,
                    'reward_amount' => $rewardAmount,
                    'reward_type' => 'signup',
                    'is_credited' => true,
                    'credited_at' => now(),
                ]);

                return [
                    'points_awarded' => $rewardAmount,
                    'staff_referrer' => (int) $referrer->user_role_id === 2,
                ];
            }, 3);

            if (isset($result['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'],
                ], $result['status']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Referral code applied successfully',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to apply referral code',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get referral history/earnings
     */
    public function getReferralHistory()
    {
        try {
            $user = Auth::guard('api')->user();

            $referrals = ReferralReward::with('referred:id,name,first_name,last_name,phone_number,image,created_at')
                ->where('referrer_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($reward) {
                    $referred = $reward->referred;
                    $fullName = null;
                    if ($referred) {
                        $fullName = trim(($referred->first_name ?? '') . ' ' . ($referred->last_name ?? ''));
                        if (empty($fullName) || $fullName === 'User') {
                            $fullName = $referred->name ?? 'User';
                        }
                    }
                    return [
                        'id' => $reward->id,
                        'referred_user' => $referred ? [
                            'id' => $referred->id,
                            'name' => $fullName,
                            'phone_number' => $referred->phone_number,
                            'image' => $referred->image,
                            'joined_at' => $referred->created_at,
                        ] : null,
                        'reward_amount' => $reward->reward_amount,
                        'reward_type' => $reward->reward_type,
                        'is_credited' => $reward->is_credited,
                        'status' => $reward->is_credited ? 'completed' : 'pending',
                        'credited_at' => $reward->credited_at,
                        'created_at' => $reward->created_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'total_earnings' => $referrals->sum('reward_amount') ?? 0,
                    'referral_count' => $referrals->count(),
                    'referrals' => $referrals,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get referral history',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Redeem referral points. Staff receive job credits; owners receive plan discount.
     */
    public function applyReferCredit(ReferralPointService $referralPoints)
    {
        try {
            $authenticatedUser = Auth::guard('api')->user();

            if ((int) $authenticatedUser->user_role_id === 2) {
                $result = DB::transaction(function () use ($authenticatedUser, $referralPoints) {
                    $user = User::whereKey($authenticatedUser->id)->lockForUpdate()->firstOrFail();
                    $availablePoints = (float) ($user->referral_earnings ?? 0);
                    $conversion = $referralPoints->conversion($availablePoints);

                    if ($conversion['credits'] < 1) {
                        return [
                            'error' => 'You need at least ' . $conversion['points_per_credit'] . ' points to redeem 1 credit.',
                            'status' => 422,
                            'points_balance' => $availablePoints,
                            'points_per_credit' => $conversion['points_per_credit'],
                        ];
                    }

                    $user->referral_earnings = $conversion['points_remaining'];
                    $user->wallet_balance = (float) ($user->wallet_balance ?? 0) + $conversion['credits'];
                    $user->save();

                    ReferralRedemption::create([
                        'user_id' => $user->id,
                        'points_redeemed' => $conversion['points_used'],
                        'credits_granted' => $conversion['credits'],
                        'points_per_credit' => $conversion['points_per_credit'],
                    ]);

                    return [
                        'points_redeemed' => $conversion['points_used'],
                        'credits_granted' => $conversion['credits'],
                        'points_balance' => $conversion['points_remaining'],
                        'points_per_credit' => $conversion['points_per_credit'],
                        'redeemed_credits' => (int) ReferralRedemption::where('user_id', $user->id)->sum('credits_granted'),
                        'wallet_balance' => (float) $user->wallet_balance,
                    ];
                });

                if (isset($result['error'])) {
                    return response()->json([
                        'success' => false,
                        'message' => $result['error'],
                        'data' => $result,
                    ], $result['status']);
                }

                return response()->json([
                    'success' => true,
                    'message' => $result['points_redeemed'] . ' points redeemed for ' . $result['credits_granted'] . ' credits.',
                    'data' => $result,
                ]);
            }

            $result = DB::transaction(function () use ($authenticatedUser) {
                $user = User::whereKey($authenticatedUser->id)->lockForUpdate()->firstOrFail();
                $availableEarnings = (float) ($user->referral_earnings ?? 0);

                if ($availableEarnings <= 0) {
                    return ['error' => 'No referral points available to withdraw.', 'status' => 400];
                }

                $ratioSetting = setting('point_to_inr_ratio');
                $ratio = isset($ratioSetting['value']) ? (float) $ratioSetting['value'] : 1.0;
                if ($ratio <= 0) {
                    return ['error' => 'Referral conversion rate is not configured.', 'status' => 422];
                }
                $discountAmount = $availableEarnings * $ratio;

                $user->wallet_balance = (float) ($user->wallet_balance ?? 0) + $discountAmount;
                $user->referral_earnings = 0;
                $user->save();

                return [
                    'wallet_balance' => (float) $user->wallet_balance,
                    'referral_earnings' => (float) $user->referral_earnings,
                    'discount_amount' => $discountAmount,
                ];
            });

            if (isset($result['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'],
                ], $result['status']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Points successfully converted to INR ' . $result['discount_amount'] . ' discount. This will be applied to your next plan purchase.',
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            \Log::error('Apply Refer Credit Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to apply points',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function otptest()
    {
        $number = "919725366212";

        $response = $this->sendOtp($number);

        return response()->json($response);
    }

    private function getReusableOrNewOtp(User $user)
    {
        if (!empty($user->verification_code) && !empty($user->verification_code_sent_time)) {
            try {
                $sentAt = Carbon::parse($user->verification_code_sent_time);
                if ($sentAt->diffInMinutes(now()) < self::OTP_VALID_MINUTES) {
                    return $user->verification_code;
                }
            } catch (\Throwable $th) {
                \Log::warning('Failed to parse verification_code_sent_time', [
                    'user_id' => $user->id ?? null,
                    'error' => $th->getMessage(),
                ]);
            }
        }

        return rand(100000, 999999);
    }

    private function parseDateToYmd($dateString)
    {
        if (empty($dateString)) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($dateString)->format('Y-m-d');
        } catch (\Exception $e) {
            try {
                return \Carbon\Carbon::createFromFormat('d/m/y', $dateString)->format('Y-m-d');
            } catch (\Exception $ex) {
                try {
                    return \Carbon\Carbon::createFromFormat('d-m-Y', $dateString)->format('Y-m-d');
                } catch (\Exception $ex2) {
                    try {
                        return \Carbon\Carbon::createFromFormat('d/m/Y', $dateString)->format('Y-m-d');
                    } catch (\Exception $ex3) {
                        return null;
                    }
                }
            }
        }
    }

}

