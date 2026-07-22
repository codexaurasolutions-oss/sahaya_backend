<?php

use App\Http\Controllers\frontend\CartController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\frontend\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['UserGuestApi', 'ResponseMiddleware'])->group(function () {
    Route::post('login', [UserController::class, 'login']);
    Route::get('searching-product', [App\Http\Controllers\frontend\ProductSearchingController::class, 'index'])->name('product.search');

    
    Route::match(['get', 'post'],'signup', [UserController::class, 'signup']);
    Route::match(['get', 'post'],'otp/{validate_string}/{otp_for?}', [UserController::class, 'otp']);
    Route::match(['get', 'post'],'resend-otp/{validate_string}/{type}', [UserController::class, 'resendOtp']);
    
    Route::post('forget-password', [UserController::class, 'forgot_password']);
    Route::post('reset-password/{validate_string}', [UserController::class, 'reset_password']);

    Route::get('privacy-policy', [UserController::class, 'privacyPolicy']);
    Route::get('terms-and-Conditions', [UserController::class, 'termsAndConditions']);
    Route::get('help-and-support', [UserController::class, 'helpAndSupport']);
    Route::get('about-us', [UserController::class, 'aboutUs']);
    Route::get('shipping-policy', [UserController::class, 'shippingPolicy']);
    Route::get('refund-policy', [UserController::class, 'refundPolicy']);
    Route::get('social-media-links', [UserController::class, 'socialMediaLinks']);

    Route::post('social-login/callback', [UserController::class, 'socialLoginCallback']);
    Route::post('contact-us', [UserController::class, 'contactUs']);
    Route::get('intro-screen', [UserController::class, 'introScreen']);
    Route::get('masters', [UserController::class, 'masters']);
    Route::get('faqs', [UserController::class, 'faqs']);
    Route::post('initiatePayment', [CartController::class, 'processPayment']);
    Route::post('callback', [CartController::class, 'callback']);
    Route::post('initiatePaymentCard', [CartController::class, 'initiatePaymentCard']);

    Route::post('paytr/payment/api', [App\Http\Controllers\frontend\PayTRController::class, 'paymentRequestapi']);
    Route::post('paytr/notification/api', [App\Http\Controllers\frontend\PayTRController::class, 'paymentNotificationpi']);
});

