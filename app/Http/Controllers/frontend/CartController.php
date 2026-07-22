<?php

namespace App\Http\Controllers\frontend;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use Auth;
use App\Models\ProductVariant;
use App\Models\ReviewRating;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\BlockUser;
use App\Models\ShippingAddressModel;
use App\Models\ProductColor;
use App\Models\Coupon;
use App\Models\Category;
use App\Models\Order;
use DB;
use Config;
use App,Str;
use Validator;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use App\Services\Admin\PaymentService;
use Gizemsever\LaravelPaytr\Payment\Product as PaytrProduct;
use Gizemsever\LaravelPaytr\Payment\Basket;
use Gizemsever\LaravelPaytr\Payment\Currency;

class CartController extends Controller
{

    protected $merchant_id;
    protected $merchant_key;
    protected $merchant_salt;
    protected $base_url;
    protected $paytrService;

    public function __construct()
    {
        $this->merchant_id ='523380' ;
        $this->merchant_key ='EkrPt6wsUqxrxddM' ;
        $this->merchant_salt ='dq9PYiZWe95QPqP2';
    //    $this->paytrService = $paytrService;

    
        $this->base_url = "https://www.paytr.com/odeme/api/get-token";
    }

    public function initiatePaymentNew(Request $request)
    {$merchantId = env('PAYTR_MERCHANT_ID');
        $merchantKey = env('PAYTR_MERCHANT_KEY');
        $merchantSalt = env('PAYTR_MERCHANT_SALT');
        $merchantOid = uniqid(); // Unique payment ID (you can generate it as needed)
        
        // Payment Details
        $amount = 1450; // Amount in kuruş (14.50 TL = 1450 kuruş)
        $currency = 'TL'; // Currency
        $userPhone = '8978988789'; // Customer phone
        $userEmail = 'sd@yopmail.com'; // Customer email
        $userName = 'asas'; // Customer name
        // Get the user's IP address (try the forwarded IP first, then fallback to the client IP)
        $userIp = $request->header('X-Forwarded-For') ? $request->header('X-Forwarded-For') : $request->ip(); 
        $userAddress = 'jaids jaipur india'; // Customer address
        $testCard = '9792030394440796';  // Test card number
        $testCVV = '000';  // CVV should be 000
        $testExpireDate = '12/23';  
        $expiry_month = '12';
        $expiry_year = '30';
        $cc_owner = 'as';
        
        // Product Details
        $productName = 'Product Name';
        $quantity = 1;
        $productPrice = 1450; 
        $basket = [
            [
                'product_id' => 1, // Example product ID
                'name' => $productName,
                'price' => $productPrice,
                'quantity' => $quantity,
                'category' => 'category_name', // Optional category, if needed
            ]
        ];
        $merchant_ok_url = "https://ayva.stage04.obdemo.com/paytr/callback-ptr";
        $merchant_fail_url = "https://ayva.stage04.obdemo.com/paytr/callback-ptr";
        
        // Payment parameters
        $params = [
            'merchant_id' => $merchantId,
            'merchant_key' => $merchantKey,
            'merchant_salt' => $merchantSalt,
            'merchant_oid' => $merchantOid,
            'user_ip' => $userIp,
            'user_phone' => $userPhone,
            'user_address' => $userAddress,
            'card_number' => $testCard,
            'cvv' => $testCVV,
            'expire_date' => $testExpireDate,
            'email' => $userEmail,
            'user_name' => $userName,
            'cc_owner' => $cc_owner,
            'payment_amount' => $amount,
            'expiry_month' => $expiry_month,
            'expiry_year' => $expiry_year,
            'currency' => $currency,
            'payment_type' => 'card', // Typically 1 for single installment
            'installment_count' => "0", // Add this line for installment count (1 for no installments, set > 1 for installment plans)
            'user_basket' => json_encode($basket),
            'merchant_ok_url' => $merchant_ok_url,
            'merchant_fail_url' => $merchant_fail_url,
        ];
        
        // Generate hash for validation
        $hashString = $merchantKey . $merchantOid . $amount . $currency . $userPhone . $userEmail . $userAddress . $userIp;
        $paytrHash = base64_encode(hash('sha256', $hashString, true));  // Fixed hash generation
        
        $params['hash'] = $paytrHash; // Add hash to params
        
        // Prepare the POST request
        $url = 'https://www.paytr.com/odeme/';  // PayTR API endpoint
        
        // Initialize cURL
        $ch = curl_init();
        
        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
        ]);
        
        // Execute the request and get the response
        $response = curl_exec($ch);
        
        // Check for cURL errors
        if ($response === false) {
            $error = curl_error($ch);
            // Handle the error
            dd("cURL Error: $error");
        }
        
        // Close cURL session
        curl_close($ch);
        
        // Handle the response
        $responseData = json_decode($response, true);
        dd($responseData);  // For debugging, print the response
        

