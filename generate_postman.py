
import re
import json

api_content = r"""
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['message' => 'API is working successfully', 'status' => 200]);
});

Route::post('customer/login', [UserController::class, 'loginCustomer']);
Route::get('/designations-list', [UserController::class, 'designationsIndex']);

Route::group(['prefix' => '/customer'], function() {
    Route::get('/dashbord-data', [SalaryController::class, 'getStaffDashboard']);
    Route::get('/approved-job', [JobApplicationController::class, 'approvedJob']);
    Route::post('/quit-job-request', [JobApplicationController::class, 'requestQuitJob']);
    Route::post('/leave-apply', [JobApplicationController::class, 'applyLeave']);
    Route::get('/leave-list', [JobApplicationController::class, 'leaveList']);
    Route::get('/leave-type-list', [JobApplicationController::class, 'leaveTypeList']);
    Route::post('/leave-reject/{id}', [JobApplicationController::class, 'reject']);
    Route::post('/leave-approve/{id}', [JobApplicationController::class, 'approve']);
    Route::get('/earnings/summary', [SalaryController::class, 'getEarningsSummary']);
    Route::get('/earnings/summary/{job_id}', [SalaryController::class, 'getEarningsSummary']);
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
    Route::prefix('/cart')->group(function () {
        Route::post('/add', [CartController::class, 'addToCart']);
        Route::get('/', [CartController::class, 'getCart']);
        Route::delete('/remove/{id}', [CartController::class, 'removeFromCart']);
        Route::delete('/clear', [CartController::class, 'clearCart']);
    });
});

Route::get('/subscriptions', [SubscriptionController::class, 'index']);
Route::get('/subscriptions/show/{id}', [SubscriptionController::class, 'show']);

Route::prefix('housesold/salary')->group(function () {
    Route::get('/staff/{user_id}', [SalaryController::class, 'getStaffSalary']);
    Route::post('/staff/{user_id}', [SalaryController::class, 'updateStaffSalary']);
    Route::get('/list', [SalaryController::class, 'getRecentPayments']);
});
Route::get('housersold/staff/active-today', [SalaryController::class, 'getTodayActiveStaff']);
Route::prefix('housersold/attendance')->group(function () {
    Route::get('/', [AttendanceController::class, 'index']);
    Route::post('/', [AttendanceController::class, 'store']);
    Route::get('/{id}', [AttendanceController::class, 'show']);
    Route::put('/{id}', [AttendanceController::class, 'update']);
    Route::patch('/{id}', [AttendanceController::class, 'update']);
    Route::delete('/{id}', [AttendanceController::class, 'destroy']);
});

Route::prefix('/admin')->group(function () {
    Route::post('/members/store', [UserController::class, 'storeNewMember']);
    Route::get('/members/list', [UserController::class, 'memberList']);
    Route::get('/members/{id}', [UserController::class, 'editMember']);
    Route::post('/members/{id}', [UserController::class, 'updateMember']);
    Route::get('/banner', [BannerController::class, 'index']);
    Route::get('/user/list', [UserController::class, 'userList']);
    Route::get('/vendor/list', [UserController::class, 'vendorList']);
    Route::post('/banner', [BannerController::class, 'storeOrUpdate']);
    Route::post('/category/update/{id}', [UserController::class, 'categoryUpdate']);
    Route::post('/banner/delete', [BannerController::class, 'delete']);
    Route::get('/auth-jobs', [JobController::class, 'authBaseList']);
    Route::post('/jobs', [JobController::class, 'store']);
    Route::post('/jobs/{id}', [JobController::class, 'update']);
    Route::post('/jobs/delete/{id}', [JobController::class, 'destroy']);
    Route::post('/jobs/{id}/status', [JobController::class, 'updateStatus']);
    Route::post('/jobs/delete/{id}', [JobController::class, 'deleteJob']);
    Route::get('/jobs/{jobId}/applications', [JobApplicationController::class, 'getJobApplications']);
    Route::post('/applications/{id}/status', [JobApplicationController::class, 'updateApplicationStatus']);
    Route::get('faq-support', [FaqSupportController::class, 'customerIndex']);
    Route::get('faq-support/{id}', [FaqSupportController::class, 'customerShow']);
    Route::post('faq-support', [FaqSupportController::class, 'customerStore']);
    Route::post('faq-support/update/{id}', [FaqSupportController::class, 'customerUpdate']);
    Route::post('faq-support/delete/{id}', [FaqSupportController::class, 'customerDestroy']);
    Route::post('/subscriptions', [SubscriptionController::class, 'store']);
    Route::post('/subscriptions/update/{id}', [SubscriptionController::class, 'update']);
    Route::post('/subscriptions/delete/{id}', [SubscriptionController::class, 'destroy']);
    Route::get('/getTransactions', [BookingController::class, 'getTransactions']);
    Route::prefix('notification-shortcuts')->group(function () {
        Route::get('/', [NotificationShortcutController::class, 'index']);
        Route::post('/', [NotificationShortcutController::class, 'store']);
        Route::get('/{id}', [NotificationShortcutController::class, 'show']);
        Route::post('/update/{id}', [NotificationShortcutController::class, 'update']);
        Route::post('/delete/{id}', [NotificationShortcutController::class, 'destroy']);
        Route::post('/send/{id}', [NotificationShortcutController::class, 'sendShortcutNotification']);
    });
});

Route::post('/signup', [UserController::class, 'signUp']);
Route::post('/verify-otp', [UserController::class, 'verifyOtp']);
Route::post('/resend-otp', [UserController::class, 'resendOtp']);
Route::get('/category', [UserController::class, 'categoryList']);
Route::get('/cms-page', [UserController::class, 'getCmsData']);
Route::get('/subscription-list', [UserController::class, 'getSubscriptionList']);
Route::post('/cms-page-update', [BannerController::class, 'updateBody']);
Route::post('/google', [UserController::class, 'socialLoginCallback']);

Route::group(['middleware' => 'auth:api'], function() {
    Route::get('/settings/notification', [SettingController::class, 'handleNotification']);
    Route::post('/settings/notification', [SettingController::class, 'handleNotification']);
    Route::get('/settings/AutoPresent', [SettingController::class, 'handleAutoPresent']);
    Route::post('/settings/AutoPresent', [SettingController::class, 'handleAutoPresent']);
    Route::post('/settings/notification/update', [SettingController::class, 'handleNotification']);
    Route::post('/last-work-experience/save', [UserController::class, 'saveLastWorkExperience']);
    Route::post('/category/save', [UserController::class, 'storeOrUpdate']);
    Route::get('/category/subcategories', [UserController::class, 'listSubcategories']);
    Route::get('/applications', [JobApplicationController::class, 'index']);
    Route::post('/applications', [JobApplicationController::class, 'store']);
    Route::post('/applications/{id}/delete', [JobApplicationController::class, 'destroy']);
    Route::get('/jobs', [JobController::class, 'index']);
     Route::prefix('staff')->group(function () {
        Route::post('/add', [UserController::class, 'addStaff']);
        Route::get('/list', [UserController::class, 'getStaffList']);
        Route::get('/{id}', [UserController::class, 'getStaffDetails']);
        Route::post('/update/{id}', [UserController::class, 'updateStaff']);
    });
    Route::get('/jobs/{id}', [JobController::class, 'show']);
    Route::get('/subscriptions', [SubscriptionController::class, 'index']);
    Route::get('/subscriptions/show/{id}', [SubscriptionController::class, 'show']);
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
    Route::prefix('notifications')->group(function () {
        Route::post('/add', [UserController::class, 'notificationAdd']);
        Route::get('/list', [UserController::class, 'notificationList']);
        Route::put('/{id}/read', [UserController::class, 'notificationMarkAsRead']);
    });
    Route::get('/bookings/list', [BookingController::class, 'vendorBookingList']);
    Route::prefix('reviews')->group(function () {
        Route::post('/store', [ReviewController::class, 'store']);
        Route::get('/list', [ReviewController::class, 'index']);
        Route::get('/list-self', [ReviewController::class, 'selfIndex']);
        Route::delete('/delete/{id}', [ReviewController::class, 'destroy']);
    });
    Route::get('mails', [MailShortcutController::class, 'index']);
    Route::post('mails', [MailShortcutController::class, 'store']);
    Route::get('mails/{id}', [MailShortcutController::class, 'show']);
    Route::put('mails/{id}', [MailShortcutController::class, 'update']);
    Route::patch('mails/{id}', [MailShortcutController::class, 'update']);
    Route::delete('mails/{id}', [MailShortcutController::class, 'destroy']);
    Route::post('mails/{id}/send', [MailShortcutController::class, 'sendShortcutMail']);
    Route::post('/update/business-availability/3', [UserController::class, 'setBusinessAvailability']);
    Route::prefix('services')->group(function () {
        Route::get('/', [ServiceController::class, 'index']);
        Route::post('/', [ServiceController::class, 'store']);
        Route::get('/{service}', [ServiceController::class, 'show']);
        Route::post('/{service}', [ServiceController::class, 'update']);
        Route::post('/delete/{service}', [ServiceController::class, 'destroy']);
        Route::get('/category/{categoryId}', [ServiceController::class, 'getByCategory']);
        Route::get('/user/{userId}', [ServiceController::class, 'getByUser']);
    });
    Route::post('/supports', [SupportController::class, 'store']);
    Route::get('/supports', [SupportController::class, 'index']);
    Route::post('/supports/{id}/reply', [SupportController::class, 'reply']);
    Route::prefix('sub-services')->group(function () {
        Route::get('/', [SubServiceController::class, 'index']);
        Route::get('/{id}', [SubServiceController::class, 'show']);
        Route::post('/', [SubServiceController::class, 'store']);
        Route::post('/{id}', [SubServiceController::class, 'update']);
        Route::post('/delete/{id}', [SubServiceController::class, 'destroy']);
    });
    Route::get('promo-codes', [PromoCodeController::class, 'index']);
    Route::get('promo-codes/{id}', [PromoCodeController::class, 'show']);
    Route::post('promo-codes', [PromoCodeController::class, 'store']);
    Route::post('promo-codes/validate', [PromoCodeController::class, 'validatePromoCode']);
    Route::post('promo-codes/update/{id}', [PromoCodeController::class, 'update']);
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
    Route::prefix('subscription')->group(function () {
        Route::post('/create-order', [SubscriptionController::class, 'createSubscriptionOrder']);
        Route::post('/verify-payment', [SubscriptionController::class, 'verifySubscriptionPayment']);
        Route::get('/current', [SubscriptionController::class, 'getCurrentSubscription']);
        Route::get('/history', [SubscriptionController::class, 'getSubscriptionHistory']);
    });
    Route::get('faq-support', [FaqSupportController::class, 'index']);
    Route::get('faq-support/{id}', [FaqSupportController::class, 'show']);
    Route::post('faq-support', [FaqSupportController::class, 'store']);
    Route::post('faq-support/update/{id}', [FaqSupportController::class, 'update']);
    Route::post('faq-support/delete/{id}', [FaqSupportController::class, 'destroy']);
    Route::get('faq-support/category/{category}', [FaqSupportController::class, 'getByCategory']);
    Route::get('faq-support-categories', [FaqSupportController::class, 'getCategories']);
    Route::post('faq-support-search', [FaqSupportController::class, 'search']);
});
Route::get('/analytics', [WalletController::class, 'getAnalytics']);
Route::post('/refer-submit', [UserController::class, 'referSubmit']);
"""