Route::middleware(['UserAuthApi', 'ResponseMiddleware'])->group(function () {
    Route::get('profile-setting', [UserController::class, 'manageProfile']);
    Route::post('update-profile-setting', [UserController::class, 'updatePersonDetails']);

    Route::get('logout', [UserController::class, 'logout']);
    // Route::post('update-profile-details', [UserController::class, 'updateProfileDetails']);

    Route::post('account-settings', [UserController::class, 'accountSettings']);
    

    Route::post('update-notification-setting', [UserController::class, 'updateSetting']);

    // Route::get('notifications', [UserController::class, 'notifications']);
    Route::get('/delete-account', [UserController::class, 'userDestroy']);
    Route::get('/update-language', [UserController::class, 'updateLanguage']);

    Route::match(['get', 'post'], 'categories/{catId?}', [UserController::class, 'categoryList']);
    Route::match(['get', 'post'], 'product-action', [UserController::class, 'productAction']);


    Route::get('get-products', [UserController::class, 'getProducts']);
    Route::match(['get', 'post'],'add-product', [UserController::class, 'addProduct']);
    Route::get('category-colors-list/{id}', [UserController::class, 'categoryColorsList']);
 
    // Route::match(['get', 'post'],'edit-product/{id}', [DemoUserController::class, 'editProduct']);   
    
    Route::get('get-profile', [UserController::class, 'getProfile']);
    Route::get('product-details/{id?}', [UserController::class, 'productDetails']);
    Route::post('update-product/{id?}', [UserController::class, 'updateProduct']);
    Route::get('delete-product/{id?}', [UserController::class, 'deleteProduct']);

    //Updated Apis Milestone 5
    Route::post('add-cart', [App\Http\Controllers\frontend\CartController::class, 'addCart'])->name('cart.add');
    Route::post('update-cart-quantity', [App\Http\Controllers\frontend\CartController::class, 'updateQuantity'])->name('cart.updatequantity');
    Route::post('remove-cart', [App\Http\Controllers\frontend\CartController::class, 'removeCart'])->name('cart.remove');
    Route::get('cart', [App\Http\Controllers\frontend\CartController::class, 'listCart'])->name('cart.list');

    Route::post('add-shipping-address', [App\Http\Controllers\frontend\ShippingAddress::class, 'StoreShippingAddress'])->name('storeshippingAddress');
    Route::post('mark-as-default', [App\Http\Controllers\frontend\ShippingAddress::class, 'mark_as_default_address'])->name('mark_as_default_address');
    Route::get('shipping-address-list', [App\Http\Controllers\frontend\ShippingAddress::class, 'ShoppingAddressList'])->name('ShoppingAddressList');
    Route::post('update-shipping-address', [App\Http\Controllers\frontend\ShippingAddress::class, 'update'])->name('address.update');

    Route::get('edit-shipping-address/{id}', [App\Http\Controllers\frontend\ShippingAddress::class, 'edit'])->name('address.edit');
    Route::get('delete-shipping-address/{id}', [App\Http\Controllers\frontend\ShippingAddress::class, 'delete'])->name('address.delete');
    Route::post('enquiry-submit', [App\Http\Controllers\frontend\EnquiryController::class, 'enquiry_submit'])->name('enquiry_submit');
    
    Route::post('order-place', [App\Http\Controllers\frontend\OrderController::class, 'store'])->name('order.store');
    Route::get('order-list', [App\Http\Controllers\frontend\OrderController::class, 'OrderList'])->name('order.list');
    Route::get('order-details', [App\Http\Controllers\frontend\OrderController::class, 'OrderDetails'])->name('order.details');
    Route::get('coupons-list', [App\Http\Controllers\frontend\CouponsController::class, 'CouponsList'])->name('CouponsList');
    Route::post('order-cancel', [App\Http\Controllers\frontend\OrderController::class, 'OrderCancel'])->name('OrderCancel');
    Route::post('order-cancel-single', [App\Http\Controllers\frontend\OrderController::class, 'SingleOrderCancel'])->name('SingleOrderCancel');
    Route::post('reject-order-single', [App\Http\Controllers\frontend\OrderController::class, 'SingleOrderReject'])->name('singleorderreject');
    Route::post('reject-order', [App\Http\Controllers\frontend\OrderController::class, 'OrderReject'])->name('OrderReject');
    Route::post('order-refund-all', [App\Http\Controllers\frontend\OrderController::class, 'OrderRefundAll'])->name('OrderRefundAll');
    Route::post('order-refund-single', [App\Http\Controllers\frontend\OrderController::class, 'OrderRefundSingle'])->name('OrderRefundSingle');
    Route::get('varient-change', [App\Http\Controllers\frontend\OrderController::class, 'varient_change'])->name('varient_change');
    Route::get('order-received-seller-listing', [App\Http\Controllers\frontend\OrderController::class, 'received_order'])->name('received_order');
    Route::get('order-received-details', [App\Http\Controllers\frontend\OrderController::class, 'receivedOrderDetails'])->name('received_order_details');
    Route::post('order-received-cancel', [App\Http\Controllers\frontend\OrderController::class, 'receivedOrderCancel'])->name('receivedOrderCancel');
    Route::get('reasons', [App\Http\Controllers\frontend\OrderController::class, 'returnreasons'])->name('reasons');
    Route::get('products-name-change', [App\Http\Controllers\frontend\ProductSearchingController::class, 'changeProductsNames'])->name('changeProductsNames');
    Route::post('apply-coupon', [App\Http\Controllers\frontend\CouponsController::class, 'applyCoupon'])->name('applycoupon');
    Route::post('remove-coupon', [App\Http\Controllers\frontend\CouponsController::class, 'removeCoupon'])->name('removecoupon');
    Route::post('submit-review', [App\Http\Controllers\frontend\RatingReviewController::class, 'ratingReviewSubmit'])->name('ratingReviewSubmit');

    // coupons crud apis
    Route::post('coupons-add', [App\Http\Controllers\frontend\CouponsController::class, 'couponsAdd'])->name('couponsAdd');
    Route::post('coupons-update', [App\Http\Controllers\frontend\CouponsController::class, 'couponsUpdate'])->name('couponsUpdate');
    Route::get('coupons-list-vendor', [App\Http\Controllers\frontend\CouponsController::class, 'couponsListVendor'])->name('couponsListVendor');
    Route::get('coupons-edit', [App\Http\Controllers\frontend\CouponsController::class, 'couponsEdit'])->name('couponsEdit');
    Route::post('coupons-delete', [App\Http\Controllers\frontend\CouponsController::class, 'couponsDelete'])->name('couponsDelete');


    Route::post('vendor-product-status-change', [App\Http\Controllers\frontend\OrderController::class, 'productStatusChangeVendor'])->name('productStatusChangeVendor');
    // products enqurises crud apis
    Route::post('send-product-enquires', [App\Http\Controllers\frontend\EnquiryController::class, 'sendProductEnquiry'])->name('sendProductEnquiry');
    Route::post('product-enquires-user-list', [App\Http\Controllers\frontend\EnquiryController::class, 'ProductEnquiryUserList'])->name('ProductEnquiryUserList');
    Route::post('product-enquires-seller-list', [App\Http\Controllers\frontend\EnquiryController::class, 'ProductEnquirySellerList'])->name('ProductEnquirySellerList');
    Route::post('product-enquires-chats', [App\Http\Controllers\frontend\EnquiryController::class, 'ProductEnquiryChat'])->name('ProductEnquiryChat');

    // query
    Route::get('user-queries', [App\Http\Controllers\frontend\EnquiryController::class, 'userQuery'])->name('userQuery');
    Route::get('seller-queries', [App\Http\Controllers\frontend\EnquiryController::class, 'sellerQuery'])->name('sellerQuery');
    Route::get('product-enquiry-details', [App\Http\Controllers\frontend\EnquiryController::class, 'enquiryDetails'])->name('enquiryDetails');


    Route::get('notification', [App\Http\Controllers\frontend\NotificationController::class, 'notificationList'])->name('notificationList');
    Route::get('clear-all-notification', [App\Http\Controllers\frontend\NotificationController::class, 'clearAllNotifications'])->name('clearAllNotifications');
    Route::post('follow', [App\Http\Controllers\frontend\FollowerController::class, 'follow'])->name('follow');
    Route::post('remove-follow', [App\Http\Controllers\frontend\FollowerController::class, 'removefollow'])->name('removefollow');
    Route::get('profile-feed', [App\Http\Controllers\frontend\FollowerController::class, 'profileFeed'])->name('profileFeed');
    Route::post('block-users', [App\Http\Controllers\frontend\NotificationController::class, 'blockUserProduct'])->name('block-users');
    Route::get('block-users-list', [App\Http\Controllers\frontend\NotificationController::class, 'blockUserList'])->name('block-users-list');
    Route::post('un-block-users', [App\Http\Controllers\frontend\NotificationController::class, 'UnBlockUsers'])->name('un-block-users');

});