dd($response);
        die;
        // Retrieve credentials from environment variables
        $merchantId = env('PAYTR_MERCHANT_ID');
        $merchantKey = env('PAYTR_MERCHANT_KEY');
        $merchantSalt = env('PAYTR_MERCHANT_SALT');
        $merchantOid = uniqid(); // Unique payment ID (you can generate it as needed)
    
        // Set the mode (test/live)
        $mode = env('PAYTR_MODE', 'test'); // default to test mode if not specified
    
        // Set the product details
        $productName = 'Product Name'; // Ensure this is a non-null string
        $price = 14.5; // Ensure this is a valid numeric value
        $quantity = 1; // Quantity must be a positive number
    
        // Create the PaytrProduct instance
        $product = new PaytrProduct($productName, $price, $quantity);
    
        // Create a basket and add the product
        $basket = new Basket();
        $basket->addProduct($product, $quantity);
    
        // Create the payment object
        $payment = new \Gizemsever\LaravelPaytr\Payment\Payment();  // Instantiate Payment class

        // Set the payment details
        $payment->setCurrency('TRY')
            ->setUserPhone('8978988789')
            ->setUserAddress('jaids jaipur india')
            ->setNoInstallment(1)
            ->setMaxInstallment(1)
            ->setEmail('sd@yopmail.com')
            ->setMerchantOid($merchantOid)  // Unique payment ID
            ->setUserIp($request->ip())
            ->setPaymentAmount(1450)  // Amount in kuruş (e.g., 14.50 TL = 1450 kuruş)
            ->setUserName('asas')
            ->setBasket($basket);
          //  ->setPaymentType('card'); 
        // Add merchant credentials directly to the payment object
        $payment->merchantId = $merchantId;
        $payment->merchantKey = $merchantKey;
        $payment->merchantSalt = $merchantSalt;
        $payments = [
            'merchant_id' => $merchantId,
            'merchant_key' => $merchantKey,
            'merchant_salt' => $merchantSalt,
            'payment_type' => 'credit_card',
            'user_ip' => $request->ip(),
            'user_phone' => '8978988789',
            'user_address' => 'jaids jaipur india',
            'email' => 'sd@yopmail.com',
            'merchant_oid' => $merchantOid,
            'payment_amount' => 1450,
            'currency' => 'TRY',
            'basket' => $basket,  // Add the basket in the appropriate format
        ];
        // Create the payment request using the payment object
        $paymentRequest = \Paytr::createPayment($payments);  // Pass the Payment object
    
        // Log or return the payment request for debugging
    
        if ($paymentRequest->isSuccess()) {
            $token = $paymentRequest->getToken();
            $iframeUrl = 'https://www.paytr.com/odeme/guvenli/' . $token;
            return view('payment.iframe', compact('iframeUrl'));
        } else {
            // Handle payment initiation failure
            return back()->withErrors(['msg' => 'Payment initiation failed: ' . $paymentRequest->getReason()]);
        }
    }
    
    public function addCart(Request $request)
    {
        $validator                 =     Validator::make($request->all(), [
            'product_id'           =>    'required',
            'quantity'             =>    'required',
            'product_variant_id'   =>    'required'
        ]);
        if ($validator->fails()) {
            $response               =   array();
            $response['status']     =   400;
            $response['error']      =   $validator->errors();
            $response['message']    =   trans('messages.required_files_are_cannot_be_null');
            return response()->json(['error' => $response]);
        }
        $product_id_temp  = $request['product_id'];
        $product_quantiy  = $request['quantity'];
           $userDetails                  = Auth::guard('api')->user();
           $user_id                      = $userDetails->id;
           $input                        = $request->all();
           $product_id                   = $input['product_id'];
           $qty                          = $input['quantity'];
           $prev                         = DB::table('carts')->where('product_id', '=', $input['product_id'])->where('product_varient_id',$input['product_variant_id'])->where(function ($query) use ($user_id) { $query->where('user_id', '=', $user_id);})->get()->first();
           if (isset($prev)) {
               $response               =   array();
               $response['status']     =   400;
               $response['message']    =  trans('messages.product_was_already_in_your_cart');
               return response()->json(['success' => $response]);
           }
           $products                   =  DB::table('products')->where('id', '=', $input['product_id'])->first();
           $cart                       =  new Cart;
           $cart['user_id']            =  $user_id;
           $cart['product_id']         =  $input['product_id'];
           $cart['product_varient_id'] =  $input['product_variant_id'];
           $cart['qty']                =  $input['quantity'];
           $variantData = [];
            //    $product_variant_details = ProductVariant::where('id',$input['product_variant_id'])->first(); 
            $product_variant_details    =  DB::table('product_variants')->where('id',$input['product_variant_id'])->first();
               if ($product_variant_details) {
                   $variantData[] = [
                       'product_id' => $products->id,
                       'varient_id' => $product_variant_details->id,
                       'price'      => $product_variant_details->price,
                       'quantity'   => $input['quantity'],
                   ];
               }
           $cart['varient_data']        =    json_encode($variantData);
           $price                       =    $product_variant_details->price * $input['quantity'];
           $cart['price']               =    $products->price ?? $price;
           $cart->save(); 
           $cartCount                   =    DB::table('carts')->where('user_id', '=', $user_id)->count();
           $response                    =   array();
           $response['status']          =   200;
           $response['cart_count']      =   $cartCount;
           $response['message']         =  trans('messages.successfully_added_to_cart');
           return response()->json(['success' => $response]);
    
    }
    public function listCart(Request $request)
    { 
            $lang			                           =	App::getLocale();
            $User                                      =    Auth::guard('api')->user();
            $user_id                                   =    $User->id;
            $allBlockUsersId = BlockUser::where('user_id',$User->id)->pluck('block_user_id');
            $shippingAddress                           =    ShippingAddressModel::where('user_id',$user_id)->where('is_deleted',0)->where('is_default',1)->select('id','name','city','town','district','country','address','phone_number','is_type','is_type_name')->first();
            $cartList                                  =    DB::table('carts')->whereNotIn('user_id', $allBlockUsersId)->where(function ($query) use ($user_id) { $query->where('user_id', '=', $user_id);})->get();
            $cartListsuggest = DB::table('carts')->where('user_id', $user_id)->get();
            $cartProductIds = $cartListsuggest->pluck('product_id')->toArray();
            $categoryIds = [];
            $subcategoryIds = [];
            foreach ($cartListsuggest as $cartItem) {
                $product = Product::find($cartItem->product_id);
                if ($product) {
                    $categoryIds[] = $product->parent_category;
                    $subcategoryIds[] = $product->category_level_2;
                }
            }
            $suggestProducts = Product::with(['userDetails', 'parentCategoryDetails', 'subCategoryDetails'])->where('is_active', 1)->where('is_approved', 1)->where('is_deleted', 0)->whereNotIn('id', $cartProductIds)->whereNotIn('user_id',$allBlockUsersId)->where(function($query) use ($categoryIds, $subcategoryIds) {  $query->whereIn('parent_category', $categoryIds)->orWhereIn('category_level_2', $subcategoryIds);})->inRandomOrder()->limit(4)->get();
            // if ($suggestProducts->count() < 5) {
            //     $additionalProducts = Product::with(['userDetails', 'parentCategoryDetails', 'subCategoryDetails'])->where('is_active', 1)->where('is_approved', 1)->whereNotIn('id', $suggestProducts->pluck('id')->toArray())->inRandomOrder()->limit(5 - $suggestProducts->count())->get();
            //     $suggestProducts = $suggestProducts->merge($additionalProducts);
            // }
            $suggestedProductsArray = [];
            foreach ($suggestProducts as $product) {
                $productImage = Category::find($product->category_level_2)->value('image') ?? Category::find($product->parent_category)->value('image');
                $variantData = ProductVariant::find($product->id);
                $variantColorDetails = null;
                $variantSizeDetails = null;
                if ($variantData) {
                    $variantColorDetails = ProductColor::with(['colorDetails', 'colorDetails.ColorsDescription'])
                    ->where('product_id', $variantData->product_id)
                    ->where('color_id', $variantData->color_id)
                    ->first();
                
                if ($variantColorDetails && $variantColorDetails->video) {
                    $cdnHostname = env("CDN_HOSTNAME");
                    
                    $variantColorDetails->video_thumbnail = "https://$cdnHostname/" . $variantColorDetails->video . "/thumbnail.jpg";
                    $variantColorDetails->video = "https://$cdnHostname/" . $variantColorDetails->video . "/playlist.m3u8";
                }                
                    $variantSizeDetails = ProductSize::with(['sizeDetails', 'sizeDetails.SizesDescription'])->where('product_id', $variantData->product_id)->where('size_id', $variantData->size_id)->first();
                }
                $ratingReviewArray = ReviewRating::where('product_id', $product->id)->get()->map(function($ratingReview) {  return array_merge($ratingReview->toArray(),['userName' => $ratingReview->user->name,'userImage' => $ratingReview->user->image,'created_at' => Carbon::parse($ratingReview->created_at)->diffForHumans(),]);});
                $avgRatingReview = ReviewRating::where('product_id', $product->id)->avg('rating');
                
                $ratingReviewed = ReviewRating::where('product_id', $product->id)->where('product_varient_id',$variantData->id)->where('user_id',Auth::guard('api')->user()->id)->first();
                $avgRatingReview = $avgRatingReview == 0 ? '0' : number_format($avgRatingReview, 1);
                $ratingReviewCount = ReviewRating::where('product_id', $request->product_id)->count();
                $formattedRatingReviewCount = formatCount($ratingReviewCount);
                if (strpos($formattedRatingReviewCount, 'k') !== false) {
                    $numericCount = floatval(str_replace('k', '', $formattedRatingReviewCount));
                    if ($numericCount <= 1) {
                        $formattedRatingReviewCount = $formattedRatingReviewCount . '';
                    } else {
                        $formattedRatingReviewCount = $formattedRatingReviewCount ;
                    }
                } else {
                    if ($formattedRatingReviewCount <= 1) {
                        $formattedRatingReviewCount = $formattedRatingReviewCount . '';
                    } else {
                        $formattedRatingReviewCount = $formattedRatingReviewCount ;
                    }
                }
                $suggestedProductsArray[] = [
                    'product_id'      => $product->id,
                    'product_name'    => $product->name,
                    'vendorDetails'   => $product->userDetails,
                    'product_image'   => $productImage,
                    'user_details'    => $product->userDetails,
                    'parent_category' => $product->parentCategoryDetails,
                    'sub_category'    => $product->subCategoryDetails,
                    'variant_data'    => $variantData,
                    'variant_color'   => $variantColorDetails,
                    'variant_size'    => $variantSizeDetails,
                    'RatingReviewArray' => [
                        'AvgRatingReview' => $avgRatingReview,
                        'RatingReviewList' => $ratingReviewArray,
                        'formattedRatingReviewCount' => $formattedRatingReviewCount,
                        'ratingReviewed' => $ratingReviewed,
                    ],
                ];
            } 
  
            if (isset($cartList)) {
            $cartData                              =    [];
            $i                                     =    0;
            $flags                                 =    0;         
            $t_sub_total                           =    0.0;
            foreach ($cartList as $key => $value) {
                $cartData[$i]['cart_id'] = $value->id;
                $cartData[$i]['product_id'] = $value->product_id;
                $cartData[$i]['product_variant_id'] = $value->product_varient_id;
                $ratingReviewArrayCart = ReviewRating::where('product_id', $value->product_id)
                ->get()
                ->map(function($ratingReview) {
                    return array_merge($ratingReview->toArray(), [
                        'userName' => $ratingReview->user->name,
                        'userImage' => $ratingReview->user->image,
                        'created_at' => Carbon::parse($ratingReview->created_at)->diffForHumans(),
                    ]);
                });
                $avgRatingReviewCart = ReviewRating::where('product_id', $value->product_id)->avg('rating');
                $avgRatingReviewCart = $avgRatingReviewCart ? number_format($avgRatingReviewCart, 1) : '0';                
                $ratingReviewedCart = ReviewRating::where('product_id', $value->product_id)
                ->where('product_varient_id', $value->product_varient_id)
                ->where('user_id', Auth::guard('api')->user()->id)
                ->first();                
                $ratingReviewCountCart = ReviewRating::where('product_id', $value->product_id)->count();
                $formattedRatingReviewCountCart = formatCount($ratingReviewCountCart);                
                // if (strpos($formattedRatingReviewCountCart, 'k') !== false) {
                // $formattedRatingReviewCountCart .= ' Review' . (floatval(str_replace('k', '', $formattedRatingReviewCountCart)) <= 1 ? '' : 's');
                // } else {
                // $formattedRatingReviewCountCart .= ' Review' . ($ratingReviewCountCart <= 1 ? '' : 's');
                // }
                $products                          =   Product::where('id', '=', $value->product_id)->get()->first();
                $productImage                      =   Category::find($products->category_level_2)?->value('image') ?? Category::find($products->parent_category)?->value('image');
                $Varientdata                       =   ProductVariant::where('id', $value->product_varient_id)->first();
                $varientcolorDetails               =   ProductColor::with(['colorDetails', 'colorDetails.ColorsDescription'])
                                                                    ->where('product_id', $Varientdata->product_id)
                                                                    ->where('color_id', $Varientdata->color_id)
                                                                    ->first();
            
            if ($varientcolorDetails) {
                $cdnHostname = env("CDN_HOSTNAME");
                
                $varientcolorDetails->video_thumbnail = "https://$cdnHostname/" . $varientcolorDetails->video . "/thumbnail.jpg";
                $varientcolorDetails->video = "https://$cdnHostname/" . $varientcolorDetails->video . "/playlist.m3u8";
            }
                $varientsizeDetails                  = ProductSize::with(['sizeDetails','sizeDetails.SizesDescription'])->where('product_id',$Varientdata->product_id)->where('size_id',$Varientdata->size_id)->first();
                $price                               = $Varientdata->price ?? $value->price;
                $price                               = is_numeric($price) ? (float)$price : 0.0;
                $varData                             = $value->varient_data ? json_decode($value->varient_data, true) : [];
                $varietnDetails                      = '';
                $cartData[$i]['max_stock']           = $Varientdata->stock_qty;
                $cartData[$i]['product_name']        = $products->name;
                $cartData[$i]['vendorDetails']       = $product->userDetails;
                $cartData[$i]['RatingReviewArray']   = [
                                                            'AvgRatingReview' => $avgRatingReviewCart,
                                                            'RatingReviewList' => $ratingReviewArrayCart,
                                                            'formattedRatingReviewCount' => $formattedRatingReviewCountCart,
                                                            'ratingReviewed' => $ratingReviewedCart,
                                                       ];
                $cartData[$i]['subcategory_image']   = $productImage;
                $cartData[$i]['parent_category_details'] = $products->parentCategoryDetails;
                $cartData[$i]['subCategoryDetails']  = $products->subCategoryDetails;
                $cartData[$i]['varientcolorDetails'] = $varientcolorDetails;
                $cartData[$i]['varientsizeDetails']  = $varientsizeDetails;
                $cartData[$i]['quantity']            = $value->qty;
                $cartData[$i]['unit_price']          = round($price, 2);
                $sub_total                           = $price * (float)$value->qty;
                $cartData[$i]['sub_total']           = round($sub_total, 2);
                $t_sub_total += $sub_total; 
                $i++;
            }
            $priceDetails                            =    [];
            if($request->coupon_code){
                // $coupon = Coupon::where('code', $request->coupon_code)->first();
            //    if ($coupon && $coupon->min_amount <= $t_sub_total) {
            //        $discountAmount = $this->calculateDiscount($t_sub_total, $coupon);
            //    }
                    $couponCode = $request->coupon_code;
                     $shipping_amount = Config::get('shipping.shipping_amount', 0);
                     $orderAmount = $t_sub_total;
                     $currentDate = now();
                     $priceDetails = [
                     'success' => false,
                     'message' => trans('messages.coupon_invalid'),
                     'is_default' => 0,
                     ];
                $coupon = Coupon::where('code', $couponCode)->first();  
                if (!$coupon) {
                      $priceDetails['message'] = trans('messages.coupon_not_found');
                      $priceDetails['total']                =    $orderAmount;
                      $priceDetails['amount']               =    $orderAmount;
                      $priceDetails['discount']             =    0;
                      $priceDetails['shipping_amount']      =   Config::get('shipping.shipping_amount') ?? "0";
                      $priceDetails['total_mrp']            =  $orderAmount + $shipping_amount;
                      $priceDetails['success']              =  false;  
                      $priceDetails['coupon_code']          =  $request->coupon_code ?? "";
              } elseif ($coupon->start_date > $currentDate) {
                      $priceDetails['message'] = trans('messages.coupon_not_started');
                      $priceDetails['total']                =    $orderAmount;
                      $priceDetails['amount']               =    $orderAmount;
                      $priceDetails['discount']             =    0;
                      $priceDetails['shipping_amount']      =   Config::get('shipping.shipping_amount') ?? "0";
                      $priceDetails['total_mrp']            =  $orderAmount + $shipping_amount;
                      $priceDetails['success']              =  false;  
                      $priceDetails['coupon_code']          =  $request->coupon_code ?? "";
              } elseif ($coupon->end_date < $currentDate) {
                     $priceDetails['message'] = trans('messages.coupon_expired');
                      $priceDetails['total']                =    $orderAmount;
                      $priceDetails['amount']               =    $orderAmount;
                      $priceDetails['discount']             =    0;
                      $priceDetails['shipping_amount']      =   Config::get('shipping.shipping_amount') ?? "0";
                      $priceDetails['total_mrp']            =  $orderAmount + $shipping_amount;
                      $priceDetails['success']              =  false;  
                      $priceDetails['coupon_code']          =  $request->coupon_code ?? "";
              } else {
                  $maximum_use = $coupon->max_uses;
                  $userUseCountCoupon = Order::where('coupon_code', $couponCode)
                      ->where('user_id', Auth::guard('api')->user()->id)
                       ->where('coupon_id', $coupon->id)
                      ->count();
                  $couponUsageCount = Order::where('coupon_code', $couponCode)->count();
      
                  if ($userUseCountCoupon >= $coupon->per_person_use) {
                      $priceDetails['message'] = trans('messages.coupon_max_per_user_reached');
                      $priceDetails['total']                =  $orderAmount;
                      $priceDetails['amount']               =  $orderAmount;
                      $priceDetails['discount']             =  0;
                      $priceDetails['shipping_amount']      =  Config::get('shipping.shipping_amount') ?? "0";
                      $priceDetails['total_mrp']            =  $orderAmount + $shipping_amount;
                      $priceDetails['success']              =  false;  
                      $priceDetails['coupon_code']          =  $request->coupon_code ?? "";
                  } elseif ($couponUsageCount >= $maximum_use) {
                      $priceDetails['message'] = trans('messages.coupon_max_uses_reached');
                      $priceDetails['total']                =  $orderAmount;
                      $priceDetails['amount']               =  $orderAmount;
                      $priceDetails['discount']             =  0;
                      $priceDetails['shipping_amount']      =  Config::get('shipping.shipping_amount') ?? "0";
                      $priceDetails['total_mrp']            =  $orderAmount + $shipping_amount;
                      $priceDetails['success']              =  false;  
                      $priceDetails['coupon_code']          =  $request->coupon_code ?? "";
                  } elseif ($t_sub_total < $coupon->min_amount) {
                      $priceDetails['message'] = trans('messages.coupon_minimum_amount_not_met', ['min' => $coupon->min_amount]);
                      $priceDetails['total']                =  $orderAmount;
                      $priceDetails['amount']               =  $orderAmount;
                      $priceDetails['discount']             =  0;
                      $priceDetails['shipping_amount']      =  Config::get('shipping.shipping_amount') ?? "0";
                      $priceDetails['total_mrp']            =  $orderAmount + $shipping_amount;
                      $priceDetails['success']              =  false;  
                      $priceDetails['coupon_code']          =  $request->coupon_code ?? "";
                  } else {
                    $discountAmount = 0;
                    $vendorProductId = Product::find($value->product_id)->user_id; 
                    $productVarientPrice = ProductVariant::find($value->product_varient_id)->price;          
                       if ($vendorProductId == $coupon->user_id) {                
                           $productPrice = $productVarientPrice * $value->qty;                
                           if ($coupon->type == "discount_by_per") {
                               $productDiscount = ($productPrice * $coupon->is_per) / 100;                    
                               if ($coupon->maximum_amount && $productDiscount > $coupon->maximum_amount) {
                                   $productDiscount = $coupon->maximum_amount;
                               }
                           } elseif ($coupon->type == "discount_by_amount") {
                               $productDiscount = $coupon->is_amount;
                               
                           } else {
                               $productDiscount = 0;
                           }
                           $discountAmount += $productDiscount;
                       }
                      $discountAmount = number_format($discountAmount, 2, '.', '');
                      $finalAmount = number_format($t_sub_total - $discountAmount, 2, '.', '');            
                      $priceDetails = [
                          'success' => true,
                          'message' => trans('messages.coupon_applied_successfully'),
                          'id' => $coupon->id,
                          'coupon_code' => $coupon->code,
                          'amount' => $t_sub_total,
                          'shipping_amount' => $shipping_amount,
                          'discount' => $discountAmount,
                          'total' => $t_sub_total,
                          'is_default' => 1,
                          'total_mrp' => $finalAmount + $shipping_amount,
                      ];
                  }
              }
          
            // $total = round($t_sub_total - $discountAmount, 2);
            // $priceDetails['discount'] = round($discountAmount, 2);  
            }else{
                $priceDetails['discount']             =   0;
                $total                                =  round($t_sub_total, 2);
                $priceDetails['total']                =  $total;
                $priceDetails['amount']               =  $total;
                $priceDetails['shipping_amount']      =  Config::get('shipping.shipping_amount') ?? "0";
                $priceDetails['total_mrp']            =  $total + Config::get('shipping.shipping_amount') ?? 0;
                $priceDetails['success']              =  true;  
                $priceDetails['coupon_code']          =  $request->coupon_code ?? "";  
            }
            // $priceDetails['total']                =    $total;
            // $priceDetails['shipping_amount']      =   Config::get('shipping.shipping_amount') ?? "0";
            // $priceDetails['total_mrp']            =  $finalAmount + $shipping_amount;
            // $priceDetails['success']              =  true;  
            // $priceDetails['coupon_code']          =  $request->coupon_code ?? "";            
            $response                             =   array();
            $response['status']                   =   200;
            $response['message']                  =   trans('messages.successfully_executed');
            $response['data']['cart_list']        =    $cartData;
            $response['data']['suggestedProductsArray']  =    $suggestedProductsArray;
            $response['data']['price_details']    =    $priceDetails;
            $response['data']['shippingAddress']          = $shippingAddress;
            return response()->json(['success' => $response]);
        } else {
            $response               =   array();
            $response['status']     =   400;
            $response['message']    =   trans('messages.data_not_found');
            return response()->json(['success' => $response]);
        }
    }
    private function calculateDiscount($price, Coupon $coupon)
    {
        if ($coupon->type == "discount_by_per") {
            return ($price * $coupon->is_per) / 100;
        } elseif ($coupon->type == "discount_by_amount") {
            return $coupon->is_amount;
        }
        return 0;
    }
    public function updateQuantity(Request $request)
    {
        $validator                 =     Validator::make($request->all(), [
            'cart_id'             =>     'required',
            'quantity'            =>    'required',
        ]);
        if ($validator->fails()) {
            $response                   =   array();
            $response['status']         =   400;
            $response['error']          =  $validator->errors();
            return response()->json(['error' => $response]);
        }
            $input                      =    $request->all();
            $cartData                   =    DB::table('carts')->where('id', '=', $input['cart_id'])->get()->first();
            if (!isset($cartData)) {
                $response               =   array();
                $response['status']     =   400;
                $response['message']    =   trans('messages.product_was_not_found_in_your_cart_list');
                return response()->json(['success' => $response]);
            }
            $products                   =     DB::table('products')->where('id', '=', $cartData->product_id)->get()->first();
            $Varientdata                =     ProductVariant::where('id', $cartData->product_varient_id)->first();
            if (!isset($products)) {
                $response               =   array();
                $response['status']     =   400;
                $response['message']    =   trans('messages.product_was_not_found_in_our_list');
                return response()->json(['success' => $response]);
            }
            $variantData = [
                'product_id' => $products->id,
                'variant_id' => $Varientdata->id,
                'price' => $Varientdata->price,
                'quantity' => $input['quantity'],
            ];
            if (@$Varientdata->stock_qty != 0  && @$Varientdata->stock_qty >= $input['quantity']) {
                $price                 =    $Varientdata->price;
                DB::table('carts')->where('id', $input['cart_id'])->update(['qty' => $input['quantity'],'price' => $price,'varient_data' => json_encode($variantData),'updated_at' => date('Y-m-d H:i:s')]);
                $response               =   array();
                $response['status']     =   200;
                $response['message']    =  trans('messages.successfully_update_cart_product_quantity');
                return response()->json(['success' => $response]);
            } else {
                $response               =   array();
                $response['status']     =   200;
                $response['message']    =   trans('messages.requested_number_of_quantity_not_available');
                return response()->json(['success' => $response]);
            }
    } 
    public function removeCart(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cart_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'status' => 400,
                    'error'  =>  $validator->errors(),
                ]
            ]);
        }
        $user_id = Auth::guard('api')->user()->id;
        $cart_id = $request->input('cart_id');
        $cart = DB::table('carts')->where('user_id', $user_id)->where('id', $cart_id)->first();
        if (!$cart) {
            return response()->json([
                'error' => trans('messages.cart_not_found')
            ], 404);
        }
        $deleted = DB::table('carts')->where('user_id', $user_id)->where('id', $cart_id)->delete();
        if ($deleted) {
            return response()->json([
                'success' => [
                    'status' => 200,
                    'message' => trans('messages.cart_was_successfully_removed')
                ]
            ]);
        } else {
            return response()->json([
                'error' => trans('messages.item_not_found_in_cart')
            ], 404);
        }
    }
    public function checkToken($token =    '')
    {
        if ($token == '') {
            return false;
        }
        $all                         =     DB::table('users')->where('app_token', $token)->first();
        return $all;
    }
    public static function getVarname($varId)
    { 
        $lang			=	App::getLocale();
        if($lang == 'en'){
        $varient     = ProductVariant::where('id', $varId)->first();
        }else{
            $varient = ProductVariant::where('id', $varId)->first();

        }
        return $varient;
    }

    public function sinitiatePayment(Request $request)
    {
        // Configuration
        $merchant_id = '523380';//env('PAYTR_MERCHANT_ID');
        $merchant_key = 'EkrPt6wsUqxrxddM';//env('PAYTR_MERCHANT_KEY');
        $merchant_salt = 'dq9PYiZWe95QPqP2';//env('PAYTR_MERCHANT_SALT');
        $test_mode = 1;//env('PAYTR_TEST_MODE', true) ? 1 : 0;

        // User Data
        $user_ip = $request->ip();
        $email = 'laks@yopmail.com';
        $user_name = "John Doe"; 
        $user_address ='asass';
        $user_phone ='12345678';
        // Payment Data
        $merchant_oid = uniqid(); // Unique Order ID
        $payment_amount = 10000; // Payment amount in cents (100 TRY = 10000)
        $currency = "TRY";
        $user_basket = base64_encode(json_encode([
            ['Product 1', 100, 1], // Example: ['Product Name', Price (in TRY), Quantity]
        ]));
        $no_installment = 0; // Disable installment
        $max_installment = 0; // Max installment options

        // Generate Token
        $hash_str = $merchant_id . $user_ip . $merchant_oid . $email . $payment_amount . $user_basket . $no_installment . $max_installment . $currency . $test_mode;
        $paytr_token = base64_encode(hash_hmac('sha256', $hash_str . $merchant_salt, $merchant_key, true));
        $merchant_ok_url = 'https://yourdomain.com/paytr/success'; // URL for successful payments
        $merchant_fail_url = 'https://yourdomain.com/paytr/fail';  
        // Payment Request Data
        $payment_data = [
            'merchant_id' => $merchant_id,
            'user_ip' => $user_ip,
            'merchant_oid' => $merchant_oid,
            'email' => $email,
            'user_name' => $user_name, 
            'user_phone'=>$user_phone,
            'user_address'=>$user_address,
            'payment_amount' => $payment_amount,
            'paytr_token' => $paytr_token,
            'user_basket' => $user_basket,
            'currency' => $currency,
            'test_mode' => $test_mode,
            'no_installment' => $no_installment,
            'max_installment' => $max_installment,
            'merchant_ok_url' => $merchant_ok_url, 
            'merchant_fail_url' => $merchant_fail_url, 
    
        ];
        $client = new Client();
        $response = $client->post('https://www.paytr.com/odeme/api/get-token', [
            'form_params' => $payment_data,
        ]);
    
        $responseBody = json_decode($response->getBody(), true);
    
        if ($responseBody['status'] === 'success') {
            // Return iframe URL
            $iframeUrl = "https://www.paytr.com/odeme/iframe?token={$responseBody['token']}";
            return response()->json([
                'status' => 'success',
                'iframe_url' => $iframeUrl,
            ]);
        }

        return back()->withErrors(['error' => $responseBody['reason']]);
    }

   // Generate Payment Link - Function 1