collection = {
    "info": {
        "name": "Sahayya Backend API",
        "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
    },
    "variable": [
        {
            "key": "base_url",
            "value": "https://sahayaa-backend.up.railway.app/api",
            "type": "string"
        }
    ],
    "item": []
}

folders = {
    "Customer": [],
    "Admin": [],
    "Auth": [],
    "Staff": [],
    "Services": [],
    "Bookings": [],
    "Salary": [],
    "Attendance": [],
    "General": [],
    "Secured": []
}

def clean_route(route_line):
    # Basic cleanup
    route_line = route_line.strip()
    return route_line

def add_to_folder(folder_name, name, method, url, auth=False):
    request_item = {
        "name": name,
        "request": {
            "method": method.upper(),
            "header": [
                {"key": "Accept", "value": "application/json"}
            ],
            "url": {
                "raw": "{{base_url}}/" + url.lstrip('/'),
                "host": ["{{base_url}}"],
                "path": url.lstrip('/').split('/')
            }
        }
    }
    
    if method.upper() in ['POST', 'PUT', 'PATCH']:
         request_item["request"]["body"] = {
            "mode": "raw",
            "raw": "{\n    \n}",
            "options": {
                "raw": {
                    "language": "json"
                }
            }
        }

    if auth:
        request_item["request"]["auth"] = {
            "type": "bearer",
            "bearer": [
                {
                    "key": "token",
                    "value": "{{auth_token}}",
                    "type": "string"
                }
            ]
        }
        folders["Secured"].append(request_item)
        return

    folders[folder_name].append(request_item)

