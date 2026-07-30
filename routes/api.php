 <?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\SubServiceController;
use App\Http\Controllers\Api\SupportController;
use App\Http\Controllers\Api\PromoCodeController;
use App\Http\Controllers\Api\BankAccountController;
use App\Http\Controllers\Api\FaqSupportController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\AnalyticsController;
// use App\Http\Controllers\Api\NotificationShortcutController;
use App\Http\Controllers\Api\MailShortcutController;
use App\Http\Controllers\Api\KycVerificationController;
use App\Http\Controllers\Api\NotificationShortcutController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\JobApplicationController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\SalaryController;
use App\Http\Controllers\Api\AttendanceController;
// start of new additions
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\HouseOwnerController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\JobApplyLimitController;
use App\Http\Controllers\Api\VoiceTranscriptionController;
use App\Http\Controllers\Api\LegalConsentController;
use App\Http\Controllers\Api\PolicyController;


Route::get('/debug-logs', function() {
    if (!config('app.debug')) {
        return response()->json(['message' => 'Not available in production'], 403);
    }
    $laravelLog = storage_path('logs/laravel.log');
    $debugLog = storage_path('logs/debug_error.log');
    $hjLog = storage_path('logs/hj.txt');
    $output = '';
    
    // Check log channel
    $output .= "LOG_CHANNEL: " . config('logging.default') . "\n";
    $output .= "LOG_LEVEL: " . config('logging.channels.single.level') . "\n\n";

    // Test file writing
    $testFile = storage_path('logs/test_write.txt');
    if (@file_put_contents($testFile, "TEST WRITE SUCCESS AT " . date('Y-m-d H:i:s')) !== false) {
        $output .= "=== FILE WRITE TEST: SUCCESS ===\n\n";
        @unlink($testFile);
    } else {
        $output .= "=== FILE WRITE TEST: FAILED ===\n\n";
    }

    // List all files in storage/logs
    $logDir = storage_path('logs');
    if (is_dir($logDir)) {
        $files = scandir($logDir);
        $output .= "=== FILES IN storage/logs ===\n" . implode("\n", $files) . "\n\n";
    } else {
        $output .= "=== storage/logs IS NOT A DIRECTORY ===\n\n";
    }

    if (file_exists($debugLog)) {
        $lines = file($debugLog);
        $lastLines = array_slice($lines, -250);
        $output .= "=== DEBUG ERROR LOG ===\n" . implode('', $lastLines) . "\n\n";
    } else {
        $output .= "=== DEBUG ERROR LOG NOT FOUND ===\n\n";
    }
    
    if (file_exists($laravelLog)) {
        $lines = file($laravelLog);
        $lastLines = array_slice($lines, -100);
        $output .= "=== LARAVEL LOG (Last 100 lines) ===\n" . implode('', $lastLines) . "\n\n";
    } else {
        $output .= "=== LARAVEL LOG NOT FOUND ===\n\n";
    }

    if (file_exists($hjLog)) {
        $lines = file($hjLog);
        $lastLines = array_slice($lines, -100);
        $output .= "=== HJ LOG (Last 100 lines) ===\n" . implode('', $lastLines) . "\n\n";
    } else {
        $output .= "=== HJ LOG NOT FOUND ===\n\n";
    }
    
    return response($output, 200, ['Content-Type' => 'text/plain']);
});
use App\Http\Controllers\Api\DashboardController;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\Api\AdminSalaryController;
use App\Http\Controllers\Api\TerminationController;
use App\Http\Controllers\Api\AdvanceController;
use App\Http\Controllers\Api\BlacklistController;
use App\Http\Controllers\adminpnlx\TransactionController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
use Illuminate\Support\Facades\File;

Route::get('/logs', function () {
    if (!config('app.debug')) {
        return response()->json(['message' => 'Not available in production'], 403);
    }
    $path = storage_path('logs/laravel.log');

    if (!File::exists($path)) {
        return response()->json([
            'message' => 'Log file not found'
        ], 404);
    }

    $logs = File::get($path);

    return response()->json([
        'logs' => $logs
    ]);
});