public function generatePaymentLink(Request $request)
{
    $merchant_id   = '523380'; // Your PayTR Merchant ID
    $merchant_key  = 'EkrPt6wsUqxrxddM'; // Your PayTR Merchant Key
    $merchant_salt = 'dq9PYiZWe95QPqP2'; // Your PayTR Merchant Salt

    // Product or Service Details
    $name = "Örnek Ürün / Hizmet Adı";
    $price = 1445;
    $currency = "TL";
    $max_installment = "12"; 
    $link_type = "product"; 
    $lang = "en";

    $min_count = 1;
    $email = time() . "@example.com"; 

    $expiry_date = now()->addDay()->setTimezone('UTC')->format('Y-m-d H:i:s'); // Expiry time set to 1 day from now
    $max_count = "1";
    $callback_link = "https://ayva.stage04.obdemo.com/paytr/callback-ptr";
    $callback_id = 'paytr' . Str::random(8);
    $get_qr = 1; // Get QR code
    // Prepare data for token generation
    $required = $name . $price . $currency . $max_installment . $link_type . $lang;
    if ($link_type == "product") {
        $required .= $min_count;
    } elseif ($link_type == "collection") {
        $required .= $email;
    }

    $debug_on             = 1;
    $paytr_token          = base64_encode(hash_hmac('sha256', $required . $merchant_salt, $merchant_key, true));

    $post_data = [
        'merchant_id'     => $merchant_id,
        'name'            => $name,
        'price'           => $price,
        'currency'        => $currency,
        'max_installment' => $max_installment,
        'link_type'       => $link_type,
        'lang'            => $lang,
        'min_count'       => $min_count,
        'email'           => $email,
        'expiry_date'     => $expiry_date,
        'max_count'       => $max_count,
        'callback_link'   => $callback_link,
        'callback_id'     => $callback_id,
        'get_qr'          => $get_qr,
        'paytr_token'     => $paytr_token,
        'debug_on'        => $debug_on,
    ];

    // Send POST request to PayTR API
    $response = Http::asForm()->post('https://www.paytr.com/odeme/api/link/create', $post_data);

    // Handle the response
    $result = $response->json();

    if ($result['status'] === 'error') {
        return response()->json(['error' => $result['err_msg']]);
    } elseif ($result['status'] === 'failed') {
        return response()->json(['error' => 'Payment link creation failed']);
    } else {
        // Success - Payment link created
        // Return payment link and QR code if requested
        return response()->json([
            'status' => 'success',
            'unique_id'=>$result['id'],
            'payment_url' => $result['link'], // Payment link URL
            'qr_code' => $result['base64_qr'] ?? null, // Base64 QR code if available
        ]);
    }
}
public function processCallbackSucess(Request $request)
{
    echo "OK";
    exit;
    // // Retrieve data from PayTR callback
    // $status = $request->input('status'); // This should be "success" or "failure"
    // $order_id = $request->input('order_id'); // Order ID from PayTR
    // $payment_status = $request->input('payment_status'); // Payment status (e.g., 'success', 'failure')

    // if ($status === 'success' && $payment_status === 'success') {
    //     // Process successful payment (e.g., mark the order as paid, send email confirmation, etc.)
    //     // Example: Save to database or send an email
    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Payment completed successfully.',
    //         'order_id' => $order_id,
    //     ]);
    // } else {
    //     // Handle payment failure
    //     return response()->json([
    //         'status' => 'error',
    //         'message' => 'Payment failed.',
    //     ]);
    // }
}