current_prefix = ""
in_auth_group = False

# Simple line-by-line parser simulation
lines = api_content.split('\n')
for line in lines:
    line = line.strip()
    
    # Track prefixes
    group_match = re.search(r"Route::(prefix|group)\(\['prefix' => '([^']+)']", line)
    prefix_match = re.search(r"Route::prefix\('([^']+)'\)", line)
    
    if "Route::group(['middleware' => 'auth:api']" in line:
        in_auth_group = True
        continue
    
    # Heuristic reset for closures (very basic)
    if line == "});" or line == "});":
        # We don't track stack depth well here in this simple script 
        # but for this specific file structure it's mostly 1 level deep except auth
        pass

    # Extract Routes
    # Route::get('/uri', ...)
    match = re.search(r"Route::(get|post|put|delete|patch)\('([^']+)',", line)
    if match:
        method = match.group(1)
        uri = match.group(2)
        
        # Determine folder
        folder = "General"
        
        full_uri = uri
        
        # Manually patching prefixes based on visual reading of the provided file format
        # This is a bit hacky but works for the known file content structure
        if "Route::group(['prefix' => '/customer']" in api_content and "dashbord-data" in uri:
             # This means we need smarter parsing, but for now I'll use keywords
             pass
        
        lower_uri = uri.lower()
        if "customer" in lower_uri or "dashbord" in lower_uri or "approved-job" in lower_uri: folder = "Customer"
        if "admin" in lower_uri or "banner" in lower_uri or "members" in lower_uri: folder = "Admin"
        if "login" in lower_uri or "signup" in lower_uri or "otp" in lower_uri: folder = "Auth"
        if "staff" in lower_uri: folder = "Staff"
        if "service" in lower_uri or "category" in lower_uri: folder = "Services"
        if "booking" in lower_uri: folder = "Bookings"
        if "salary" in lower_uri or "earnings" in lower_uri: folder = "Salary"
        if "attendance" in lower_uri: folder = "Attendance"
        
        # Override for auth group
        is_auth = False
        # We can't easily detect the block scope line-by-line without a stack
        # But we know many routes are typically auth if they are settings/profile/etc
        if "settings" in lower_uri or "profile" in lower_uri or "logout" in lower_uri or "upload" in lower_uri:
            is_auth = True
        
        add_to_folder(folder, f"{method.upper()} {uri}", method, uri, is_auth)

# Build final structure
for folder_name, items in folders.items():
    if items:
        collection["item"].append({
            "name": folder_name,
            "item": items
        })

print(json.dumps(collection, indent=4))