Route::get('/migrate-plans', function (\Illuminate\Http\Request $request) {
    if (!config('app.debug')) {
        return response()->json(['message' => 'Not available in production'], 403);
    }
    if ($request->input('debug') === 'true') {
        $jobId = 1;
        try {
            $applications = \App\Models\JobApplication::with([
                                'user',
                                'user.userWorkInfo',
                                'user.reviewsReceived',
                                'user.addresses'
                             ])
                             ->where('job_id', $jobId)
                             ->orderBy('created_at', 'desc')
                             ->get();
                             
            $singleApp = \App\Models\JobApplication::find(1);
            $explicitUser = $singleApp ? $singleApp->user : null;
            
            return response()->json([
                'status' => 'success',
                'applications' => $applications,
                'single_app' => $singleApp,
                'explicit_user' => $explicitUser,
                'all_applications_count' => \App\Models\JobApplication::count(),
                'jobs' => \App\Models\Job::all(),
                'users' => \App\Models\User::orderBy('id', 'desc')->take(30)->get()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }


    }

    // Show ALL plans including soft-deleted for debugging
    $allPlans = \App\Models\Subscription::withTrashed()->get(['id', 'subscription_name', 'price', 'type', 'deleted_at']);
    $plans    = \App\Models\Subscription::all();
    
    // Find standard plan (price = 0, not soft-deleted)
    $standardPlan = \App\Models\Subscription::where('price', 0)->first();


    
    if (!$standardPlan) {
        return response()->json([
            'status'    => 'error',
            'message'   => 'No free Standard plan (price = 0) found in subscriptions table. Check all_plans below including soft-deleted.',
            'all_plans' => $allPlans,
            'plans'     => $plans,
        ]);
    }

    // House owners without ANY subscription record
    $houseOwnerRole = \App\Models\Role::where('slug', 'house_owner')->orWhere('slug', 'houseowner')->orWhere('name', 'like', '%house%owner%')->first();
    $houseOwnerRoleId = $houseOwnerRole ? $houseOwnerRole->id : 3;

    $houseOwnersWithNoSub = \App\Models\User::where('user_role_id', $houseOwnerRoleId)
        ->whereDoesntHave('subscriptionUsers')
        ->pluck('id');

    // Existing active subscriptions that are on old/wrong plan
    $activeSubs = \App\Models\SubscriptionUser::where('status', 'active')->get();
    
    $count       = 0;
    $newCount    = 0;

    if ($request->input('confirm') === 'true') {
        // Migrate existing active subscriptions to standard plan
        foreach ($activeSubs as $sub) {
            if ($sub->subscription_id !== $standardPlan->id) {
                $sub->update([
                    'subscription_id' => $standardPlan->id,
                    'amount'          => $standardPlan->price
                ]);
                $count++;
            }
        }
        // Create new standard plan subscription for house owners with no subscription
        foreach ($houseOwnersWithNoSub as $userId) {
            \App\Models\SubscriptionUser::create([
                'user_id'        => $userId,
                'subscription_id'=> $standardPlan->id,
                'order_id'       => 'AUTO' . time() . $userId,
                'order_number'   => 'AUTO' . time() . $userId,
                'amount'         => 0,
                'currency'       => 'INR',
                'payment_status' => 'free',
                'payment_mode'   => 'free',
                'role'           => $houseOwnerRoleId,
                'status'         => 'active',
                'start_date'     => now(),
                'end_date'       => now()->addYears(10),
                'user_limit'     => 0,
                'job_user_limit' => 0,
            ]);
            $newCount++;
        }
        return response()->json([
            'status'                   => 'success',
            'message'                  => "Migration complete!",
            'migrated_existing'        => $count,
            'new_standard_assigned'    => $newCount,
            'standard_plan'            => $standardPlan,
        ]);
    }
    
    return response()->json([
        'status'                      => 'preview',
        'message'                     => 'Pass ?confirm=true to execute the migration.',
        'standard_plan_found'         => [
            'id'    => $standardPlan->id,
            'name'  => $standardPlan->subscription_name,
            'price' => $standardPlan->price
        ],
        'existing_active_subs_to_migrate' => $activeSubs->where('subscription_id', '!=', $standardPlan->id)->count(),
        'house_owners_with_no_sub'    => count($houseOwnersWithNoSub),
        'total_active_subscriptions'  => count($activeSubs),
        'all_plans_incl_deleted'      => $allPlans,
        'active_plans'                => $plans,
    ]);
});

// Route::get('apitestttt', [UserController::class, 'otptest']);


Route::get('/', function () {
    return response()->json(['message' => 'API is working successfully', 'status' => 200]);
});

Route::get('/fixissue', function () {
    if (!config('app.debug')) {
        return response()->json(['message' => 'Not available in production'], 403);
    }
    Artisan::call('optimize:clear');
    return response()->json(['message' => 'Cache cleared successfully', 'status' => 200]);
});

Route::get('/freshdata', function () {
    if (!config('app.debug')) {
        return response()->json(['message' => 'Not available in production'], 403);
    }
    Artisan::call('optimize:clear');
    return response()->json(['message' => 'Cache cleared successfully', 'status' => 200]);
});


// TEMPORARY FIX: Run this to generate keys on Railway
Route::get('/fix-passport', function () {
    if (!config('app.debug')) {
        return response()->json(['message' => 'Not available in production'], 403);
    }
    $output = "Starting diagnostics...<br>";
    try {
        // 1. Check if vendor directory exists
        if (!file_exists(base_path('vendor/laravel/passport'))) {
            return "ERROR: vendor/laravel/passport directory is missing. Please run composer install on the server.";
        }
        $output .= "Vendor directory found.<br>";

        // 2. Clear caches first
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        $output .= "Caches cleared.<br>";

        // 3. Check for keys and create them manually if artisan fails
        $privateKey = storage_path('oauth-private.key');
        $publicKey = storage_path('oauth-public.key');

        if (!file_exists($privateKey) || !file_exists($publicKey)) {
            try {
                Artisan::call('passport:keys', ['--force' => true]);
                $output .= "Passport keys generated via Artisan.<br>";
            } catch (\Exception $e) {
                $output .= "Artisan passport:keys failed. Error: " . $e->getMessage() . "<br>";
                // Basic check for openssl to see if we can generate manually
                if (function_exists('openssl_pkey_new')) {
                    $res = openssl_pkey_new([
                        "private_key_bits" => 4096,
                        "private_key_type" => OPENSSL_KEYTYPE_RSA,
                    ]);
                    openssl_pkey_export($res, $privKey);
                    $pubKey = openssl_pkey_get_details($res);
                    $pubKey = $pubKey["key"];
                    file_put_contents($privateKey, $privKey);
                    file_put_contents($publicKey, $pubKey);
                    chmod($privateKey, 0600);
                    chmod($publicKey, 0600);
                    $output .= "Passport keys generated MANUALLY via OpenSSL.<br>";
                } else {
                    $output .= "ERROR: openssl extension is missing. Cannot generate keys manually.<br>";
                }
            }
        } else {
            $output .= "Passport keys already exist.<br>";
        }

        // 4. Check for Personal Access Client mapping
        try {
            // Check if tables exist
            if (!Schema::hasTable('oauth_clients')) {
                Artisan::call('migrate', ['--force' => true]);
                $output .= "Migrations run.<br>";
            }

            $client = DB::table('oauth_clients')->where('personal_access_client', 1)->first();
            if (!$client) {
                // Manually insert personal access client
                $clientId = DB::table('oauth_clients')->insertGetId([
                    'name' => 'Sahayya Personal Access Client',
                    'secret' => Str::random(40),
                    'provider' => null,
                    'redirect' => 'http://localhost',
                    'personal_access_client' => 1,
                    'password_client' => 0,
                    'revoked' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $output .= "Manual Personal Access Client created (ID $clientId).<br>";

                // Insert into oauth_personal_access_clients
                DB::table('oauth_personal_access_clients')->insert([
                    'client_id' => $clientId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $output .= "Oauth Personal Access client mapping added.<br>";
            } else {
                $output .= "Personal access client already exists (ID $client->id).<br>";
            }
        } catch (\Exception $e) {
            $output .= "Failed to create client: " . $e->getMessage() . "<br>";
        }

        return $output . "<br><b>SUCCESS! Diagnostics completed. Please try Signup/OTP now.</b>";

    } catch (\Exception $e) {
        return $output . 'CRITICAL EXCEPTION: ' . $e->getMessage() . 
               '<br><br>Stack Trace: <pre>' . $e->getTraceAsString() . '</pre>';
    }
});

Route::post('customer/login', [UserController::class, 'loginCustomer']);
Route::get('/designations-list', [UserController::class, 'designationsIndex']);

Route::get('/subscriptions', [SubscriptionController::class, 'index']);

// Deep DB inspection
Route::get('/debug-attendance-today', function () {
    if (!config('app.debug')) {
        return response()->json(['message' => 'Not available in production'], 403);
    }
    try {
        $nowIST    = \Carbon\Carbon::now('Asia/Kolkata')->toDateTimeString();
        $nowUTC    = \Carbon\Carbon::now()->toDateTimeString();
        $todayIST  = \Carbon\Carbon::now('Asia/Kolkata')->toDateString();
        $todayUTC  = \Carbon\Carbon::now()->toDateString();

        // All attendance last 3 days
        $recentAtt = \App\Models\Attendance::where('date', '>=', '2026-04-16')
            ->orderBy('date')->orderBy('id')
            ->get(['id','staff_id','date','status','description','created_at']);

        // All hired staff (is_staff_added=1) with their employer info
        $hiredStaff = \App\Models\User::with(['userWorkInfo'])
            ->where('user_role_id', 2)
            ->where('is_staff_added', 1)
            ->whereNotNull('added_by')
            ->get()
            ->map(fn($u) => [
                'id'          => $u->id,
                'name'        => $u->name,
                'added_by'    => $u->added_by,
                'emp_auto'    => \App\Models\User::find($u->added_by)?->auto_attendence,
                'working_days'=> $u->userWorkInfo?->working_days,
            ]);

        return response()->json([
            'server_time_IST' => $nowIST,
            'server_time_UTC' => $nowUTC,
            'today_IST'       => $todayIST,
            'today_UTC'       => $todayUTC,
            'recent_attendance_apr16_18' => $recentAtt,
            'hired_staff'     => $hiredStaff,
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
    }
});

// Check raw attendance table for today
Route::get('/debug-staff-data', function () {
    if (!config('app.debug')) {
        return response()->json(['message' => 'Not available in production'], 403);
    }
    $staff = \App\Models\User::with(['userWorkInfo'])
        ->where('user_role_id', '2')
        ->get()
        ->map(function($u) {
            $employer = null;
            $empId = $u->added_by ?? $u->parent_user_id ?? null;
            if ($empId) {
                $emp = \App\Models\User::find($empId);
                $employer = $emp ? ['id'=>$emp->id,'name'=>$emp->name,'auto_attendence'=>$emp->auto_attendence] : null;
            }
            $attendance17 = \App\Models\Attendance::where('staff_id',$u->id)->where('date','2026-04-17')->first();
            $attendance18 = \App\Models\Attendance::where('staff_id',$u->id)->where('date','2026-04-18')->first();
            return [
                'id'             => $u->id,
                'name'           => $u->name,
                'role_id'        => $u->user_role_id,
                'is_active'      => $u->is_active,
                'is_deleted'     => $u->is_deleted,
                'is_staff_added' => $u->is_staff_added,
                'added_by'       => $u->added_by,
                'parent_user_id' => $u->parent_user_id,
                'employer'       => $employer,
                'working_days'   => $u->userWorkInfo?->working_days,
                'att_apr17'      => $attendance17?->status ?? 'NOT MARKED',
                'att_apr18'      => $attendance18?->status ?? 'NOT MARKED',
            ];
        });
    return response()->json(['staff' => $staff, 'total' => $staff->count()]);
});

// Secret key protected - sirf tumhare liye
Route::get('/run-auto-attendance/{secret}', function ($secret) {
    if ($secret !== 'sahayya2026secure') {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    try {
        // Use IST timezone for correct date/day
        $today = \Carbon\Carbon::now('Asia/Kolkata')->toDateString();
        $todayDayName = strtolower(\Carbon\Carbon::now('Asia/Kolkata')->format('l'));
        $marked = [];
        $skipped = [];
        $skipped_reasons = [];

        // Load all staff (role 2) who are active and added to a household
        $users = \App\Models\User::with(['userWorkInfo'])
            ->where('user_role_id', '2')
            ->where('is_active', 1)
            ->where('is_deleted', 0)
            ->where('is_staff_added', 1) // Only hired staff
            ->get();

        foreach ($users as $user) {
            try {
                // Employer = added_by (primary) or parent_user_id (fallback)
                $employerId = $user->added_by ?? $user->parent_user_id ?? null;
                if (!$employerId) {
                    $skipped_reasons[] = $user->name . ' (no employer linked/not hired)';
                    continue;
                }

                // Load employer and check auto_attendence
                $employer = \App\Models\User::find($employerId);
                if (!$employer) {
                    $skipped_reasons[] = $user->name . ' (employer record not found)';
                    continue;
                }

                $autoEnabled = ($employer->auto_attendence == "1" || $employer->auto_attendence == 1 || $employer->auto_attendence === true);
                if (!$autoEnabled) {
                    $skipped_reasons[] = $user->name . ' (auto-present OFF for employer: ' . $employer->name . ')';
                    continue;
                }

                // Working days check
                $rawDays = $user->userWorkInfo?->working_days;
                if (empty($rawDays)) {
                    // Default to Mon-Sat if not specified
                    $rawDays = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
                }
                
                $workingDays3 = array_map(fn($d) => substr(strtolower(trim($d)), 0, 3), $rawDays);
                $today3       = substr($todayDayName, 0, 3);
                
                if (!in_array($today3, $workingDays3)) {
                    $skipped_reasons[] = $user->name . ' (today is ' . $todayDayName . ', not in working days: ' . implode(',', $workingDays3) . ')';
                    continue;
                }

                // Check if already marked
                $existingAtt = \App\Models\Attendance::where('staff_id', $user->id)
                    ->where('date', $today)
                    ->first();

                if ($existingAtt) {
                    $skipped_reasons[] = $user->name . ' (already marked as ' . $existingAtt->status . ')';
                    continue;
                }

                // Create new attendance record
                \App\Models\Attendance::create([
                    'staff_id'      => $user->id,
                    'date'          => $today,
                    'check_in_time' => '07:00:00',
                    'status'        => 'present',
                    'description'   => 'Auto-marked by system trigger',
                    'processed_by'  => 1,
                ]);
                $marked[] = $user->name;

            } catch (\Exception $e) {
                $skipped_reasons[] = $user->name . ' (Error: ' . $e->getMessage() . ')';
            }
        }

        return response()->json([
            'success' => true,
            'message' => '✅ Auto attendance trigger process complete',
            'date' => $today,
            'day' => $todayDayName,
            'marked_count' => count($marked),
            'marked_list' => $marked,
            'skipped_reasons' => $skipped_reasons,
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});
// One-shot: clean duplicate attendance rows AND add unique index.
// Safe to call multiple times (migration guards against re-adding index).
Route::get('/fix-attendance-duplicates/{secret}', function ($secret) {
    if ($secret !== 'sahayya2026secure') {
        return response()->json(['error' => 'Unauthorized'], 401);
    }
    try {
        // Count dupes before
        $before = \DB::select('
            SELECT COUNT(*) as cnt FROM attendance a1
            INNER JOIN attendance a2 ON a1.staff_id=a2.staff_id AND a1.date=a2.date AND a1.id>a2.id
        ');
        $dupesBefore = $before[0]->cnt ?? 0;

        // Delete dupes (keep lowest id per staff+date)
        \DB::statement('
            DELETE a1 FROM attendance a1
            INNER JOIN attendance a2 ON a1.staff_id=a2.staff_id AND a1.date=a2.date AND a1.id>a2.id
        ');

        // Add unique index if missing
        $indexes = collect(\DB::select('SHOW INDEX FROM attendance'))
            ->pluck('Key_name')->unique()->toArray();
        $indexAdded = false;
        if (!in_array('attendance_staff_id_date_unique', $indexes)) {
            \DB::statement('ALTER TABLE attendance ADD UNIQUE attendance_staff_id_date_unique (staff_id, date)');
            $indexAdded = true;
        }

        return response()->json([
            'success'           => true,
            'duplicates_deleted'=> (int) $dupesBefore,
            'unique_index_added'=> $indexAdded,
            'message'           => 'Attendance duplicates cleaned + unique index in place.',
        ]);
    } catch (\Throwable $e) {
        return response()->json(['success'=>false, 'error'=>$e->getMessage()], 500);
    }
});

// One-shot: delete attendance records for a given date that belong to staff
// whose working_days don't include that day (or whose working_days are null →
// default Mon-Sat, so Sunday is off-day).
Route::get('/fix-wrong-day-attendance/{secret}', function ($secret) {
    if ($secret !== 'sahayya2026secure') {
        return response()->json(['error' => 'Unauthorized'], 401);
    }
    try {
        $deleted = [];
        // Get ALL attendance records
        $records = \App\Models\Attendance::with('staff.userWorkInfo')
            ->where('description', 'like', '%Auto-marked%')
            ->get();

        foreach ($records as $att) {
            $date   = \Carbon\Carbon::parse($att->date);
            $dayNum = (int) $date->format('N'); // 1=Mon … 7=Sun
            $dayNames = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
            $dayName  = $dayNames[$dayNum - 1];
            $day3     = substr($dayName, 0, 3); // 'sun','mon', etc.

            $rawDays = $att->staff?->userWorkInfo?->working_days;
            if (empty($rawDays)) {
                // Default: Mon–Sat. Sunday (day3='sun') should be deleted.
                $allowedDays3 = ['mon','tue','wed','thu','fri','sat'];
            } else {
                $allowedDays3 = array_map(fn($d) => substr(strtolower($d), 0, 3), $rawDays);
            }

            if (!in_array($day3, $allowedDays3)) {
                $deleted[] = [
                    'id'       => $att->id,
                    'staff_id' => $att->staff_id,
                    'date'     => $att->date->toDateString(),
                    'day'      => $dayName,
                ];
                $att->delete();
            }
        }

        return response()->json([
            'success'  => true,
            'deleted'  => count($deleted),
            'records'  => $deleted,
            'message'  => 'Wrong-day auto-attendance records removed.',
        ]);
    } catch (\Throwable $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

Route::get('/subscriptions/show/{id}', [SubscriptionController::class, 'show']);
Route::get('/subscription-list', [UserController::class, 'getSubscriptionList']);
Route::post('subscriptions/role', [SubscriptionController::class,'subscriptionByRole']);
    

Route::post('/signup', [UserController::class, 'signUp']);
Route::post('/verify-otp', [UserController::class, 'verifyOtp']);
Route::post('/otp-login', [UserController::class, 'verifyOtp']);
Route::post('/resend-otp', [UserController::class, 'resendOtp']);
Route::get('/category', [UserController::class, 'categoryList']);
Route::get('/cms-page', [UserController::class, 'getCmsData']);

Route::post('/cms-page-update', [BannerController::class, 'updateBody']);
Route::post('/google', [UserController::class, 'socialLoginCallback']);
Route::get('housersold/staff/active-today', [SalaryController::class, 'getTodayActiveStaff']);

Route::get('/analytics', [WalletController::class, 'getAnalytics']);
Route::post('/refer-submit', [UserController::class, 'referSubmit']);



Route::post('/supports', [SupportController::class, 'store']);  
Route::get('/supports', [SupportController::class, 'index']);
Route::post('/supports/{id}/reply', [SupportController::class, 'reply']);

Route::group(['prefix' => '/customer'], function() {
    Route::get('/dashbord-data', [SalaryController::class, 'getStaffDashboard']);
    Route::get('/approved-job', [JobApplicationController::class, 'approvedJob']);
    

    

    Route::get('/vendor/{id}', [UserController::class, 'vendorDetails']);
    Route::get('/home', [BookingController::class, 'homeScreen']);
    Route::get('/transaction/list', [BookingController::class, 'transactionList']);
    Route::get('/vendor/list/Auth', [UserController::class, 'vendorListAuth']);
    Route::get('/service/category/{id}', [UserController::class, 'categoryDetails']);
    Route::post('/signup', [UserController::class, 'signUpCustomer']);
    Route::post('/profile/update', [UserController::class, 'updateProfileCustomer']);
    Route::get('/service/category', [UserController::class, 'serviceCategoryList']);
    Route::get('/order/list', [UserController::class, 'orderList']);
    Route::get('/category/shops/{id}', [ServiceController::class, 'categoryShopList']);
    Route::get('/shops/{id}', [ServiceController::class, 'shopDetails']);
    Route::post('/bookings', [BookingController::class, 'addBooking']);
    Route::post('/booking-create/{id}', [BookingController::class, 'bookingCreate']);
    Route::post('/booking-verify', [BookingController::class, 'verifyBookingPayment']);
    Route::get('/bookings/list', [BookingController::class, 'bookingList']);
    Route::get('/booking/{id}', [BookingController::class, 'bookingDetails']);
    Route::post('/booking/{id}/cancel', [BookingController::class, 'cancelBooking']);
    Route::post('/sub-category/{id}', [ServiceController::class, 'subcategoryService']);
    Route::get('/services/{serviceId}/available-slots', [ServiceController::class, 'getAvailableSlots']);
    Route::post('/wishlist-add', [ServiceController::class, 'saveWishlist']);
    Route::post('/wishlist-remove/{id}', [ServiceController::class, 'removeWishlist']);
    Route::post('/booking-remove/{id}', [ServiceController::class, 'bookingWishlist']);
    Route::post('/cart-remove/{id}', [ServiceController::class, 'cartWishlist']);
    Route::get('/wishlist', [ServiceController::class, 'wishlistList']);
    Route::get('/promo-codes/{id}', [ServiceController::class, 'promoCodesList']);
    Route::get('/promo-code/highlighted', [ServiceController::class, 'promoCodesListHighlighted']);
    
    // Referral routes for customers (require auth)
    Route::middleware('auth:api')->group(function () {
        Route::get('/referral/code', [UserController::class, 'getReferralCode']);
        Route::post('/referral/apply', [UserController::class, 'applyReferralCode']);
        Route::post('/referral/credit-apply', [UserController::class, 'applyReferCredit']);
        Route::get('/referral/history', [UserController::class, 'getReferralHistory']);
    });
    
    Route::prefix('/cart')->group(function () {
        Route::post('/add', [CartController::class, 'addToCart']);
        Route::get('/', [CartController::class, 'getCart']);
        Route::delete('/remove/{id}', [CartController::class, 'removeFromCart']);
        Route::delete('/clear', [CartController::class, 'clearCart']);
    });

});


Route::prefix('housesold/salary')->middleware('auth:api')->group(function () {
    Route::get('/staff/{user_id}', [SalaryController::class, 'getStaffSalary']);
    Route::post('/staff/{user_id}', [SalaryController::class, 'updateStaffSalary']);
    Route::get('/list', [SalaryController::class, 'getRecentPayments']);
    Route::post('/{id}/send-to-bank', [SalaryController::class, 'sendToBank']);
    Route::get('/{id}/payouts', [SalaryController::class, 'payoutHistory']);
});



Route::post('admin/login', [UserController::class, 'loginAdmin']);

Route::prefix('/admin')->middleware('auth:api')->group(function () {
    //Route::get('/leave-list', [JobApplicationController::class, 'leaveList']);
    Route::get('/dashbord-data', [SalaryController::class, 'getAdminDashboard'])->middleware('admin.permission:dashboard');

    
    Route::post('/members/store', [UserController::class, 'storeNewMember'])->middleware('admin.permission:house_owners');
    Route::get('/members/list', [UserController::class, 'memberList'])->middleware('admin.permission:house_owners');
    Route::get('/members/{id}', [UserController::class, 'editMember'])->middleware('admin.permission:house_owners');
    Route::post('/members/{id}', [UserController::class, 'updateMember'])->middleware('admin.permission:house_owners');
    Route::get('/banner', [BannerController::class, 'index']); // Get banner
    Route::get('/user/list', [UserController::class, 'userList'])->middleware('admin.permission:house_owners'); // Get banner
    Route::get('/vendor/list', [UserController::class, 'vendorList'])->middleware('admin.permission:staff'); // Get banner
    Route::post('/banner', [BannerController::class, 'storeOrUpdate']); // Add/Update banner
    Route::post('/banner/delete', [BannerController::class, 'delete']); // Add/Update banner
    Route::get('/auth-jobs', [JobController::class, 'authBaseList']);
    Route::get('/jobs/list', [JobController::class, 'joblist'])->middleware('admin.permission:jobs');
    Route::get('/jobs/{id}', [JobController::class, 'show'])->middleware('admin.permission:jobs');
    Route::post('/jobs', [JobController::class, 'store'])->middleware('admin.permission:jobs');
    Route::post('/jobs/{id}', [JobController::class, 'update'])->middleware('admin.permission:jobs');
    Route::delete('/jobs/{id}', [JobController::class, 'destroy'])->middleware('admin.permission:jobs');
    Route::post('/jobs/{id}/status', [JobController::class, 'updateStatus'])->middleware('admin.permission:jobs');
    Route::get('/jobs/{jobId}/applications', [JobApplicationController::class, 'getJobApplications'])->middleware('admin.permission:jobs');
    Route::post('/applications/{id}/status', [JobApplicationController::class, 'updateApplicationStatus'])->middleware('admin.permission:jobs');

    // Admin Job Apply Limit Routes
    Route::get('/job-limit/settings', [JobApplyLimitController::class, 'adminGetSettings'])->middleware('admin.permission:settings');
    Route::post('/job-limit/settings', [JobApplyLimitController::class, 'adminUpdateSettings'])->middleware('admin.permission:settings');
    Route::get('/job-limit/stats', [JobApplyLimitController::class, 'adminStats'])->middleware('admin.permission:settings');
    Route::get('/job-limit/staff', [JobApplyLimitController::class, 'adminStaffLimits'])->middleware('admin.permission:settings');

    Route::get('faq-support', [FaqSupportController::class, 'customerIndex']);
    Route::get('faq-support/{id}', [FaqSupportController::class, 'customerShow']);
    Route::post('faq-support', [FaqSupportController::class, 'customerStore']);
    Route::post('faq-support/update/{id}', [FaqSupportController::class, 'customerUpdate']);
    Route::post('faq-support/delete/{id}', [FaqSupportController::class, 'customerDestroy']);

    Route::get('/getTransactions', [BookingController::class, 'getTransactions']);
    // Route::prefix('notification-shortcuts')->group(function () {
    //     Route::get('/', [NotificationShortcutController::class, 'index']);
    //     Route::post('/', [NotificationShortcutController::class, 'store']);
    //     Route::get('/{id}', [NotificationShortcutController::class, 'show']);
    //     Route::post('/update/{id}', [NotificationShortcutController::class, 'update']);
    //     Route::post('/delete/{id}', [NotificationShortcutController::class, 'destroy']);
    //     Route::post('/send/{id}', [NotificationShortcutController::class, 'sendShortcutNotification']);
    // });

    
    Route::apiResource('subscriptions', SubscriptionController::class)->middleware('admin.permission:membership');
    Route::get('dashboard', [DashboardController::class, 'index'])->middleware('admin.permission:dashboard');
    Route::post('report', [DashboardController::class, 'report'])->middleware('admin.permission:reports');
    Route::apiResource('houseowners', HouseOwnerController::class)->middleware('admin.permission:house_owners');
    Route::apiResource('staff', StaffController::class)->middleware('admin.permission:staff');
    Route::put('/staff/{id}/status', [StaffController::class, 'updateStatus'])->middleware('admin.permission:staff');
    Route::post('/staff/attendance', [StaffController::class, 'getAttendance'])->middleware('admin.permission:staff');
    Route::post('/staff/get-ai-data', [StaffController::class, 'getAiData']);
    Route::post('/staff/get-job-data', [StaffController::class, 'getJobByStaffAiData']);
    Route::get('/staff/job/list', [StaffController::class, 'getjobs']);
    Route::get('/stafflist', [StaffController::class, 'getStaffList'])->middleware('admin.permission:staff');
    
    Route::apiResource('roles', RoleController::class)->middleware('admin.permission:roles');
    Route::get('/sub-admins', [AdminUserController::class, 'index'])->middleware('admin.permission:sub_admins');

    // KYC Admin Routes
    Route::get('/kyc/list', [KycVerificationController::class, 'getAdminKycList']);
    Route::post('/kyc/{id}/status', [KycVerificationController::class, 'updateKycStatus']);

    // Notifications Admin Routes
    Route::get('/notifications', [NotificationShortcutController::class, 'getNotifications']);
    Route::post('/notifications/send', [NotificationShortcutController::class, 'sendNotification']);
    Route::post('/sub-admins', [AdminUserController::class, 'store'])->middleware('admin.permission:sub_admins');
    Route::post('/sub-admins/{id}', [AdminUserController::class, 'update'])->middleware('admin.permission:sub_admins');
    Route::put('/sub-admins/{id}/status', [AdminUserController::class, 'toggleStatus'])->middleware('admin.permission:sub_admins');
    Route::delete('/sub-admins/{id}', [AdminUserController::class, 'destroy'])->middleware('admin.permission:sub_admins');
    Route::get('/blacklists', [BlacklistController::class, 'index'])->middleware('admin.permission:blacklist');
    Route::get('/blacklist', [BlacklistController::class, 'index'])->middleware('admin.permission:blacklist');

      Route::get('/salary', [AdminSalaryController::class, 'index'])->middleware('admin.permission:staff');
      Route::post('/salary/store', [AdminSalaryController::class, 'store'])->middleware('admin.permission:staff');
      Route::put('/salary/{id}/status', [AdminSalaryController::class, 'updateStatus'])->middleware('admin.permission:staff');
      Route::get('/salary/{id}/payouts', [AdminSalaryController::class, 'payoutHistory'])->middleware('admin.permission:staff');
      Route::post('/salary/{id}/payout', [AdminSalaryController::class, 'initiateRazorpayXPayout'])->middleware('admin.permission:staff');
    
    Route::apiResource('terminations', TerminationController::class)->middleware('admin.permission:blacklist');

    Route::prefix('subscriptionuser')->group(function () {
        Route::get('/show/{id}', [SubscriptionController::class, 'getSubscriptionUser'])->middleware('admin.permission:membership');
        Route::post('/create-order', [SubscriptionController::class, 'createSubscriptionOrder']);
        Route::post('/verify-payment', [SubscriptionController::class, 'verifySubscriptionPayment']);
        Route::get('/current', [SubscriptionController::class, 'getCurrentSubscription']);
        Route::get('/history', [SubscriptionController::class, 'getSubscriptionHistory']);
                Route::post('/{id}/refund', [\App\Http\Controllers\Api\SubscriptionController::class, 'refundSubscription']);
    });



    Route::prefix('housersold/attendance')->group(function () {
        Route::get('/', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::post('/', [AttendanceController::class, 'store'])->name('attendance.store');
        Route::get('/{id}', [AttendanceController::class, 'show'])->name('attendance.show');
        Route::put('/{id}', [AttendanceController::class, 'update'])->name('attendance.update');
        Route::patch('/{id}', [AttendanceController::class, 'update'])->name('attendance.update.patch');
        Route::delete('/{id}', [AttendanceController::class, 'destroy'])->name('attendance.destroy');
        
    });

    Route::get('/referral/code', [UserController::class, 'getReferralCode']);
    Route::post('/referral/apply', [UserController::class, 'applyReferralCode']);
    Route::post('/referral/credit-apply', [UserController::class, 'applyReferCredit']);
    Route::get('/referral/history', [UserController::class, 'getReferralHistory']);


        Route::get('/legal-consents', [LegalConsentController::class, 'adminIndex'])->middleware('admin.permission:settings');

        Route::post('/settings/store', [SettingController::class, 'store']);
    Route::get('/settings', [SettingController::class, 'getAllSettings']);

});




Route::post('/legal-consent', [LegalConsentController::class, 'store']);
Route::post('/legal-consent/bulk', [LegalConsentController::class, 'storeBulk']);

// Policy version status (public for logged-in users)
Route::get('/policy/status', [PolicyController::class, 'status'])->middleware('auth:api');
Route::post('/policy/accept', [PolicyController::class, 'accept'])->middleware('auth:api');

// Admin policy management
Route::prefix('admin')->middleware(['auth:api', 'admin.permission:settings'])->group(function () {
    Route::get('/policy-versions', [PolicyController::class, 'index']);
    Route::post('/policy-versions', [PolicyController::class, 'store']);
});

Route::group(['middleware' => 'auth:api'], function() {
    Route::post('/logout', [UserController::class, 'logout']);

    // Alias routes for new frontend (subscription/* without /admin prefix)
    Route::prefix('subscription')->group(function () {
        Route::get('/current', [SubscriptionController::class, 'getCurrentSubscription']);
        Route::get('/history', [SubscriptionController::class, 'getSubscriptionHistory']);
                Route::post('/{id}/refund', [\App\Http\Controllers\Api\SubscriptionController::class, 'refundSubscription']);
        Route::post('/create-order', [SubscriptionController::class, 'createSubscriptionOrder']);
        Route::post('/verify-payment', [SubscriptionController::class, 'verifySubscriptionPayment']);
        Route::post('/subscribe', [SubscriptionController::class, 'subscribeFree']);
        Route::post('/create-extra-job-order', [SubscriptionController::class, 'createExtraJobOrder']);
        Route::post('/verify-extra-job-payment', [SubscriptionController::class, 'verifyExtraJobPayment']);
        Route::post('/create-extra-staff-order', [SubscriptionController::class, 'createExtraStaffOrder']);
        Route::post('/verify-extra-staff-payment', [SubscriptionController::class, 'verifyExtraStaffPayment']);
    });

    Route::get('/settings/notification', [SettingController::class, 'handleNotification']);
    Route::post('/settings/notification', [SettingController::class, 'handleNotification']);

    Route::get('/settings/AutoPresent', [SettingController::class, 'handleAutoPresent']);
    Route::post('/settings/AutoPresent', [SettingController::class, 'handleAutoPresent']);

    Route::post('/settings/notification/update', [SettingController::class, 'handleNotification']);
    Route::post('/last-work-experience/save', [UserController::class, 'saveLastWorkExperience']);
    Route::post('/category/save', [UserController::class, 'storeOrUpdate']);
    Route::post('/category/update/{id}', [UserController::class, 'categoryUpdate']); // Add/Update banner
    Route::delete('category/{id}', [UserController::class, 'destroy']);
    Route::get('/category/subcategories', [UserController::class, 'listSubcategories']);
    Route::get('/applications', [JobApplicationController::class, 'index']);
    Route::post('/applications', [JobApplicationController::class, 'store']);
    Route::post('/applications/{id}/delete', [JobApplicationController::class, 'destroy']);
    
    // Job Apply Limit Routes
    Route::get('/job-limit/status', [JobApplyLimitController::class, 'status']);
    Route::post('/job-limit/create-order', [JobApplyLimitController::class, 'createOrder']);
    Route::post('/job-limit/verify-payment', [JobApplyLimitController::class, 'verifyPayment']);
    Route::post('/voice/transcribe', [VoiceTranscriptionController::class, 'transcribe'])
        ->middleware('throttle:20,1');
    
    Route::get('/jobs', [JobController::class, 'index']);
    Route::prefix('staff')->group(function () {
        Route::post('/add', [UserController::class, 'addStaff']);
        Route::get('/list', [UserController::class, 'getStaffList']);
        Route::get('/available/{id}', [UserController::class, 'getAvailableStaffDetails']);
        Route::get('/{id}', [UserController::class, 'getStaffDetails']);
        Route::post('/update/{id}', [UserController::class, 'updateStaff']);
        
        // Staff Availability & Hire Me
        Route::post('/availability/update', [StaffController::class, 'updateAvailability']);
        Route::get('/availability/status', [StaffController::class, 'getAvailabilityStatus']);
        Route::post('/hire-me/opt-in', [StaffController::class, 'optInHireMe']);
        Route::post('/hire-me/update', [StaffController::class, 'updateAvailability']);
        Route::post('/hire-me/pause', [StaffController::class, 'pauseHireMe']);
        Route::post('/hire-me/deactivate', [StaffController::class, 'deactivateHireMe']);
    });

    Route::get('housersold/staff/active-today', [StaffController::class, 'getActiveTodayUser']);

    Route::get('/jobs/{id}', [JobController::class, 'show']);
    
    Route::post('user/delete-self', [UserController::class, 'deleteSelfAccount']);
    Route::post('admin/delete-user', [UserController::class, 'deleteUserByAdmin']);
    Route::get('admin/deleted-users', [UserController::class, 'getDeletedUsers']);
    Route::post('/kyc/upload', [KycVerificationController::class, 'updateOrCreateKyc']);
    Route::get('/kyc/status/{user_id}', [KycVerificationController::class, 'getKycStatus']);
    Route::get('/addresses', [UserController::class, 'addressIndex']);
    Route::post('/addresses/update', [UserController::class, 'addressUpdate']);
    Route::post('/work-info-update', [UserController::class, 'updateOrCreateWorkInfo']);
    Route::post('/aadhar/send-otp', [UserController::class, 'saveAadharAndSendOtp']);
    Route::post('/aadhar/verify', [UserController::class, 'aadharVerifyOtp']);
    Route::post('/aadhar/resend-otp', [UserController::class, 'resendAadharOtp']);
    Route::get('/aadhar/status', [UserController::class, 'getAadharStatus']);
    Route::get('/profile', [UserController::class, 'getProfile']);
    Route::post('/profile/update', [UserController::class, 'updateProfile']);
    Route::post('/update/password', [UserController::class, 'resetPassword']);
    Route::post('/delete/user', [UserController::class, 'deleteAcc']);
    Route::post('/delete/member/{id}', [UserController::class, 'deleteAccUser']);
    Route::get('/random-analytics/overview', [UserController::class, 'overview']);
    Route::post('/update/business-profile/2', [UserController::class, 'completeBusinessProfile']);
    Route::get('/mywork', [UserController::class, 'getMyWork']);

    Route::prefix('notifications')->group(function () {
        Route::post('/add', [UserController::class, 'notificationAdd']);
        Route::get('/list', [UserController::class, 'notificationList']);
        Route::put('/{id}/read', [UserController::class, 'notificationMarkAsRead']);
        Route::get('/unread-count', [UserController::class, 'notificationUnreadCount']);
        Route::post('/read', [UserController::class, 'notificationMarkAsReadPost']);
        Route::post('/read-all', [UserController::class, 'readAll']);
    });

    Route::post('/device-token', [UserController::class, 'updateDeviceToken']);


    Route::prefix('reviews')->group(function () {
        Route::post('/store', [ReviewController::class, 'store']);    // Add Review
        Route::get('/list', [ReviewController::class, 'index']); 
        Route::get('/list-self', [ReviewController::class, 'selfIndex']); 
        Route::delete('/delete/{id}', [ReviewController::class, 'destroy']); // Delete Review
    });

    Route::get('mails', [MailShortcutController::class, 'index']);
    Route::post('mails', [MailShortcutController::class, 'store']);
    Route::get('mails/{id}', [MailShortcutController::class, 'show']);
    Route::put('mails/{id}', [MailShortcutController::class, 'update']);
    Route::patch('mails/{id}', [MailShortcutController::class, 'update']);
    Route::delete('mails/{id}', [MailShortcutController::class, 'destroy']);
    Route::post('mails/{id}/send', [MailShortcutController::class, 'sendShortcutMail']);
    Route::post('/update/business-availability/3', [UserController::class, 'setBusinessAvailability']);
    Route::get('/bookings/list', [BookingController::class, 'vendorBookingList']);

    Route::prefix('services')->group(function () {
        Route::get('/', [ServiceController::class, 'index']);
        Route::post('/', [ServiceController::class, 'store']);
        Route::get('/{service}', [ServiceController::class, 'show']);
        Route::post('/{service}', [ServiceController::class, 'update']);
        Route::post('/delete/{service}', [ServiceController::class, 'destroy']);
        Route::get('/category/{categoryId}', [ServiceController::class, 'getByCategory']);
        Route::get('/user/{userId}', [ServiceController::class, 'getByUser']);
    });


    Route::prefix('sub-services')->group(function () {
        Route::get('/', [SubServiceController::class, 'index']);  
        Route::get('/{id}', [SubServiceController::class, 'show']);   
        Route::post('/', [SubServiceController::class, 'store']); 
        Route::post('/{id}', [SubServiceController::class, 'update']);
        Route::post('/delete/{id}', [SubServiceController::class, 'destroy']); 
    });

    Route::get('promo-codes', [PromoCodeController::class, 'index']); 
    Route::get('promo-codes/{id}', [PromoCodeController::class, 'show']);
    Route::post('promo-codes', [PromoCodeController::class, 'store']); // Create new promo code
    Route::post('promo-codes/validate', [PromoCodeController::class, 'validatePromoCode']); // Validate promo code
    Route::post('promo-codes/update/{id}', [PromoCodeController::class, 'update']); // Full update of promo code
    Route::post('promo-codes/delete/{id}', [PromoCodeController::class, 'destroy']);
    

    Route::get('bank-accounts', [BankAccountController::class, 'index']);
    Route::get('bank-accounts/{id}', [BankAccountController::class, 'show']);
    Route::post('bank-accounts', [BankAccountController::class, 'store']);
    Route::post('bank-accounts/update/{id}', [BankAccountController::class, 'update']);
    Route::post('bank-accounts/delete/{id}', [BankAccountController::class, 'destroy']);
    Route::get('bank-accounts/type/{type}', [BankAccountController::class, 'getByType']);
    Route::post('bank-accounts/set/{id}', [BankAccountController::class, 'setAcc']);


    Route::get('vendor-transactions/list', [BankAccountController::class, 'vendorTransactionsList']);
    Route::get('read-all', [UserController::class, 'readAll']);
    Route::get('transactions/{transaction}/invoice', [TransactionController::class, 'downloadInvoice']);

    Route::prefix('wallet')->group(function () {
        Route::get('/', [WalletController::class, 'index']);
        Route::post('/', [WalletController::class, 'store']); 
        Route::post('/verify', [WalletController::class, 'verifyAndCreditWallet']); 
    });

    Route::get('/transaction/list', [BookingController::class, 'vendorTransactionList']);
    Route::get('/appointment/list', [UserController::class, 'appointmentList']);
    Route::post('/booking/accepted/{id}', [BookingController::class, 'acceptBooking']);
    Route::post('/booking/reject/{id}', [BookingController::class, 'rejectBooking']);
    Route::post('/booking/completed/{id}', [BookingController::class, 'completedBooking']);

    Route::get('analytics/customers', [AnalyticsController::class, 'customerAnalytics']);
    Route::get('analytics/vendors', [AnalyticsController::class, 'vendorAnalytics']);


    Route::get('faq-support', [FaqSupportController::class, 'index']);
    Route::get('faq-support/{id}', [FaqSupportController::class, 'show']);
    Route::post('faq-support', [FaqSupportController::class, 'store']);
    Route::post('faq-support/update/{id}', [FaqSupportController::class, 'update']);
    Route::post('faq-support/delete/{id}', [FaqSupportController::class, 'destroy']);
    Route::get('faq-support/category/{category}', [FaqSupportController::class, 'getByCategory']);
    Route::get('faq-support-categories', [FaqSupportController::class, 'getCategories']);
    Route::post('faq-support-search', [FaqSupportController::class, 'search']);


    Route::post('/leave-apply', [JobApplicationController::class, 'applyLeave']);
    Route::get('/leave-list', [JobApplicationController::class, 'leaveList']);
    Route::get('/leave-type-list', [JobApplicationController::class, 'leaveTypeList']);
    Route::post('/leave-reject/{id}', [JobApplicationController::class, 'reject']);
    Route::post('/leave-approve/{id}', [JobApplicationController::class, 'approve']);
    Route::match(['get', 'post'], '/quit-job-request', [JobApplicationController::class, 'requestQuitJob']);
    Route::get('/quit-job-list', [JobApplicationController::class, 'listQuitJobs']);

    Route::get('/earnings/summary', [SalaryController::class, 'getEarningsSummary']);
    Route::get('/earnings/summary/{job_id}', [SalaryController::class, 'getEarningsSummary']);
    Route::post('/advance-withdraw', [SalaryController::class, 'advanceWithdraw']);

    // ── Advance & Deduction Management ──────────────────────────────
    // Employer routes
    Route::get('/advances', [AdvanceController::class, 'index']);                        // list advances
    Route::post('/advances', [AdvanceController::class, 'store']);                       // give advance
    Route::get('/advances/pending-deduction/{staff_id}', [AdvanceController::class, 'getPendingDeduction']); // MUST be before {id} wildcard
    Route::get('/advances/{id}', [AdvanceController::class, 'show']);                   // single advance detail
    Route::post('/advances/{id}/deduct', [AdvanceController::class, 'deduct']);         // manual deduction

    // Staff routes
    Route::get('/my-advances', [AdvanceController::class, 'staffAdvances']);            // staff sees their advances

});


// ═══════════════════════════════════════════════════════════════════
// ZOHO CRM + DESK INTEGRATION
// ═══════════════════════════════════════════════════════════════════

// Zoho auth-url + status — OUTSIDE auth middleware (needed for OAuth flow)
Route::group(['prefix' => 'zoho'], function () {
    Route::get('/status', [\App\Http\Controllers\Api\ZohoController::class, 'authStatus']);
    Route::get('/auth-url', [\App\Http\Controllers\Api\ZohoController::class, 'getAuthUrl']);
});

// Debug endpoint — admin only
Route::group(['prefix' => 'zoho', 'middleware' => 'auth:api'], function () {
    Route::get('/debug', [\App\Http\Controllers\Api\ZohoController::class, 'debugZoho']);
});

Route::group(['prefix' => 'zoho', 'middleware' => 'auth:api'], function () {
    // ── CRM ──
    Route::prefix('crm')->group(function () {
        Route::get('/summary', [\App\Http\Controllers\Api\ZohoController::class, 'getCrmModulesSummary']);
        Route::get('/modules', [\App\Http\Controllers\Api\ZohoController::class, 'getCrmModules']);
        Route::get('/reports', [\App\Http\Controllers\Api\ZohoController::class, 'getCrmReports']);
        Route::get('/leads', [\App\Http\Controllers\Api\ZohoController::class, 'getLeads']);
        Route::get('/leads/search', [\App\Http\Controllers\Api\ZohoController::class, 'searchLeads']);
        Route::post('/leads', [\App\Http\Controllers\Api\ZohoController::class, 'createLead']);
        Route::put('/leads/{id}', [\App\Http\Controllers\Api\ZohoController::class, 'updateLead']);
        Route::delete('/leads/{id}', [\App\Http\Controllers\Api\ZohoController::class, 'deleteLead']);
        Route::get('/contacts', [\App\Http\Controllers\Api\ZohoController::class, 'getContacts']);
        Route::get('/contacts/search', [\App\Http\Controllers\Api\ZohoController::class, 'searchContacts']);
        Route::post('/contacts', [\App\Http\Controllers\Api\ZohoController::class, 'createContact']);
        Route::put('/contacts/{id}', [\App\Http\Controllers\Api\ZohoController::class, 'updateContact']);
        Route::delete('/contacts/{id}', [\App\Http\Controllers\Api\ZohoController::class, 'deleteContact']);
        Route::get('/deals', [\App\Http\Controllers\Api\ZohoController::class, 'getDeals']);
        Route::get('/deals/search', [\App\Http\Controllers\Api\ZohoController::class, 'searchDeals']);
        Route::get('/deals/pipeline', [\App\Http\Controllers\Api\ZohoController::class, 'getDealsPipeline']);
        Route::put('/deals/{id}/stage', [\App\Http\Controllers\Api\ZohoController::class, 'moveDealStage']);
        Route::post('/deals', [\App\Http\Controllers\Api\ZohoController::class, 'createDeal']);
        Route::put('/deals/{id}', [\App\Http\Controllers\Api\ZohoController::class, 'updateDeal']);
        Route::delete('/deals/{id}', [\App\Http\Controllers\Api\ZohoController::class, 'deleteDeal']);
        Route::get('/{module}/{id}/timeline', [\App\Http\Controllers\Api\ZohoController::class, 'getCrmTimeline']);
        Route::post('/sync/staff', [\App\Http\Controllers\Api\ZohoController::class, 'syncStaffToCrm']);
        Route::post('/sync/owners', [\App\Http\Controllers\Api\ZohoController::class, 'syncOwnersToCrm']);
    });

    // ── DESK ──
    Route::prefix('desk')->group(function () {
        Route::get('/tickets', [\App\Http\Controllers\Api\ZohoController::class, 'getTickets']);
        Route::get('/tickets/counts', [\App\Http\Controllers\Api\ZohoController::class, 'getTicketCounts']);
        Route::get('/tickets/{id}', [\App\Http\Controllers\Api\ZohoController::class, 'getTicket']);
        Route::post('/tickets', [\App\Http\Controllers\Api\ZohoController::class, 'createTicket']);
        Route::put('/tickets/{id}', [\App\Http\Controllers\Api\ZohoController::class, 'updateTicket']);
        Route::delete('/tickets/{id}', [\App\Http\Controllers\Api\ZohoController::class, 'deleteTicket']);
        Route::put('/tickets/{id}/reassign', [\App\Http\Controllers\Api\ZohoController::class, 'reassignTicket']);
        Route::get('/tickets/{id}/comments', [\App\Http\Controllers\Api\ZohoController::class, 'getTicketComments']);
        Route::post('/tickets/{id}/comments', [\App\Http\Controllers\Api\ZohoController::class, 'addTicketComment']);
        Route::get('/departments', [\App\Http\Controllers\Api\ZohoController::class, 'getDepartments']);
        Route::get('/agents', [\App\Http\Controllers\Api\ZohoController::class, 'getAgents']);
        Route::get('/contacts', [\App\Http\Controllers\Api\ZohoController::class, 'getDeskContacts']);
        Route::get('/knowledgebase/categories', [\App\Http\Controllers\Api\ZohoController::class, 'getKnowledgeBase']);
        Route::get('/knowledgebase/articles', [\App\Http\Controllers\Api\ZohoController::class, 'getKBArticles']);
        Route::get('/cannedresponses', [\App\Http\Controllers\Api\ZohoController::class, 'getCannedResponses']);
    });

    // ── MAIL ──
    Route::prefix('mail')->group(function () {
        Route::get('/accounts', [\App\Http\Controllers\Api\ZohoController::class, 'getMailAccounts']);
        Route::get('/folders', [\App\Http\Controllers\Api\ZohoController::class, 'getMailFolders']);
        Route::get('/messages', [\App\Http\Controllers\Api\ZohoController::class, 'getMailMessages']);
        Route::get('/messages/{accountId}/{messageId}', [\App\Http\Controllers\Api\ZohoController::class, 'getMailMessage']);
        Route::post('/send', [\App\Http\Controllers\Api\ZohoController::class, 'sendZohoMail']);
        Route::post('/reply/{accountId}/{messageId}', [\App\Http\Controllers\Api\ZohoController::class, 'replyMail']);
    });
});

// OAuth callbacks — OUTSIDE auth middleware (Zoho redirects browser directly)
Route::get('/zoho/crm/callback', [\App\Http\Controllers\Api\ZohoController::class, 'oauthCallback']);
Route::get('/zoho/desk/callback', [\App\Http\Controllers\Api\ZohoController::class, 'oauthCallback']);
Route::get('/zoho/mail/callback', [\App\Http\Controllers\Api\ZohoController::class, 'oauthCallback']);


Route::get('/debug-db', function () {
    if (!config('app.debug')) {
        return response()->json(['message' => 'Not available in production'], 403);
    }
    return response()->json([
        'applications' => \App\Models\JobApplication::all(),
        'jobs' => \App\Models\Job::all(),
        'users' => \App\Models\User::orderBy('id', 'desc')->take(10)->get()
    ]);
});

// TEMP: Test ticket creation for Zoho Desk sync testing
Route::post('/test-create-ticket', function (\Illuminate\Http\Request $request) {
    try {
        $user = \App\Models\User::where('is_deleted', 0)->first();
        if (!$user) {
            return response()->json(['error' => 'No users found in DB'], 404);
        }

        $ticket = \App\Models\Ticket::create([
            'user_id' => $user->id,
            'subject' => $request->input('subject', 'Test Support Ticket - Ankit'),
            'description' => $request->input('description', 'This is a test support ticket created by Ankit to verify Zoho Desk integration. Please ignore.'),
            'category' => $request->input('category', 'General'),
            'priority' => $request->input('priority', 'Medium'),
            'status' => 'Open',
        ]);

        $zohoTicketId = null;
        $zohoError = null;
        try {
            $zohoService = new \App\Services\ZohoService('desk');
            if ($zohoService->isAuthorized()) {
                $deptResult = $zohoService->makeRequest('GET', '/departments');
                $departmentId = null;
                if ($deptResult['ok'] && !empty($deptResult['data']['departments'])) {
                    $departmentId = $deptResult['data']['departments'][0]['id'];
                }

                if ($departmentId) {
                    $body = "Category: {$ticket->category}\nPriority: {$ticket->priority}\n\n{$ticket->description}";
                    $body .= "\n\nUser: {$user->name} (" . ($user->email ?? 'no email') . ")";
                    $body .= "\nUser Phone: " . ($user->phone_number ?? 'N/A');

                    $result = $zohoService->makeRequest('POST', '/tickets', [
                        'subject' => $ticket->subject,
                        'description' => $body,
                        'departmentId' => $departmentId,
                        'priority' => $ticket->priority,
                        'status' => 'Open',
                        'channel' => 'Sahayya App',
                    ]);

                    if ($result['ok'] && isset($result['data']['id'])) {
                        $zohoTicketId = $result['data']['id'];
                        $ticket->update(['zoho_ticket_id' => $zohoTicketId]);
                    } else {
                        $zohoError = $result;
                    }
                } else {
                    $zohoError = 'No Zoho Desk departments found';
                }
            } else {
                $zohoError = 'Zoho Desk not authorized - set ZOHO_DESK_REFRESH_TOKEN in Railway env';
            }
        } catch (\Exception $e) {
            $zohoError = $e->getMessage();
        }

        return response()->json([
            'status' => 'success',
            'ticket' => $ticket->fresh(),
            'zoho_ticket_id' => $zohoTicketId,
            'zoho_error' => $zohoError,
            'user' => ['id' => $user->id, 'name' => $user->name],
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});





// Debug Zoho Desk status (temporary - remove after testing)
Route::get('/debug-zoho-desk', function () {
    try {
        $zohoService = new \App\Services\ZohoService('desk');
        $authorized = $zohoService->isAuthorized();
        $result = ['authorized' => $authorized, 'service' => 'desk'];

        if ($authorized) {
            try {
                $token = $zohoService->getAccessToken();
                $result['token_obtained'] = !empty($token);
                $result['token_length'] = strlen($token ?? '');
            } catch (\Exception $e) {
                $result['token_error'] = $e->getMessage();
            }
        } else {
            $result['hint'] = 'Zoho Desk not authorized. Set ZOHO_DESK_CLIENT_ID, ZOHO_DESK_CLIENT_SECRET, ZOHO_DESK_REFRESH_TOKEN in Railway env, then authorize via /api/zoho/desk/callback';
        }

        return response()->json($result);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()]);
    }
});

// Quick test - create ticket with Zoho sync
Route::post('/debug-create-zoho-ticket', function (\Illuminate\Http\Request $request) {
    try {
        $user = \App\Models\User::where('is_deleted', 0)->first();
        if (!$user) {
            return response()->json(['error' => 'No users found'], 404);
        }

        $ticket = \App\Models\Ticket::create([
            'user_id' => $user->id,
            'subject' => $request->input('subject', 'Test Ticket - Zoho Desk'),
            'description' => $request->input('description', 'Testing Zoho Desk integration'),
            'category' => $request->input('category', 'General'),
            'priority' => $request->input('priority', 'Medium'),
            'status' => 'Open',
        ]);

        $zohoResult = null;
        $zohoError = null;
        try {
            $zohoService = new \App\Services\ZohoService('desk');
            if ($zohoService->isAuthorized()) {
                $accessToken = $zohoService->getAccessToken();
                $zohoResult = ['token_ok' => true];

                $deptResult = $zohoService->makeRequest('GET', '/departments');
                $zohoResult['departments_response'] = $deptResult;

                $departmentId = null;
                if ($deptResult['ok']) {
                    $departments = $deptResult['data']['departments'] ?? $deptResult['data']['data'] ?? [];
                    if (!empty($departments)) {
                        $departmentId = $departments[0]['id'];
                    }
                }

                if ($departmentId) {
                    // First create/find contact
                    $contactId = null;
                    try {
                        $searchPhone = $user->phone_number;
                        if ($searchPhone) {
                            $contactSearch = $zohoService->makeRequest('GET', '/contacts', ['phone' => $searchPhone]);
                            $contacts = $contactSearch['data']['data'] ?? $contactSearch['data']['contacts'] ?? [];
                            if (!empty($contacts)) {
                                $contactId = $contacts[0]['id'];
                            }
                        }
                        if (!$contactId && $user->email) {
                            $contactSearch = $zohoService->makeRequest('GET', '/contacts', ['email' => $user->email]);
                            $contacts = $contactSearch['data']['data'] ?? $contactSearch['data']['contacts'] ?? [];
                            if (!empty($contacts)) {
                                $contactId = $contacts[0]['id'];
                            }
                        }
                        if (!$contactId) {
                            $lastName = !empty($user->last_name) ? trim($user->last_name) : 'Kumar';
                            $firstName = !empty($user->first_name) ? trim($user->first_name) : trim($user->name ?? '');
                            if (empty($firstName)) $firstName = 'Sahayya';
                            $newContact = $zohoService->makeRequest('POST', '/contacts', [
                                'firstName' => $firstName,
                                'lastName' => $lastName,
                                'phone' => $searchPhone ?? '',
                                'email' => $user->email ?? '',
                            ]);
                            if ($newContact['ok'] && isset($newContact['data']['id'])) {
                                $contactId = $newContact['data']['id'];
                            }
                            $zohoResult['contact_create'] = $newContact;
                        }
                        $zohoResult['contact_id'] = $contactId;
                    } catch (\Exception $e) {
                        $zohoResult['contact_error'] = $e->getMessage();
                    }

                    $body = "Category: {$ticket->category}\nPriority: {$ticket->priority}\n\n{$ticket->description}";
                    $body .= "\n\nUser: {$user->name}";
                    $body .= "\nPhone: {$user->phone_number}";

                    $ticketData = [
                        'subject' => $ticket->subject,
                        'description' => $body,
                        'departmentId' => $departmentId,
                        'priority' => $ticket->priority,
                        'status' => 'Open',
                        'channel' => 'Sahayya App',
                    ];
                    if ($contactId) {
                        $ticketData['contactId'] = $contactId;
                    }

                    $result = $zohoService->makeRequest('POST', '/tickets', $ticketData);

                    $zohoResult['create_response'] = $result;

                    if ($result['ok'] && isset($result['data']['id'])) {
                        $ticket->update(['zoho_ticket_id' => $result['data']['id']]);
                        $zohoResult['zoho_ticket_id'] = $result['data']['id'];
                    }
                } else {
                    $zohoError = 'No departments found';
                }
            } else {
                $zohoError = 'Zoho Desk not authorized - need OAuth first';
            }
        } catch (\Exception $e) {
            $zohoError = $e->getMessage();
        }

        return response()->json([
            'ticket' => $ticket->fresh(),
            'zoho_result' => $zohoResult,
            'zoho_error' => $zohoError,
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});