public function processCallbackFail(Request $request)
{
    echo "OK";
    exit;
    // // Retrieve data from PayTR callback
    // $status = $request->input('status'); // This should be "success" or "failure"
    // $order_id = $request->input('order_id'); // Order ID from PayTR
    // $payment_status = $request->input('payment_status'); // Payment status (e.g., 'success', 'failure')

    // if ($status === 'success' && $payment_status === 'success') {
    //     // Process successful payment (e.g., mark the order as paid, send email confirmation, etc.)
    //     // Example: Save to database or send an email
    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Payment completed successfully.',
    //         'order_id' => $order_id,
    //     ]);
    // } else {
    //     // Handle payment failure
    //     return response()->json([
    //         'status' => 'error',
    //         'message' => 'Payment failed.',
    //     ]);
    // }
}
// Callback Processing - Function 2
public function processCallback(Request $request)
{
    $json_string = json_encode($request->all());
    $file_handle = fopen('payment_response.json', 'w');
    fwrite($file_handle, $json_string);
    fclose($file_handle);
    
    

    
    echo "OK";
    exit;
    // // Retrieve data from PayTR callback
    // $status = $request->input('status'); // This should be "success" or "failure"
    // $order_id = $request->input('order_id'); // Order ID from PayTR
    // $payment_status = $request->input('payment_status'); // Payment status (e.g., 'success', 'failure')

    // if ($status === 'success' && $payment_status === 'success') {
    //     // Process successful payment (e.g., mark the order as paid, send email confirmation, etc.)
    //     // Example: Save to database or send an email
    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Payment completed successfully.',
    //         'order_id' => $order_id,
    //     ]);
    // } else {
    //     // Handle payment failure
    //     return response()->json([
    //         'status' => 'error',
    //         'message' => 'Payment failed.',
    //     ]);
    // }
}
public function makePayment(Request $request)
{  
//     $merchant_id = '523380';
//     $merchant_key = 'EkrPt6wsUqxrxddM';
//     $merchant_salt = 'dq9PYiZWe95QPqP2';

//     $merchant_ok_url = "https://ayva.stage04.obdemo.com/paytr/callback-ptr";
//     $merchant_fail_url = "https://ayva.stage04.obdemo.com/paytr/callback-ptr";

//     $user_basket = json_encode([
//         ["Altis Renkli Deniz Yatağı - Mavi", "18.00", 1],
//         ["Pharmasol Güneş Kremi 50+ Yetişkin & Bepanthol Cilt Bakım Kremi", "33.25", 2],
//         ["Bestway Çocuklar İçin Plaj Seti Beach Set ÇANTADA DENİZ TOPU-BOT-KOLLUK", "45.42", 1]
//     ]);

//     $merchant_oid = rand(); // Unique order ID

//     $test_mode = "0"; // Set to "1" for testing
//     $non_3d = "0"; // For non-3D payments

//     $user_ip = request()->ip(); // Get user's IP
//     $email = "testnon3d@paytr.com"; // User's email

//     $payment_amount = "100.99";
//     $currency = "TL";
//     $payment_type = "card"; // Card payment

//     $installment_count = "0"; // No installments

//     // Prepare the hash string
//     $hash_str = $merchant_id . $user_ip . $merchant_oid . $email . $payment_amount . $payment_type . $installment_count . $currency . $test_mode . $non_3d;
//     $token = base64_encode(hash_hmac('sha256', $hash_str . $merchant_salt, $merchant_key, true));

//     $post_url = "https://www.paytr.com/odeme";

//     $data = [
//         'merchant_id' => $merchant_id,
//         'user_ip' => $user_ip,
//         'merchant_oid' => $merchant_oid,
//         'email' => $email,
//         'payment_type' => $payment_type,
//         'payment_amount' => $payment_amount,
//         'installment_count' => $installment_count,
//         'currency' => $currency,
//         'test_mode' => $test_mode,
//         'non_3d' => $non_3d,
//         'merchant_ok_url' => $merchant_ok_url,
//         'merchant_fail_url' => $merchant_fail_url,
//         'user_name' => 'Paytr Test',
//         'user_address' => 'test test test',
//         'user_phone' => '05555555555',
//         'user_basket' => $user_basket,
//         'debug_on' => 1,
//         'paytr_token' => $token,
//         'non3d_test_failed' => 0,
//         'utoken' => '',
//         'ctoken' => '',
//         'card_number' => '9792030394440796', // Card number
//         'cc_owner' => 'sdsd', // Card owner
//         'expiry_month' => '12', // Expiry month
//         'expiry_year' => '30', // Expiry year
//         'cvv' => '000', // CVV
//     ];

//     // Send the POST request to PayTR
//     $response = Http::post($post_url, $data);
// dd($response);

//     die;
    $merchant_id = '523380';  // Your PayTR Merchant ID
    $merchant_key = 'EkrPt6wsUqxrxddM';  // Your PayTR Merchant Key
    $merchant_salt = 'dq9PYiZWe95QPqP2';  // Your PayTR Merchant Salt
    
    // Customer details
    $user_ip ="115.246.90.221";// $request->ip();  // User's IP address (e.g., from request)
    $email = "test@example.com";  // Replace with actual user email
    $payment_amount = 10099;  // 100.99 TL (PayTR expects amount in KURUŞ, so multiply by 100)
    $currency = "TL";
    $payment_type = "card";  // Payment method: Card
    $installment_count = 0;  // Installment: 0 (no installment)
    $non_3d = 1;  // No 3D Secure for testing
    $test_mode = "1";  // Test mode: 1 (enable testing)
    
    // Card Details (replace with actual user input)
    $card_number = '9792030394440796';  // Example card number (do not use real data in tests)
    $expiry_month = '12';  // Card expiration month
    $expiry_year = '99';  // Card expiration year
    $cvv = "000";  // CVV of the card
    $cc_owner = "TEST KARTI";  // Card owner's name
    
    // User Information (replace with actual data)
    $user_name = "Test User";  // User's name
    $user_address = "Test Address, Merzifon, Amasya";  // User's address
    $user_phone = "5523627811";  // User's phone number
    
    // Create a unique order ID
    $merchant_oid = uniqid();
    
    // User Basket (Base64 encoded)
    $user_basket = [
        ["Test Product", "10099", 1]  // Product name, price (kurus), quantity
    ];
    $user_basket_encoded = base64_encode(json_encode($user_basket));
    
    // Generate PayTR Token
    $hash_str = $merchant_id . $user_ip . $merchant_oid . $email . $payment_amount . $payment_type . $currency . $user_basket_encoded . $installment_count . $non_3d . $test_mode;
    $paytr_token = base64_encode(hash_hmac('sha256', $hash_str . $merchant_salt, $merchant_key, true));
    // Prepare the POST data to send to PayTR
    $post_data = [
        'merchant_id' => $merchant_id,
        'user_ip' => $user_ip,
        'merchant_oid' => $merchant_oid,
        'email' => $email,  // User email
        'payment_type' => $payment_type,
        'payment_amount' => $payment_amount,
        'currency' => $currency,
        'installment_count' => $installment_count,
        'cc_owner' => $cc_owner,
        'card_number' => $card_number,
        'expiry_month' => $expiry_month,
        'expiry_year' => $expiry_year,
        'cvv' => $cvv,
        'no_installment'=>"0",
        "max_installment"=>"0",
        "lang"=>"en",
        'paytr_token' => $paytr_token,  // The token generated
        'merchant_ok_url' => 'https://aztinternational.com/payment/success',  // Success URL
        'merchant_fail_url' => 'https://aztinternational.com/payment/fail',  // Failure URL
        'test_mode' => $test_mode,  // For testing purposes
        'user_name' => $user_name,  // User name
        'user_address' => $user_address,  // User address
        'user_phone' => $user_phone,  // User phone number
        'user_basket' => $user_basket_encoded  // Encoded user basket
    ];
    
    // Send the POST data to PayTR
    $paytr_url = "https://www.paytr.com/odeme";  // PayTR payment page URL
    
    // Initialize cURL session
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $paytr_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);  // Disable SSL verification for testing
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);  // Set timeout
    
    // Execute the request and get the response
    $response = curl_exec($ch);
    dd($response);  // Dump the response data for debugging

    // Check for cURL errors
    if ($response === false) {
        echo 'cURL Error: ' . curl_error($ch);
    } else {
        // Decode the JSON response from PayTR
        $response_data = json_decode($response, true);
        dd($response_data);  // Dump the response data for debugging
    }
    
    // Close the cURL session
    curl_close($ch);

die;
    $merchant_id = '523380'; 
    $merchant_key = 'EkrPt6wsUqxrxddM'; 
    $merchant_salt = 'dq9PYiZWe95QPqP2'; 
    $merchant_oid = uniqid();
    $user_ip = $request->ip(); 
    $email = "test@paytr.com"; 
    $payment_amount = "10099"; 
    $currency = "TL";
    $payment_type = "card"; 
    $installment_count = "0";

    $card_number = '9792030394440796';
    $expiry_month = '12';
    $expiry_year = '99';
    $cvv = "000";
    $cc_owner = "TEST KARTI";
    $user_name = $request->user_name ?? "Paytr Test";
    $user_address = $request->user_address ?? "test test test";
    $user_phone = $request->user_phone ?? "05555555555";
    $user_basket = [
        ["Test Product", "10099", 1]
    ];
    $user_basket_encoded = base64_encode(json_encode($user_basket));
    
    $hash_str = $merchant_id 
                . $user_ip 
                . $merchant_oid 
                . $email 
                . $payment_amount 
                . $payment_type 
                . $installment_count 
                . $currency 
                . $user_basket_encoded 
                . "0"
                . "0";
    
    $paytr_token = base64_encode(hash_hmac('sha256', $hash_str . $merchant_salt, $merchant_key, true));
    $paytr_url = "https://www.paytr.com/odeme";
    
    $post_data = [
        'merchant_id' => $merchant_id,
        'user_ip' => $user_ip,
        'merchant_oid' => $merchant_oid,
        'email' => $email,
        'payment_type' => $payment_type,
        'payment_amount' => $payment_amount,
        'currency' => $currency,
        'installment_count' => $installment_count,
        'cc_owner' => $cc_owner,
        'card_number' => $card_number,
        'expiry_month' => $expiry_month,
        'expiry_year' => $expiry_year,
        'cvv' => $cvv,
        'paytr_token' => $paytr_token,
        'merchant_ok_url' => 'http://ayva.stage04.obdemo.com', // Your success URL
        'merchant_fail_url' => 'http://ayva.stage04.obdemo.com', // Your failure URL
        'test_mode' => "1", // Enable test mode for testing
        'non_3d' => "1", // If 3D Secure is not required
        'user_name' => $user_name,
        'user_address' => $user_address,
        'user_phone' => $user_phone,
        'user_basket' => $user_basket_encoded
    ];
    
    // Initialize cURL session
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $paytr_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Execute the cURL request
    $result = curl_exec($ch);
    curl_close($ch);
    
    // Decode response
    $response = json_decode($result, true);
        dd($response);
    die;
    // PayTR Credentials
    $merchant_id = '523380'; // Your PayTR Merchant ID
    $merchant_key = 'EkrPt6wsUqxrxddM'; // Your PayTR Merchant Key
    $merchant_salt = 'dq9PYiZWe95QPqP2'; // Your PayTR Merchant Salt
    $test_mode = '1'; // Test mode

    // Dummy card details for testing
    $card_number = '4355084355084358'; // Example card number
    $expiry_month = '12';
    $expiry_year = '30';
    $cvv = '000';
    $payment_amount = '10000'; // Amount in kuruş (1 TL = 100 kuruş)
    $email = 'test@example.com';
    $user_ip = $request->ip();
    $merchant_oid = Str::random(12); // Unique order ID

    // Prepare user basket in JSON format (base64-encoded)
    $user_basket = base64_encode(json_encode([["Product 1", "1", $payment_amount]]));
    
    // Set installment_count to 1 (as a string, since that's common)
    $installment_count = "0";

    // Generate the hash string
    $hash_str = $merchant_id . $user_ip . $merchant_oid . $email . $payment_amount . 'TL' . 'card' . $installment_count . $test_mode;
    $paytr_token = base64_encode(hash_hmac('sha256', $hash_str . $merchant_salt, $merchant_key, true));

    // Ensure user_name and cc_owner are properly set
    $user_name = 'John Doe';  // Ensure this is a valid name string, try to keep it simple (no special characters)
    $cc_owner = 'John Doe';   // The cardholder's name

    // Create the payment data array
    $payment_data = [
        'merchant_id' => $merchant_id,
        'user_ip' => $user_ip,
        'merchant_oid' => $merchant_oid,
        'email' => $email,
        'payment_amount' => $payment_amount,
        'currency' => 'TL',
        'payment_type' => 'card',
        'card_number' => $card_number,
        'expiry_month' => $expiry_month,
        'expiry_year' => $expiry_year,
        'cvv' => $cvv,
        'user_basket' => $user_basket,
        'test_mode' => $test_mode,
        'merchant_fail_url' => 'https://yourwebsite.com/fail', // Test URL
        'merchant_ok_url' => 'https://yourwebsite.com/success', // Test URL
        'paytr_token' => $paytr_token,
        'installment_count' => $installment_count, // Ensure this is either '1' or '2', or valid number
        'user_name' => $user_name,
        'cc_owner' => $cc_owner,
        // Do not include 'card_type' as it's optional
    ];

    // Send the request to PayTR API
    $response = Http::asForm()->post('https://www.paytr.com/odeme', $payment_data);


    // Check the response
    $result = $response->json();
    
    // Output the response for debugging
    dd($result); // Dump the response
}




public function generatePaymentToken(Request $request)
{
    $merchant_id   = '523380'; // Your PayTR Merchant ID
    $merchant_key  = 'EkrPt6wsUqxrxddM'; // Your PayTR Merchant Key
    $merchant_salt = 'dq9PYiZWe95QPqP2'; // Your PayTR Merchant Salt
    $payt_api_url  = 'https://www.paytr.com/odeme/api/get-token';


        // Get payment details from the request (Example)
        $payment_amount = $request->input('payment_amount', 100.99); // Example payment amount
        $email = $request->input('email', 'testnon3d@paytr.com'); // Example email
        $currency = 'TL'; // Payment currency (Turkish Lira)
        $user_ip = $request->ip(); // User's IP
        $payment_type = 'card'; // Payment type (card)

        // Generate unique merchant order ID
        $merchant_oid = rand();

        // Create the hash string for PayTR token
        $hash_str = $merchant_id . $user_ip . $merchant_oid . $email . $payment_amount . $payment_type . $currency;
        $token = base64_encode(hash_hmac('sha256', $hash_str . $merchant_salt, $merchant_key, true));

        // Prepare the payment data
        $payment_data = [
            'merchant_id' => $merchant_id,
            'user_ip' => $user_ip,
            'merchant_oid' => $merchant_oid,
            'email' => $email,
            'payment_type' => $payment_type,
            'payment_amount' => $payment_amount,
            'currency' => $currency,
            'test_mode' => 0, // 0 for live, 1 for test mode
            'non_3d' => 0, // 0 for 3D payment, 1 for non-3D payment
            'merchant_ok_url' => 'https://ayva.stage04.obdemo.com/paytr/callback-ptr', // Success URL (set the route)
            'merchant_fail_url' => 'https://ayva.stage04.obdemo.com/paytr/callback-ptr', // Failure URL (set the route)
            'paytr_token' => $token,
        ];

        // Send payment request to PayTR
        try {
            $response = Http::asForm()->post('https://www.paytr.com/odeme', $payment_data);
            $response_data = $response->json();
            dd($response_data);
            // Return the response from PayTR (this is the PayTR payment page URL)
            return response()->json([
                'status' => 'success',
                'payment_url' => $response->json()['url'], // Redirect URL from PayTR to complete payment
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error processing payment',
                'error' => $e->getMessage(),
            ], 500);
        }
}



public function initiatePaymentCard(Request $request)
{
    // Get order details from the request
    $orderId = $request->order_id;
    $order = Order::find($orderId);

    // Prepare data to generate token (using the data you provided)
    $data = [
        'order_id' => 123,
        'order_products' => [
            [
                'product_id' => 1,
                'name' => 'Product 1',
                'quantity' => 2,
                'price' => 10.00,
            ],
            [
                'product_id' => 2,
                'name' => 'Product 2',
                'quantity' => 1,
                'price' => 15.50,
            ],
        ],
        'amount' => 100,
        'email' =>'lak@yopmail.com',
        'first_name' => 'sls',
        'last_name' =>'asc',
        'currency' => 'TRY', // Or any other currency
        'address_data' => 'sd',
        'phone_number' => '3456788767',
        'language' => 'tr',
        'response_condition' => 'fd',
        'cc_owner' => 'hj',
        'card_number' => 9792030394440796,
        'expiry_month' => 12,
        'expiry_year' => 30,
        'cvv' => 000,
      ////  'shipping_data' => $request->shipping_data,
        'user_type' => $request->user_type,
    ];

    // Assuming you have the `generateToken` function in the `PaymentService` class
    $paymentService = new PaymentService();
    $paymentUrl = $paymentService->generateToken($data);

    // After token generation, update the order with PayTR process data
    // $order->update([
    //     'paytr_process_data' => json_encode($data), // Assuming your `Order` model has a `paytr_process_data` column
    // ]);
    dd($paymentUrl);

    // Send response to front-end with PayTR URL for payment processing
    return response()->json([
        'url' => $data['url'], // Frontend URL for payment
    ]);
}

public function showPaymentForm()
    {
        $merchant_id = env('PAYTR_MERCHANT_ID');
        $merchant_key = env('PAYTR_MERCHANT_KEY');
        $merchant_salt = env('PAYTR_MERCHANT_SALT');
        $email = 'customer@example.com';
        $payment_amount = 1000; // Amount in kuruş (e.g., 10.00 TL = 1000)
        $merchant_oid = uniqid(); // Unique order ID
        $user_name = 'John Doe';
        $user_address = '123 Main St';
        $user_phone = '5551234567';
        $user_basket = base64_encode(json_encode([
            ['Product 1', 500, 1],
            ['Product 2', 500, 1],
        ]));
        $no_installment = "0"; // No installment
        $max_installment = "0"; // Max installment
        $currency = 'TL'; // Currency
        $test_mode = "1"; // Set to 1 for test mode
        $user_ip = request()->ip(); // Customer's IP address
        $payment_type = 'card'; // Payment type
        $installment_count = "0"; // No installment
        $merchant_ok_url ="https://ayva.stage04.obdemo.com/paytr/callback-ptr";
        $merchant_fail_url ="https://ayva.stage04.obdemo.com/paytr/callback-ptr";
        $cc_owner ="asa dad";
        $non_3d="0";
        // Generate paytr_token
        dd($merchant_id,$merchant_key,$merchant_salt);
        $hash_str = $merchant_id . $user_ip . $merchant_oid . $email . $payment_amount . $payment_type . $installment_count. $currency. $test_mode. $non_3d;

       // $hash_str = $merchant_id . $user_name . $email . $merchant_oid . $payment_amount . $user_basket . $no_installment . $max_installment . $currency . $test_mode . $user_ip . $payment_type . $merchant_salt;
        $paytr_token = base64_encode(hash_hmac('sha256', $hash_str . $merchant_key, $merchant_salt, true));

        return view('payment-form', compact('merchant_id', 'merchant_oid', 'email', 'payment_amount', 'user_name','installment_count','merchant_ok_url','cc_owner','merchant_fail_url', 'user_address', 'user_phone', 'user_basket', 'no_installment', 'max_installment', 'currency', 'test_mode', 'user_ip', 'payment_type', 'paytr_token'));
    }


    public function processPayment(Request $request)
    {
        $merchant_id = '523380';
        $merchant_key = 'EkrPt6wsUqxrxddM';
        $merchant_salt = 'dq9PYiZWe95QPqP2';
        $merchant_ok_url = "https://ayva.stage04.obdemo.com/paytr/callback-success";
        $merchant_fail_url = "https://ayva.stage04.obdemo.com/paytr/callback-fail";
        $callback_url = "https://ayva.stage04.obdemo.com/paytr/callback-ptr";

        $payment_amount = 10099;
        $currency = $request->input('currency', 'TL');
        $payment_type = 'card'; // or 'bank'
        $user_ip = $request->ip();
        $merchant_oid = rand();
        $email ='as@yopmail.com';
        
        // User basket (shopping cart items)
        $user_basket = htmlentities(json_encode(array(
            array("Altis Renkli Deniz Yatağı - Mavi", "18.00", 1),
            array("Pharmasol Güneş Kremi 50+ Yetişkin & Bepanthol Cilt Bakım Kremi", "33,25", 2),
            array("Bestway Çocuklar İçin Plaj Seti Beach Set ÇANTADA DENİZ TOPU-BOT-KOLLUK", "45,42", 1)
        )));
        $test_mode="1";
       $installment_count ="0";
       $non_3d="0";
       $user_name ='asas';

        // Prepare the hash string
        $hash_str = $merchant_id . $user_ip . $merchant_oid . $email . $payment_amount . $payment_type . $installment_count. $currency. $test_mode. $non_3d;
        $token = base64_encode(hash_hmac('sha256', $hash_str . $merchant_salt, $merchant_key, true));
        dd($token);

        // Prepare the payment data
        $data = [
            'merchant_id' => $merchant_id,
            'user_ip' => $user_ip,
            'merchant_oid' => $merchant_oid,
            'email' => $email,
            'payment_amount' => $payment_amount,
            'payment_type' => $payment_type,
            'currency' => $currency,
            'merchant_ok_url' => $merchant_ok_url,
            'merchant_fail_url' => $merchant_fail_url,
            'callback_url' => $callback_url,
          //  'installment_count'=>$installment_count,
            // 'user_basket' => $user_basket,
            // 'paytr_token' => $token,
            // 'test_mode'=>$test_mode,
            // 'user_name'=>$user_name,
            // 'user_address'=>'as dsd',

           // 'non_3d'=>$non_3d,
        ];
        $client = new Client();
        $response = $client->post('https://www.paytr.com/odeme', [
            'form_params' => $data
        ]);
    
        // Parse the response from PayTR
        $responseData = json_decode($response->getBody()->getContents(), true);
        dd($responseData);
        return view('payment.form', compact('data'));
        // Send the data to PayTR via a POST request
        $response = Http::asForm()->post('https://www.paytr.com/odeme', $data);

        if ($response->successful()) {
            return response()->json([
                'success' => true,
                'redirect_url' => $response->body(),  // PayTR will return a redirect URL
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Payment initiation failed.',
            ]);
        }
    }

}
