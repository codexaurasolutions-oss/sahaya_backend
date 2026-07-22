<?php

namespace App\Http\Controllers\frontend;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Auth;
use App\Models\Order;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\ProductColor;
use App\Models\Category;
use App\Models\ReviewRating;
use App\Models\OrderProducts;
use App\Models\ProductVariant;
use App\Models\Notification;
use App\Models\OrderLog;
use App\Models\User;
use App\Models\ShippingAddressModel;
use App\Models\Transaction;
use App\Models\RefundOrder;
use App\Models\Lookup;
use App\Models\RefundOrderImage;
use App\Models\UserDeviceToken;
use App\Models\Cart;
use DB;
use App;
use Str;
use Validator;
use Carbon\Carbon;
use DateTime,DateTimeZone,Config;
use App\Traits\ImageUpload;


class OrderController extends Controller
{
    use ImageUpload;

        function generateOrderNumber()
    {
        $prefix = 'ODR';
        $lastOrder = DB::table('orders')->orderBy('id', 'desc')->first();
        if ($lastOrder && isset($lastOrder->order_number)) {
            $lastNumber = (int) preg_replace('/^' . $prefix . '/', '', $lastOrder->order_number);
        } else {
            $lastNumber = 0;
        }
        $newNumber = $lastNumber + 1;
        $paddedNumber = str_pad($newNumber, 4, '0', STR_PAD_LEFT);
        $newOrderNumber = $prefix . $paddedNumber;
        return $newOrderNumber;
    }      
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shipping_id' => 'nullable|numeric|min:0',
            'total'       => 'required|numeric|min:0',
            'coupon_code' => 'nullable|string',
            'method'      => 'nullable|string',
            'shipping'    => 'nullable|string',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'status' => 400,
                'errors' => $validator->errors()->all(),
            ], 400);
        }
        if ($request->coupon_code) {
            $couponCode = $request->coupon_code;
            $user_id = Auth::guard('api')->user()->id;
            $cartData = Cart::where('user_id', $user_id)->get();
            $productIds = $cartData->pluck('product_id');
            $allUserIds = Product::whereIn('id', $productIds)->pluck('user_id');
            $currentDate = now();
            $orderAmount = $request->total;        
            $coupon = Coupon::where('code', $couponCode)
            ->whereIn('user_id', $allUserIds)
            ->orderBy('id', 'desc')
            ->first();
            if (!$coupon) {
                return response()->json([
                    'message' => trans('messages.Invalid_coupon'),
                    'coupon'  => $couponCode,
                ], 200);
            }   
            if ($coupon->start_date > $currentDate) {
                return response()->json([
                    'status'  => true,
                    'message' => trans('messages.coupon_not_started'),
                ], 400);
            }
            if ($coupon->end_date < $currentDate) {
                return response()->json([
                    'status'  => true,
                    'message' => trans('messages.coupon_expired'),
                ], 400);
            }
            $maximumUse = $coupon->max_uses;
            $userUseCountCoupon = Order::where('coupon_code', $couponCode)
                ->where('user_id', Auth::guard('api')->user()->id)
                ->count();
            $couponUsageCount = Order::where('coupon_code', $couponCode)->count();        
            $priceDetails = [];        
            if ($userUseCountCoupon >= $coupon->per_person_use) {
                return response()->json([
                    'status'  => true,
                    'message' => trans('messages.coupon_max_per_user_reached'),
                ], 400); 
            }
            if ($couponUsageCount >= $maximumUse) {
                return response()->json([
                    'status'  => true,
                    'message' => trans('messages.coupon_max_uses_reached'),
                ], 400); 
            }
            if ($orderAmount < $coupon->min_amount) {
                return response()->json([
                    'status'  => true,
                    'message' => trans('messages.coupon_minimum_amount_not_met'),
                    'min'     => $coupon->min_amount
                ], 400);
            }

            
        }        
        $userDetail = Auth::guard('api')->user();
        DB::transaction(function () use ($request, $userDetail) {
            try {
                $newOrder                     = new Order;
                $newOrder->user_id            = $userDetail->id;
                $newOrder->order_number       = $this->generateOrderNumber();
                $newOrder->method             = $request->method ?? "Cash On Delivery";
                $newOrder->shipping_amount    = Config::get('shipping.shipping_amount') ?? "";
                $newOrder->payment_status     = "pending";
                $newOrder->status             = "pending";
                $newOrder->shipping_id        = $request->shipping_id;
                $newOrder->customer_name      = $userDetail->name ?? "";
                $newOrder->customer_email     = $userDetail->email ?? "";
                $newOrder->customer_phone     = $userDetail->phone_number ?? "";
                $newOrder->coupon_code        = $request->coupon_code ?? null;
                $newOrder->coupon_id          = $coupon->id ?? $request->coupon_id ?? null;
                $newOrder->price              = $request->total ?? null;
                $newOrder->discount_amount    = $request->discount_amount ?? null;
                $newOrder->pay_amount         = number_format($request->total - $request->discount_amount + Config::get('shipping.shipping_amount'), 2, '.', '') ?? null;
                $commission_percentage = Config::get('Commission.admin_commission_amount');
                $amount = $request->total;
                $commission_amount     = ($amount * $commission_percentage) / 100;
                $newOrder->admin_commission_amount = $commission_amount;
                    
                $newOrder->save();
                $order_data = $request->order_data ?? [];
                $allProductsId = [];
                $allVariantsId = [];
                foreach ($order_data as $value) {
                    $ids = $this->createOrderProduct($newOrder, $value, $request->coupon_code);
                    $allProductsId[] = $ids['product_id'];
                    $allVariantsId[] = $ids['variant_id'];
                }  
                $userDetailToken = UserDeviceToken::where('user_id', $userDetail->id)->latest()->first();

                //$userDetailToken = UserDeviceToken::where('user_id',$userDetail->id)->orderBy('id', 'DESC')->first();
                if($userDetail->language == 2){
                    $order_des ='Sipariş başarıyla verildi';
                    $msg_title = 'Yeni  Sipariş #' . $newOrder->order_number;
                }else{
                    $order_des ='Order has been placed successfully';
                    $msg_title = 'New Order #' . $newOrder->order_number;
                }
                $data=[
                'order_number'      => $newOrder->order_number, 
                ];
                if($userDetail->push_notification == 1){
                    if (!empty($userDetailToken->device_token)) {
                        $this->send_push_notification(
                            $userDetailToken->device_token,
                            $userDetailToken->device_type,
                            $order_des,
                            $msg_title,
                            'order_place',
                            $data
                        );
                    }
               }  
               $notification                  = new Notification;
               $notification->user_id         = $userDetail->id;
               $notification->action_user_id  = Auth::guard('api')->user()->id;
               $notification->order_number    = $newOrder->order_number ?? "";
               $notification->description_en  = 'Order has been placed successfully';
               $notification->title_en        = 'New Order #' . $newOrder->order_number;
               $notification->title_tur       = 'Yeni  Sipariş #' . $newOrder->order_number;
               $notification->description_tur = 'Sipariş başarıyla verildi';
               $notification->type            = "order_place";
               $notification->send_by         = 0;
               $notification->save();

               
                $this->logOrder($newOrder, $userDetail->id, $allProductsId, $allVariantsId);
                $this->transaction($newOrder, $userDetail->id);
                
               $allSellerIds = Product::whereIn('id', $allProductsId)->pluck('user_id')->unique();            
            $sellerProductMap = collect($allProductsId)->map(function ($productId, $index) use ($allVariantsId) {
                return [
                    'product_id' => $productId,
                    'variant_id' => $allVariantsId[$index] ?? null,
                ];
            })->groupBy(function ($item) use ($allSellerIds) {
                return Product::where('id', $item['product_id'])->value('user_id');
            });
            UserDeviceToken::whereIn('user_id', $allSellerIds)
            ->orderBy('id', 'desc')
                ->chunk(100, function ($userDeviceTokens) use ($sellerProductMap, $newOrder) {
                    foreach ($userDeviceTokens as $userDetailToken) {
                        $sellerId = $userDetailToken->user_id;
                        $productsForSeller = $sellerProductMap->get($sellerId);
                        if ($productsForSeller && $productsForSeller->count() === 1) {
                            $singleProduct = $productsForSeller->first();
                            $data = [
                                'order_number' => $newOrder->order_number,
                                'product_id'   => $singleProduct['product_id'],
                                'variant_id'   => $singleProduct['variant_id'],
                            ];
                        } else {
                            $data = [
                                'order_number' => $newOrder->order_number,
                            ];
                        }
                        $seller = User::find($sellerId);
                        if($seller->push_notification == 1){
                           if (!empty($userDetailToken->device_token)) {
                            if($seller->language == 2){
                               $seller_order_des = 'Yeni Sipariş Alındı';
                               $seller_msg_title = 'Yeni Sipariş #' . $data['order_number'];
                            }else{
                                $seller_order_des = 'New Order Received';
                                $seller_msg_title = 'New Order #' . $data['order_number'];

                            }
                               $this->send_push_notification(
                                   $userDetailToken->device_token,
                                   $userDetailToken->device_type,
                                   $seller_order_des,
                                   $seller_msg_title,
                                   'order_received',
                                   $data
                               );
                               $notification                  = new Notification;
                               $notification->user_id         = $seller->id;
                               $notification->action_user_id  = Auth::guard('api')->user()->id;
                               $notification->order_number    = $newOrder->order_number ?? "";
                               $notification->description_en  = 'Order has been recevied successfully';
                               $notification->title_en        = 'New Order #' . $newOrder->order_number;
                               $notification->title_tur       ='Yeni  Sipariş #' . $newOrder->order_number;
                               $notification->description_tur ='Sipariş başarıyla verildi';
                               $notification->type            = "order_received";
                               $notification->send_by         = 1;
                               $notification->save();
                           }
                        }
                    }
                });
     
            } catch (\Exception $e) {
                throw $e;
            }
        });
        return response()->json([
            'success' => true,
            'message' => trans('messages.order_created_successfully'),
        ], 200);
    }
    
    private function applyCoupon(Order $order, $couponCode)
    {
        if ($couponCode) {
            $coupon = Coupon::where('code', $couponCode)->first();
            if ($coupon && $coupon->min_amount <= $order->price) {
                $order->coupon_code_id = $coupon->id;
                $discountAmount = $this->calculateDiscount($order->price, $coupon);
                $order->coupon_discount = $discountAmount;
    
                if ($order->pay_amount - $discountAmount > Config::get('Commission.admin_commission_amount')) {
                    $commission_percentage  = Config::get('Commission.admin_commission_percentage');
                    $amount =  $order->pay_amount - $discountAmount;
                    $commission_amount = ($amount * $commission_percentage) / 100;
                    $order->admin_commission_amount = $commission_amount;
                }
    
                $order->pay_amount = $order->pay_amount - $discountAmount;
            }
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
    
    private function createOrderProduct(Order $order, $value, $couponCode)
    {


        $productOrder                     = new OrderProducts;
        $productOrder->order_number       = $order->order_number;
        $productOrder->user_id            = Auth::guard('api')->user()->id;
        $productSellerDetails             = Product::where('id',$value['product_id'])->first();
        $productOrder->seller_id          = $productSellerDetails->user_id;

        $productOrder->product_id         = $value['product_id'];
        $productOrder->product_varient_id = $value['variant_id'];
        $productOrder->coupon_discount = 0;
        $productOrder->coupon_code = null;
        if ($couponCode) {
            $coupon = Coupon::where('code', $couponCode)
                ->where('user_id', $productSellerDetails->user_id)
                ->orderBy('id', 'desc')
                ->first();
            if ($coupon) {
                $orderAmount = $value['price'] * $value['quantity'];
                if ($coupon->type == "discount_by_per") {
                    $discountAmount = ($orderAmount * $coupon->is_per) / 100;
                    if ($coupon->maximum_amount < $discountAmount) {
                        $discountAmount = $coupon->maximum_amount;
                    }
                } elseif ($coupon->type == "discount_by_amount") {
                    $discountAmount = $coupon->is_amount;
                } else {
                    $discountAmount = 0;
                }
                $finalAmount = $orderAmount - $discountAmount;
                $finalAmount = number_format($finalAmount, 2, '.', '');    
                $productOrder->coupon_discount = $discountAmount;
                $productOrder->coupon_code = $couponCode;
            }
        }
        $productOrder->varient_data       = json_encode([[
            'product_id' => $value['product_id'],
            'variant_id' => $value['variant_id'],
            'price'      => $value['price'],
            'quantity'   => $value['quantity'],
        ]]);
        
        $productOrder->qty   = $value['quantity'];
        $productOrder->price = $value['price'];
        $productOrder->save();
    
        return [
            'product_id' => $productOrder->product_id,
            'variant_id' => $productOrder->product_varient_id,
        ];
    }
    
    private function logOrder(Order $order, $userId, $allProductsId, $allVariantsId)
    {
        $orderLog = new OrderLog;
        $orderLog->user_id = $userId;
        $orderLog->order_id = $order->id;
        $orderLog->notes = $order->status;
        $orderLog->description = trans('messages.your_order_has_been_placed_successfully');
        $orderLog->order_number = $order->order_number ?? "";
        $orderLog->product_id = implode(',', $allProductsId) ?? "";
        $orderLog->product_variant_id = implode(',', $allVariantsId) ?? "";
        $orderLog->notes = "pending";
        $orderLog->save();
    }
    
    private function transaction(Order $order, $userId)
    {
        $newTransaction                 = new Transaction;
        $newTransaction->user_id        = $userId;
        $newTransaction->transaction_id = Str::random(8);
        $newTransaction->order_id       = $order->id;
        $newTransaction->order_number   = $order->order_number;
        $newTransaction->reference_id   = Str::random(6);
        $newTransaction->amount         = $order->pay_amount;
        $newTransaction->currency       = "INR";
        $newTransaction->payment_mode   = "CASH";
        $newTransaction->payment_status = "success";
        $transactionSave                = $newTransaction->save();
        if($transactionSave) {
                $cartData = Cart::where('user_id', Auth::guard('api')->id())->get();            
                $cartData->each(function ($cartItem) {
                    $cartItem->delete();
                });
            }; 
    }
    
    public function OrderList(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = 10; 
        $offset = ($page - 1) * $perPage; 
        $user = Auth::guard('api')->user();
        $query = Order::where('user_id', $user->id)->orderBy('created_at', 'desc');  
        if (!empty($request->start_date) && !empty($request->end_date)) {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();
        } elseif (!empty($request->start_date)) {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::now()->endOfDay();
        } elseif (!empty($request->end_date)) {
            $startDate = Carbon::now()->subDays(90)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();
        } else {
            $startDate = Carbon::now()->subDays(90)->startOfDay();
            $endDate = Carbon::now()->endOfDay();
        }
        $query->whereBetween('created_at', [$startDate, $endDate]);
        $orderlist = $query->get();               
        $orderlist = $query->skip($offset)->take($perPage)->get();  
        $countorderList = $orderlist->count();
        $userDetails = User::find($user->id);
        $order_list = $orderlist->map(function ($list) {
            $order_products = OrderProducts::where('order_number', $list->order_number)->orderBy('created_at', 'desc')->get();
            $order_details = [];
            $isNextOrderStatus = '';

            foreach ($order_products as $product) {

                if ($product->order_status === 'pending') {
                    $isNextOrderStatus = 'confirmed';
                } elseif ($product->order_status === 'confirmed') {
                    $isNextOrderStatus = 'packed';
                } elseif ($product->order_status === 'packed') {
                    $isNextOrderStatus = 'shipped';
                } elseif ($product->order_status === 'shipped') {
                    $isNextOrderStatus = 'delivered';
                } else{
                    $isNextOrderStatus = $product->order_status;  
                }               
                 $product_variant_details = ProductVariant::with([
                    'colorDetails',
                    'colorDetails.ColorsDescription',
                    'sizeDetails',
                    'sizeDetails.SizesDescription',
                    'product.parentCategoryDetails',
                    'product.subCategoryDetails'
                ])->find($product->product_varient_id);

                $product_details = Product::find($product->product_id);
                $avgRatingReview = ReviewRating::where('product_id', $product->product_id)->avg('rating');
                $avgRatingReview = $avgRatingReview == 0 ? '0' : number_format($avgRatingReview, 1);
                $ratingReviewArray = ReviewRating::where('product_id', $product->product_id)->get()->map(function($ratingReview) {  return array_merge($ratingReview->toArray(),['userName' => $ratingReview->user->name,'userImage' => $ratingReview->user->image,'created_at' => Carbon::parse($ratingReview->created_at)->diffForHumans(),]);});
                $ratingReviewCount = ReviewRating::where('product_id', $product->product_id)->count();
                $formattedRatingReviewCount = formatCount($ratingReviewCount);
                if (strpos($formattedRatingReviewCount, 'k') !== false) {
                    $numericCount = floatval(str_replace('k', '', $formattedRatingReviewCount));
                    if ($numericCount <= 1) {
                        $formattedRatingReviewCount = $formattedRatingReviewCount . ' Review';
                    } else {
                        $formattedRatingReviewCount = $formattedRatingReviewCount ;
                    }
                } else {
                    if ($formattedRatingReviewCount <= 1) {
                        $formattedRatingReviewCount = $formattedRatingReviewCount . ' Review';
                    } else {
                        $formattedRatingReviewCount = $formattedRatingReviewCount ;
                    }
                }
                if ($product_details && $product_variant_details) {
                    $productColorDetails = ProductColor::where('product_id', $product_variant_details->product_id)
                        ->where('color_id', $product_variant_details->colorDetails->id)
                        ->select('video', 'video_thumbnail')
                        ->first();
                    $order_details[] = [
                        'userDetails'         => User::find($list->user_id),
                        'sellerDetails'       => User::find($product->seller_id),
                        'order_number'        => $product->order_number,
                        'isNextOrderStatus'   => $isNextOrderStatus,
                        'product_name'        => $product_details->name,
                        'productQty'          => $product->qty,
                        'RatingReviewArray' => [
                            'AvgRatingReview' => $avgRatingReview,
                            'RatingReviewList' => $ratingReviewArray,
                            'formattedRatingReviewCount' => $formattedRatingReviewCount,
                        ],
                        'productOrderPrice'   => $product->price,
                        'product_variants'    => [
                            'product_id'           => $product_variant_details->product_id,
                            'product_varient_id'   => $product_variant_details->id,
                            // 'productVideo'         => "https://" . env("CDN_HOSTNAME") . "/" . $product_variant_details->video  . "/playlist.m3u8",
                            // 'productvideoThumbnail' => "https://" . env("CDN_HOSTNAME") . "/" . $product_variant_details->video . "/thumbnail.jpg",
                        ],
                        'product_size' => [
                            'sizeDetails'      => $product_variant_details->sizeDetails,
                            'SizesDescription' => $product_variant_details->sizeDetails->SizesDescription,
                        ],
                        'product_color' => [
                            'colorDetails'      => $product_variant_details->colorDetails,
                            'ColorsDescription' => $product_variant_details->colorDetails->ColorsDescription,
                        ],
                        'product_order_status' => $product->order_status,
                        'estimated_date'       => $product->estimated_date,
                        'category_details' => [
                            'parentCategory'   => $product_details->parentCategoryDetails,
                            'subCategory'      => $product_details->subCategoryDetails,
                        ],
                        
                        'productColorDetails' => [
                            'video' => $productColorDetails && $productColorDetails->video
                            ? "https://" . env("CDN_HOSTNAME") . "/" . $productColorDetails->video . "/playlist.m3u8"
                            : null,
                        'video_thumbnail' => $productColorDetails && $productColorDetails->video
                            ? "https://" . env("CDN_HOSTNAME") . "/" . $productColorDetails->video . "/thumbnail.jpg"
                            : null,
                        ],

                    ];
                }
            }
            return [
                'order_number'    => $list->order_number,
                'order_date'      => date_format($list->created_at,"d-m-Y"),
                'method'          => $list->method,
                'price'           => number_format($list->price, 2, '.', ''),
                'status'          => $list->status,
                'coupon_discount' => $list->coupon_discount,
                'pay_amount'      => number_format($list->pay_amount, 2, '.', ''),
                'order_details'   => $order_details,
                'userDetails'     => User::find($list->user_id),

            ];
        });
    
        return response()->json([
            'success'    => true,
            'message'    => 'Order List with Details.',
            'data'       => $order_list,
            'count'      => $countorderList,
            'page'       => $page,
            'date_range' => [
               'start_date' => $startDate,
               'end_date' => $endDate,
            ],
        ], 200);
    }



    public function OrderDetails(Request $request) {
        $validator = Validator::make($request->all(), [
            'order_number'       => 'required|string',
            'product_id'         => 'required|string',
            'product_varient_id' => 'required|string', 
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 400,
                'errors' => $validator->errors()->all(),
            ], 400);
        }
        
        $order_number = $request->order_number;
        $user_id = Auth::guard('api')->user()->id;    
        $order = Order::where('order_number', $order_number)->where('user_id', $user_id)->first();
        
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => trans('messages.order_not_found'),
            ], 404);
        }    
        
        $shippingDetails = ShippingAddressModel::find($order->shipping_id);
        $orderDetails = [];
        
        $order_product = OrderProducts::where('order_number', $order_number)
            ->where('product_id', $request->product_id)
            ->where('product_varient_id', $request->product_varient_id)
            ->first();
            $isNextOrderStatus = '';
            if ($order_product->order_status === 'pending') {
                $isNextOrderStatus = 'confirmed';
            } elseif ($order_product->order_status === 'confirmed') {
                $isNextOrderStatus = 'packed';
            } elseif ($order_product->order_status === 'packed') {
                $isNextOrderStatus = 'shipped';
            } elseif ($order_product->order_status === 'shipped') {
                $isNextOrderStatus = 'delivered';
            } else{
                $isNextOrderStatus = $order_product->order_status;  
            }
            $exchangeDays = config('Site.no_of_exchange_days');
            $lastExchangeDate = $order_product->created_at;
            $exchangeDeadline = $lastExchangeDate->copy()->addDays($exchangeDays);            
            if ($exchangeDeadline->isFuture()) {
                $is_exchange = 1;
            } else {
                $is_exchange = 0;
            }
        if ($order_product) {
            $productStatusDate = [
                'productStatusConfirmed' => $order_product->order_confirmed_at ? date("d M Y h:i A", strtotime($order_product->order_confirmed_at)) : null,
                'productStatusPacked'    => $order_product->on_the_way ? date("d M Y h:i A", strtotime($order_product->on_the_way)) : null,
                'productStatusShipping'  => $order_product->estimated_date ? date("d M Y h:i A", strtotime($order_product->estimated_date)) : null,
                'productStatusDelivered' => $order_product->order_completed_at ? date("d M Y h:i A", strtotime($order_product->order_completed_at)) : null,
            ];
            
            $product_varientDetails = ProductVariant::with(['colorDetails','colorDetails.ColorsDescription', 'sizeDetails','sizeDetails.SizesDescription','product.parentCategoryDetails', 'product.subCategoryDetails'])->find($order_product->product_varient_id);
            $productDetails = Product::find($order_product->product_id);
            if ($productDetails && $product_varientDetails) {
                $productColorDetails = ProductColor::where('product_id', $product_varientDetails->product_id)
                ->where('color_id', $product_varientDetails->colorDetails->id)
                ->select('video', 'video_thumbnail')
                ->first();
                $productColorDetails = [
                    'video' => $productColorDetails && $productColorDetails->video 
                        ? "https://" . env("CDN_HOSTNAME") . "/" . $productColorDetails->video . "/playlist.m3u8" 
                        : null,
                    'video_thumbnail' => $productColorDetails && $productColorDetails->video
                        ? "https://" . env("CDN_HOSTNAME") . "/" . $productColorDetails->video . "/thumbnail.jpg"
                        : null,
                ];
                $ratingReviewArray = ReviewRating::where('product_id', $request->product_id)->get()->map(function($ratingReview) {  return array_merge($ratingReview->toArray(),['userName' => $ratingReview->user->name,'userImage' => $ratingReview->user->image,'created_at' => Carbon::parse($ratingReview->created_at)->diffForHumans(),]);});
                $avgRatingReview = ReviewRating::where('product_id', $request->product_id)->avg('rating');
                $ratingReviewed = ReviewRating::where('product_id', $request->product_id)->where('product_varient_id',$request->product_varient_id)->where('user_id',Auth::guard('api')->user()->id)->first();
                $avgRatingReview = $avgRatingReview == 0 ? '0' : number_format($avgRatingReview, 1);
                $ratingReviewCount = ReviewRating::where('product_id', $request->product_id)->count();
                $formattedRatingReviewCount = formatCount($ratingReviewCount);
                if (strpos($formattedRatingReviewCount, 'k') !== false) {
                    $numericCount = floatval(str_replace('k', '', $formattedRatingReviewCount));
                    if ($numericCount <= 1) {
                        $formattedRatingReviewCount = $formattedRatingReviewCount . ' Review';
                    } else {
                        $formattedRatingReviewCount = $formattedRatingReviewCount ;
                    }
                } else {
                    if ($formattedRatingReviewCount <= 1) {
                        $formattedRatingReviewCount = $formattedRatingReviewCount . ' Review';
                    } else {
                        $formattedRatingReviewCount = $formattedRatingReviewCount ;
                    }
                }
                $orderDetails[] = [
                    'order_number'                  => $order_product->order_number,
                    'product_name'                  => $productDetails->name,
                    'RatingReviewArray' => [
                        'AvgRatingReview' => $avgRatingReview,
                        'RatingReviewList' => $ratingReviewArray,
                        'formattedRatingReviewCount' => $formattedRatingReviewCount,
                        'ratingReviewed' => $ratingReviewed,
                    ],
                    'isNextOrderStatus'             => $isNextOrderStatus,
                    'productQty'                    => $order_product->qty,
                    'productPrice'                  => $order_product->price,
                    'productRejectReason'           => $order_product->reject_reason,
                    'productRejectReasonSeller'     => $order_product->reject_reason_seller,
                    'productReturnReason'           => $order_product->return_reason,
                    'productRejectReasonUser'       => $order_product->reject_reason_user,
                    'productIsRejectUser'           => $order_product->is_reject_user,
                    'productIsSellerCancel'         => $order_product->is_seller_cancelled,
                    'productIsRefund'               => $order_product->is_refund,
                    'productIsExchangeRejectReason' => $order_product->exchange_reject_reason,
                    'product_size' => [
                        'sizeDetails'      => $product_varientDetails->sizeDetails,
                        'SizesDescription' => $product_varientDetails->sizeDetails->SizesDescription,
                    ],
                    'product_color' => [
                        'colorDetails'      => $product_varientDetails->colorDetails,
                        'ColorsDescription' => $product_varientDetails->colorDetails->ColorsDescription,
                    ],
                    'product_order_status' => $order_product->order_status,
                    'category_details' => [
                        'parentCategory' => $productDetails->parentCategoryDetails,
                        'subCategory'    => $productDetails->subCategoryDetails,
                    ],
                    'userDeatils' => User::find($user_id),
                         
                    'productColorDetails' => $productColorDetails,
                ];
            }
        }
        $order_summary = [
            'price'          => $order->price,
            'final_price'    => $order->pay_amount,
            'discount'       => (int)$order->discount_amount,//$order->coupon_discount,
            'shippingAmount' => (int)$order->shipping_amount,
            'all_item_count' => $order_product ? 1 : 0,
            'is_exchange'    => $is_exchange,
        ];
        
        return response()->json([
            'success'           => true,
            'message'           => 'Order List.',
            'data'              => $orderDetails,
            'productStatusDate' => $productStatusDate ?? [],
            'shipping_data'     => $shippingDetails,
            'order_summary'     => $order_summary,
        ], 200);
    }
    
    public function OrderCancel(Request $request)
    {    
        $validator = Validator::make($request->all(), [
            'order_number' => 'required',
            'product_id' => 'required',
            'product_varient_id' => 'required', 
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 400,
                'errors' => $validator->errors()->all(),
            ], 400);
        }
        $user = Auth::guard('api')->user();
        $order = Order::where('order_number', $request->order_number)->where('user_id', $user->id)->first();
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'order_not_found',
            ], 404);
        }
        if ($order->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => trans('messages.order_is_already_cancelled'),
            ], 400);
        }
            $order->status = 'cancelled';
            $order->save();
            $orderProducts = OrderProducts::where('order_number', $order->order_number)->where('product_id', $request->product_id)->where('product_varient_id', $request->product_varient_id)->get();
            foreach ($orderProducts as $orderProduct) {
                $orderProduct->order_status = 'cancelled';
                $orderProduct->save();
            }
        return response()->json([
            'success' => true,
            'message' => trans('messages.order_has_been_cancelled_successfully'),
        ], 200);
    }

    public function SingleOrderCancel(Request $request)
    {  
        $validator = Validator::make($request->all(), [
            'order_number' => 'required',
            'product_id' => 'required',
            'product_varient_id' => 'required',
            'reject_reason' => 'required', 
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 400,
                'errors' => $validator->errors()->all(),
            ], 400);
        }
        $user               = Auth::guard('api')->user();
        $order              = Order::where('order_number', $request->order_number)->where('user_id', $user->id)->first();
        $countOrderProducts = OrderProducts::where('order_number', $request->order_number)->count();
        if (!$order) {
            return response()->json([
                'status' => 404,
                'message' => 'Order not found',
            ], 404);
        }
        $orderProduct = OrderProducts::where('order_number', $request->order_number)->where('product_id', $request->product_id)->where('product_varient_id', $request->product_varient_id)->first();
        if ($orderProduct) {
            if ($orderProduct->order_status === 'cancelled') {
                return response()->json(['message' => trans('messages.order_is_already_cancelled')], 400);
            }
            $orderProduct->order_status       = 'cancelled';
            $orderProduct->reject_reason_user = $request->reject_reason;
            $orderProduct->is_reject_user = 1;
            $updated =  $orderProduct->save();
            if ($updated) {
                if($countOrderProducts == 1){
                    $orderToUpdate = Order::where('order_number', $order->order_number)->first();
                    if ($orderToUpdate) {
                        $orderToUpdate->status = 'cancelled';
                        $orderToUpdate->is_reject_user = 1;
                        $orderToUpdate->save();
                    }                    
                }
                $newOrderlog                        = new OrderLog;
                $newOrderlog->user_id               = Auth::guard('api')->user()->id;
                $newOrderlog->order_id              = $order->id;
                $newOrderlog->order_number          = $order->order_number;
                $newOrderlog->notes                 = $orderToUpdate->status ?? $orderProduct->order_status ?? "";
                $newOrderlog->description           = trans('messages.order_cancel_by_user') ?? "cancel";
                $newOrderlog->product_id            = $orderProduct->product_id;
                $newOrderlog->product_variant_id    = $orderProduct->product_varient_id;
                $newOrderlog->save();
                return response()->json(['message' => trans('messages.order_has_been_cancelled_successfully')]);
            } else {
                return response()->json(['message' => trans('messages.Order_cancellation_failed')], 400);
            }
        }
    }
    public function SingleOrderReject(Request $request) {
        return $this->rejectOrder($request, 'single');
    }
    public function OrderReject(Request $request) {
        return $this->rejectOrder($request, 'multiple');
    }
    private function rejectOrder(Request $request, $type) {
        $validator = Validator::make($request->all(), [
            'reject_reason_user' => 'required|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 400,
                'errors' => $validator->errors()->all(),
            ], 400);
        }
        if ($type === 'single') {
            $order_products_id   = base64_decode($request->order_products_id);
            $orderItem           = OrderProducts::find($order_products_id);
            if (!$orderItem) {
                return response()->json(['message' => trans('messages.order_not_found')], 404);
            }
            $order_number = $orderItem->order_number;
            $allproductcount = OrderProducts::where('order_number', $order_number)->count();
            $orderItem->order_status = "cancelled";
            $orderItem->reject_reason_user = $request->reject_reason_user;
            $orderItem->is_reject_user = 1;
            $saveResult = $orderItem->save();            
            if ($allproductcount === 1) {
                $order = Order::where('order_number',$order_number)->first();
                if ($order) {
                    $order->status = 'cancelled';
                    $order->reject_reason_user = $request->reject_reason_user;
                    $order->is_reject_user = 1;
                    $order->save();
                }                
            } 
        } else {
            $order_number = $request->order_number;
            $order = Order::where('order_number', $order_number)->where('user_id', Auth::guard('api')->user()->id)->first();
            if (!$order) {
                return response()->json(['message' => trans('messages.order_not_found')], 404);
            }
            $order->status = "cancelled";
            $order->reject_reason_user = $request->reject_reason_user;
            $order->is_reject_user = 1;
            $saveResult = $order->save();
            $orderProducts = OrderProducts::where('order_number', $order->order_number)->get();
            foreach ($orderProducts as $product) {
                $product->order_status = 'cancelled';
                $product->reject_reason_user = $request->reject_reason_user;
                $product->is_reject_user = 1;
                $product->save();
            }            
        }
        if ($saveResult) {
            $newOrderlog                        = new OrderLog;
            $newOrderlog->user_id               = Auth::guard('api')->user()->id;
            $newOrderlog->order_id              = $order->id ?? $orderItem->id;
            $newOrderlog->order_number          = $order->order_number ?? $orderItem->order_number;
            $newOrderlog->product_id            = $orderItem->product_id ?? "";
            $newOrderlog->product_variant_id    = $orderItem->product_varient_id ?? "";
            $newOrderlog->notes                 = $orderItem->order_status ?? $order->status;
            $newOrderlog->description           = trans('messages.order_cancel_by_user');
            $newOrderlog->save();
            return response()->json(['status' => true,'message' => trans('messages.order_has_been_cancelled_successfully')]);
        } else {
            return response()->json(['status' => true,'message' => trans('messages.Order_cancellation_failed')], 400);
        }
    }

    public function OrderRefundAll(Request $request){
        $order_number            = $request->order_number;
        $order                   = Order::where('user_id', Auth::guard('api')->user()->id)->where('order_number',$order_number)->first();
        $order->is_refund_all    = 1;
        $saveResult              = $order->save();
        if ($saveResult) {
            $products = OrderProducts::where('order_number', $order->order_number)->get();
            foreach ($products as $product) {
                $product->is_refund = 1;
                $product->save();
            }
            $newOrderlog                        = new OrderLog;
            $newOrderlog->user_id               = Auth::guard('api')->user()->id;
            $newOrderlog->order_id              = $order->id;
            $newOrderlog->order_number          = $order->order_number;
            $newOrderlog->product_id            = $orderItem->product_id ?? "";
            $newOrderlog->product_variant_id    = $orderItem->product_varient_id ?? "";
            $newOrderlog->description           = trans('messages.refund_request_for_all_products');
            $newOrderlog->save();
            return response()->json(['status' => true,'message' => trans('messages.order_has_been_refund_successfully')]);
        } else {
            return response()->json(['status' => true,'message' => trans('messages.order_refund_failed')], 400);
        }
    }
    public function OrderRefundSingle(Request $request){
        $validator = Validator::make($request->all(), [
            'order_number'       => 'required',
            'product_id'         => 'required',
            'product_varient_id' => 'required',
            'reason' => 'required',
            'description' => 'required_if:reason,other',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 400,
                'errors' => $validator->errors()->all(),
            ], 400);
        }
        $order_number                 = $request->order_number;
        $product_id                      = $request->product_id;
        $product_varient_id           = $request->product_varient_id;
        $SingleOrder                  = OrderProducts::where('order_number',$order_number)->where('product_id',$product_id)->where('product_varient_id',$product_varient_id)->first();

        if (!$SingleOrder) {
            return response()->json(['status' => true,'message' => trans('messages.order_not_found')], 404);
        }
    
        // if ($SingleOrder->is_refund == 1) {
        //     return response()->json(['message' => trans('messages.product_already_refunded')], 400);
        // }
        $orderCount                   = OrderProducts::where('order_number',$order_number)->count();
        $SingleOrder->is_refund       = 1;
        $SingleOrder->order_status       = "refund";
        $saveResult                   = $SingleOrder->save();
        if($saveResult){
            $refundOrder                     = new RefundOrder;
            $refundOrder->order_number       = $request->order_number;
            $refundOrder->product_id         = $request->product_id;
            $refundOrder->product_varient_id = $request->product_varient_id;
            $refundOrder->reason             = $request->reason;
            $refundOrder->description        = $request->description;
             $refundOrder->save();  
            foreach ($request->images as $image) {
                $refundImages = new RefundOrderImage;
                $refundImages->refund_order_id = $refundOrder->id;                
                $imagePath = $this->maltipalUploadFiles($image, config('constants.REFUND_IMAGE_ROOT_PATH'));
            
                if ($imagePath) {
                    $refundImages->image = $imagePath;
                    $refundImages->save();
                }
            }
            
            
        }
        if($orderCount == 1){
            $order = Order::where('order_number', $order_number)->first();
            if ($order) {
                $order->is_refund_all = 1;
                $order->save();
            }
        };
        if ($saveResult) {
            $newOrderlog                        = new OrderLog;
            $newOrderlog->user_id               = Auth::guard('api')->user()->id;
            $newOrderlog->order_number          = $order_number;
            $newOrderlog->product_id            = $SingleOrder->product_id ?? "";
            $newOrderlog->product_variant_id    = $SingleOrder->product_varient_id ?? "";
            $newOrderlog->description           = trans('messages.refund_request_for_single_product');
            $newOrderlog->notes                 = "Refund";
            $newOrderlog->save();
            return response()->json(['status' => true,'message' => trans('messages.order_has_been_refund_successfully')]);
        } else {
            return response()->json(['status' => true,'message' => trans('messages.order_refund_failed')],  400);
        }
    }
    public function varient_change(Request $request) {
        $product_id = $request->product_id;
        $product_varient_id = $request->product_varient_id;
        $parent_category = $request->parent_category;
        $category_level_2 = $request->category_level_2;
        $color_id = $request->color_id;
        $size_id = $request->size_id;    
        $query = Product::query();
        if ($product_id) {
            $query->Where('id', $product_id);
        }
        $products = $query->with(['userDetails','parentCategoryDetails','subCategoryDetails'])->get();
        $variantQuery = ProductVariant::query();
        if ($product_varient_id) {
            $variantQuery->Where('id', $product_varient_id);
        }
        if ($color_id) {
            $variantQuery->Where('color_id', $color_id);
        }
        if ($size_id) {
            $variantQuery->Where('size_id', $size_id);
        }
        if ($products->isNotEmpty()) {
            $productIds = $products->pluck('id');
            $variantQuery->WhereIn('product_id', $productIds);
        }
        $variants = $variantQuery->with(['colorDetails','colorDetails.ColorsDescription', 'sizeDetails','sizeDetails.SizesDescription','product.parentCategoryDetails', 'product.subCategoryDetails'])->orderBy('id', 'desc')->get();
        return response()->json([
            'status' => true,
            'message' => trans('messages.order_has_been_fatch_successfully'),
            'data' => $variants,
        ]);
    }
    

    public function received_order(Request $request) {
        $page = $request->input('page', 1);
        $perPage = 10; 
        $offset = ($page - 1) * $perPage; 
        if (!empty($request->start_date) && !empty($request->end_date)) {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();
        } elseif (!empty($request->start_date)) {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::now()->endOfDay();
        } elseif (!empty($request->end_date)) {
            $startDate = Carbon::now()->subDays(90)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();
        } else {
            $startDate = Carbon::now()->subDays(90)->startOfDay();
            $endDate = Carbon::now()->endOfDay();
        }
        
        $all_received_orders = OrderProducts::with('ProductDetails', 'ProductVarientDetails')
            ->where('seller_id', Auth::guard('api')->user()->id)
            ->whereBetween('created_at', [$startDate, $endDate])->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($perPage) 
            ->get();     
             $orders_with_details = [];
              $totalAmount = 0;
              $isNextOrderStatus = '';
            foreach ($all_received_orders as $order) {
                if ($order->order_status === 'pending') {
                    $isNextOrderStatus = 'confirmed';
                } elseif ($order->order_status === 'confirmed') {
                    $isNextOrderStatus = 'packed';
                } elseif ($order->order_status === 'packed') {
                    $isNextOrderStatus = 'shipped';
                } elseif ($order->order_status === 'shipped') {
                    $isNextOrderStatus = 'delivered';
                } else{
                    $isNextOrderStatus = $order->order_status;  
                }
                $totalAmount += $order->price;$variant_color_details = ProductColor::with(['colorDetails', 'colorDetails.ColorsDescription'])
                ->where('product_id', $order->ProductVarientDetails->product_id)
                ->where('color_id', $order->ProductVarientDetails->color_id)
                ->first();
                $productVideo = $variant_color_details->video ? "https://" . env("CDN_HOSTNAME") . "/" . $variant_color_details->video  . "/playlist.m3u8" : null;
                $productThumbnail = $variant_color_details->video ? "https://" . env("CDN_HOSTNAME") . "/" . $variant_color_details->video  . "/thumbnail.jpg" : null;
            
                    $videoBaseUrl = "https://" . env("CDN_HOSTNAME");
                    if ($variant_color_details->video) {
                        $videoPath = $variant_color_details->video;
                        $variant_color_details->video = $videoBaseUrl . "/" . $videoPath . "/playlist.m3u8";
                        $variant_color_details->video_thumbnail = $videoBaseUrl . "/" . $videoPath . "/thumbnail.jpg";
                    } else {
                        $variant_color_details->video = null;
                        $variant_color_details->video_thumbnail = null;
                    }
                $variant_size_details = ProductSize::with(['sizeDetails', 'sizeDetails.SizesDescription'])
                    ->where('product_id', $order->ProductVarientDetails->product_id)
                    ->where('size_id', $order->ProductVarientDetails->size_id)
                    ->first();
                    $ratingReviewArray = ReviewRating::where('product_id', $order->product_id)->get()->map(function($ratingReview) {  return array_merge($ratingReview->toArray(),['userName' => $ratingReview->user->name,'userImage' => $ratingReview->user->image,'created_at' => Carbon::parse($ratingReview->created_at)->diffForHumans(),]);});
                    $avgRatingReview = ReviewRating::where('product_id', $order->product_id)->avg('rating');
                    $avgRatingReview = $avgRatingReview == 0 ? '0' : number_format($avgRatingReview, 1);
                    $ratingReviewCount = ReviewRating::where('product_id', $order->product_id)->count();
                    $formattedRatingReviewCount = formatCount($ratingReviewCount);
                    if (strpos($formattedRatingReviewCount, 'k') !== false) {
                        $numericCount = floatval(str_replace('k', '', $formattedRatingReviewCount));
                        if ($numericCount <= 1) {
                            $formattedRatingReviewCount = $formattedRatingReviewCount . ' Review';
                        } else {
                            $formattedRatingReviewCount = $formattedRatingReviewCount ;
                        }
                    } else {
                        if ($formattedRatingReviewCount <= 1) {
                            $formattedRatingReviewCount = $formattedRatingReviewCount . ' Review';
                        } else {
                            $formattedRatingReviewCount = $formattedRatingReviewCount ;
                        }
                    }
                $product = Product::find($order->ProductVarientDetails->product_id);
                $userDetails = User::find($order->user_id);
                $product_name = $product->name ?? null;
                $product_image = Category::find($product->category_level_2)?->value('image') ?? Category::find($product->parent_category)?->value('image');
                $productAmount    = $order->price;
                $productOrderStatus = $order->order_status	;
                $productQty         = $order->qty;
                $productId        = $order->product_id;
                $productVarientId         = $order->product_varient_id;
                $categoryDetails = Category::find($product->parent_category);
                $subCategoryDetails = Category::find($product->category_level_2);
                $order_number = $order->order_number;            
                if (!isset($orders_with_details[$order_number])) {
                    $orders_with_details[$order_number] = [
                        'order_number' => $order_number,
                        'order_details' => [],
                        'userDetails' => $userDetails,
                        'totalAmount' => 0,
                        
                    ];
                }
                $orders_with_details[$order_number]['order_details'][] = [
                    'order_number' => $order_number,
                    'isNextOrderStatus'=> $isNextOrderStatus,
                    'product_name' => $product_name,
                    'product_image' => $product_image,
                    'productVideo' => $productVideo,
                    'productThumbnail' =>$productThumbnail,
                    'productOrderStatus' => $productOrderStatus,
                    'productQty' => $productQty,
                    'productAmount' => $productAmount,
                    'productId' => $productId,
                    'avgRatingReview' => $avgRatingReview,
                    'RatingReviewArray' => [
                        'AvgRatingReview' => $avgRatingReview,
                        'RatingReviewList' => $ratingReviewArray,
                        'formattedRatingReviewCount' => $formattedRatingReviewCount,
                    ],
                     'productVarientId' => $productVarientId,
                    'userDetails' => $userDetails,
                    'productSubCategory' => $subCategoryDetails,
                    'productCategory' => $categoryDetails,
                    'variant_color' => $variant_color_details,
                    'variant_size' => $variant_size_details,
                    
                ];
                $orders_with_details[$order_number]['totalAmount'] += $order->price;
            }
            $orders_with_details = array_values($orders_with_details);
        return response()->json([
            'status' => true,
            'message' => trans('messages.order_list_has_been_fatch_successfully'),
            'data' => $orders_with_details,
            'orderCount' => $all_received_orders->count(),
            'totalAmount' => $totalAmount,
            'page' => $page,
            'date_range' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
             ],
        ]);
        
    }

    public function returnreasons(){
        $reasons = Lookup::where('lookup_type', 'return reason')->get();
      return response()->json([
        'status' => true,
        'message' => trans('messages.reasons_has_been_fatch_successfully'),
        'data' => $reasons,
    ]);
    }
    public function receivedOrderDetails(Request $request) {
        $validator = Validator::make($request->all(), [
            'order_number'       => 'required|string',
            'product_id'         => 'required|string',
            'product_varient_id' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 400,
                'errors' => $validator->errors()->all(),
            ], 400);
        
        }
        $orderNumber = $request->order_number;
        $orderDetails = OrderProducts::with(['ProductDetails', 'ProductVarientDetails'])
            ->where('order_number', $orderNumber)
            ->where('product_id', $request->product_id)
            ->where('product_varient_id', $request->product_varient_id)
            ->where('seller_id', Auth::guard('api')->user()->id)
            ->first();
        if (!$orderDetails) {
            return response()->json([
                'status' => true,
                'message' => trans('messages.no_order_found'),
                'data'    => []
            ]);
        }
        $isNextOrderStatus = '';
        if ($orderDetails->order_status === 'pending') {
            $isNextOrderStatus = 'confirmed';
        } elseif ($orderDetails->order_status === 'confirmed') {
            $isNextOrderStatus = 'packed';
        } elseif ($orderDetails->order_status === 'packed') {
            $isNextOrderStatus = 'shipped';
        } elseif ($orderDetails->order_status === 'shipped') {
            $isNextOrderStatus = 'delivered';
        } else{
            $isNextOrderStatus = $orderDetails->order_status;  
        }
            $ratingReviewArray = ReviewRating::where('product_id', $request->product_id)->get()->map(function($ratingReview) {  return array_merge($ratingReview->toArray(),['userName' => $ratingReview->user->name,'userImage' => $ratingReview->user->image,'created_at' => Carbon::parse($ratingReview->created_at)->diffForHumans(),]);});
            $avgRatingReview = ReviewRating::where('product_id', $request->product_id)->avg('rating');
            
            $ratingReviewed = ReviewRating::where('product_id', $request->product_id)->where('product_varient_id',$request->product_varient_id)->where('user_id',Auth::guard('api')->user()->id)->first();
            $avgRatingReview = $avgRatingReview == 0 ? '0' : number_format($avgRatingReview, 1);
            $ratingReviewCount = ReviewRating::where('product_id', $request->product_id)->count();
            $formattedRatingReviewCount = formatCount($ratingReviewCount);
            if (strpos($formattedRatingReviewCount, 'k') !== false) {
                $numericCount = floatval(str_replace('k', '', $formattedRatingReviewCount));
                if ($numericCount <= 1) {
                    $formattedRatingReviewCount = $formattedRatingReviewCount . ' Review';
                } else {
                    $formattedRatingReviewCount = $formattedRatingReviewCount ;
                }
            } else {
                if ($formattedRatingReviewCount <= 1) {
                    $formattedRatingReviewCount = $formattedRatingReviewCount . ' Review';
                } else {
                    $formattedRatingReviewCount = $formattedRatingReviewCount ;
                }
            }
        $exchangeDays = config('Site.no_of_exchange_days');
            $lastExchangeDate = $orderDetails->created_at;
            $exchangeDeadline = $lastExchangeDate->copy()->addDays($exchangeDays);            
            if ($exchangeDeadline->isFuture()) {
                $is_exchange = 1;
            } else {
                $is_exchange = 0;
            }
        $shipping_id = Order::where('order_number', $orderNumber)->value('shipping_id');    
        $shippingDetails = ShippingAddressModel::find($shipping_id);  
        $order = Order::where('order_number', $orderNumber)->first();
        $priceSummary = [
            'itemCount' => OrderProducts::where('order_number', $orderNumber)->count(),
            'orderPayAmount' => $order->pay_amount,
            'orderPrice' => $order->price,
            'orderShippingAmount' => $order->shipping_amount,
            'orderDiscountAmount' => $order->discount_amount,
        ];
        $variantColorDetails = ProductColor::with(['colorDetails', 'colorDetails.ColorsDescription'])
         ->where('product_id', $orderDetails->ProductVarientDetails->product_id)
         ->where('color_id', $orderDetails->ProductVarientDetails->color_id)
         ->first();
         $videoBaseUrl = "https://" . env("CDN_HOSTNAME");
         if ($variantColorDetails->video) {
             $videoPath = $variantColorDetails->video;
             $variantColorDetails->video = $videoBaseUrl . "/" . $videoPath . "/playlist.m3u8";
             $variantColorDetails->video_thumbnail = $videoBaseUrl . "/" . $videoPath . "/thumbnail.jpg";
         } else {
             $variantColorDetails->video = null;
             $variantColorDetails->video_thumbnail = null;
         }         
        $variantSizeDetails = ProductSize::with(['sizeDetails', 'sizeDetails.SizesDescription'])
            ->where('product_id', $orderDetails->ProductVarientDetails->product_id)
            ->where('size_id', $orderDetails->ProductVarientDetails->size_id)
            ->first();
        
        $product = Product::find($orderDetails->ProductVarientDetails->product_id);
        $userDetails = User::find($orderDetails->user_id);
        $productName = $product->name ?? null;
        $productImage = Category::find($product->category_level_2)?->value('image') ?? Category::find($product->parent_category)?->value('image') ?? null;
        // $productVideo = "https://" . env("CDN_HOSTNAME") . "/" . $variantColorDetails->video  . "/playlist.m3u8";
        // $productThumbnail = "https://" . env("CDN_HOSTNAME") . "/" . $variantColorDetails->video . "/thumbnail.jpg";
        $categoryDetails = Category::find($product->parent_category);
        $subCategoryDetails = Category::find($product->category_level_2);
        $productStatusDate = [
            'productStatusConfirmed' => $orderDetails->order_confirmed_at ? date("d M Y h:i A", strtotime($orderDetails->order_confirmed_at)) : null,
            'productStatusPacked'    => $orderDetails->on_the_way ? date("d M Y h:i A", strtotime($orderDetails->on_the_way)) : null,
            'productStatusShipping'  => $orderDetails->estimated_date ? date("d M Y h:i A", strtotime($orderDetails->estimated_date)) : null,
            'productStatusDelivered' => $orderDetails->order_completed_at ? date("d M Y h:i A", strtotime($orderDetails->order_completed_at)) : null,
        ];   

        $ordersWithDetails = [
            'order'              => $orderDetails,
            'productSubCategory' => $subCategoryDetails,
            'productCategory'    => $categoryDetails,
            'isNextOrderStatus'  => $isNextOrderStatus,
            'variant_color'      => $variantColorDetails,
            'variant_size'       => $variantSizeDetails,
            'product_name'       => $productName,
            'product_image'      => $productImage,
            'userDetails'        => $userDetails,
            'is_exchange'        => $is_exchange,
            'RatingReviewArray' => [
                'AvgRatingReview' => $avgRatingReview,
                'RatingReviewList' => $ratingReviewArray,
                'formattedRatingReviewCount' => $formattedRatingReviewCount,
                'ratingReviewed' => $ratingReviewed,
            ],
        ];
        return response()->json([
            'status' => true,
            'message'         => trans('messages.order_list_has_been_fatch_successfully'),
            'data'            => [$ordersWithDetails],
            'shippingDetails' => $shippingDetails,
            'productStatusDate' => $productStatusDate,
            'price'           => $orderDetails->price,
            'priceSummary'    => $priceSummary,
        ]);
    }
    public function receivedOrderCancel(Request $request) {
        $validator = Validator::make($request->all(), [
            'order_number'         => 'required|string',
            'product_id'           => 'required|string',
            'product_varient_id'   => 'required|string',
         //   'reject_reason'   => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 400,
                'errors' => $validator->errors()->all(),
            ], 400);
        }
        $order_number         = $request->order_number;
        $product_id       = $request->product_id;
        $product_varient_id = $request->product_varient_id;
        $productDetails         = Product::find($product_id);
        if($productDetails->user_id == Auth::guard('api')->user()->id){
            $orderProduct     = OrderProducts::where('order_number', $order_number)->where('seller_id', Auth::guard('api')->user()->id)->where('product_id',$product_id)->where('product_varient_id',$product_varient_id)->first();
          if ($orderProduct) {
              if ($orderProduct->order_status == 'cancelled') {
                  return response()->json([
                    'status' => true,
                      'message' => trans('messages.order_already_cancelled_by_seller')
                  ], 400);
              }
              $orderProduct->is_seller_cancelled = 1;
              $orderProduct->reject_reason_seller = $request->reject_reason;
              $orderProduct->order_status        = 'cancelled';
              $orderProduct->save();
              $allproductcount = OrderProducts::where('order_number', $order_number)->count();
            if ($allproductcount === 1) {
                $order = Order::where('order_number',$order_number)->first();
                if ($order) {
                    $order->status = 'cancelled';
                    $order->reject_reason_user = $request->reject_reason_user;
                    $order->is_reject_seller = 1;
                    $order->save();
                }                
            } 
              return response()->json([
                'status' => true,
                  'message' => trans('messages.order_cancel_by_seller')
              ]);
          } else {
              return response()->json([
                'status' => true,
                  'message' => trans('messages.order_not_found')
              ], 404);
          }
      }else{
          return response()->json([
            'status' => true,
              'message' => trans('messages.order_not_found')
          ], 404);
      }
    }

    public function productStatusChangeVendor(Request $request)
    {
        
        $validator = Validator::make($request->all(), [
            'order_number'         => 'required|string',
            'product_id'           => 'required|string',
            'product_varient_id'   => 'required|string',
            'nextstatus'           => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 400,
                'errors' => $validator->errors()->all(),
            ], 400); 
        }
        $orderNumber      = $request->order_number;
        $productId        = $request->product_id;
        $variantProductId = $request->product_varient_id;
        $nextStatus       = $request->nextstatus;
        $productDetails = OrderProducts::where('order_number', $orderNumber)
        ->where('product_id', $productId)
        ->where('product_varient_id', $variantProductId)
        ->first();
        $allProductsId = [];
        $allVariantsId = [];
        $order_data    = OrderProducts::where('order_number', $orderNumber)->get();
        foreach ($order_data as $value) {
            $allProductsId[] = $value['product_id'];
            $allVariantsId[] = $value['variant_id'];
        }  
        if (!$productDetails) {
            return response()->json([
                'status'  => 404,
                'message' => trans('messages.product_details_not_found_for_the_given_criteria'),
            ], 404);
        }
        if ($productDetails->order_status == $nextStatus) {
            return response()->json([
                'status'  => 200,
                'success' => 'success',
                'message' => trans('messages.order_status_already_set') . ' ' . ucfirst($nextStatus),
            ], 200);
        }
        $productDetails->order_status = $nextStatus;
        if($nextStatus == "shipped"){
            $productDetails->estimated_date  = now();
        }
        if($nextStatus == "packed"){
            $productDetails->on_the_way  = now();
        }
        if($nextStatus == "confirmed"){
            $productDetails->order_confirmed_at  = now();
        }
        if($nextStatus == "delivered"){
            $productDetails->order_completed_at  = now();
        }
        $productDetails->save();
        $userDetail  = User::find($productDetails->user_id);
        $userDetailToken =UserDeviceToken::where('user_id',$userDetail->id)->orderBy('id', 'desc')->first();
        if ($userDetail->language == 2) {
            $order_des = 'Siparişinizin durumu "' . $nextStatus . '" olarak güncellenmiştir.';
            $msg_title =  'Sipariş Durumu: ' . $nextStatus;
        } else {
            $order_des = 'The status of your order has been updated to "' . $nextStatus . '".';
            $msg_title = 'Order Status: ' . ucfirst($nextStatus);
        }       
        $data=[
            'order_number' => $orderNumber, 
            'product_id' => $productId,
            'product_varient_id'=> $variantProductId,
        ];
        if (!empty($userDetailToken->device_token)) {
            $this->send_push_notification(
                $userDetailToken->device_token,
                $userDetailToken->device_type,
                $order_des,
                $msg_title,
                $nextStatus,
                $data
            );
            $notification = new Notification;
            $notification->user_id   = $userDetail->id;
            $notification->action_user_id = Auth::guard('api')->user()->id;
            $notification->order_number = $orderNumber ?? "";
            $notification->product_id  = $productId ?? "";
            $notification->product_varient_id = $variantProductId ?? "";
            $notification->description_en = 'The status of your order has been updated to "' . $nextStatus . '".';
              $notification->title_en  = 'Order Status: ' . ucfirst($nextStatus);
              $notification->title_tur = 'Sipariş Durumu: ' . $nextStatus;
              $notification->description_tur = 'Siparişinizin durumu "' . $nextStatus . '" olarak güncellenmiştir.';
            $notification->type           = "vendor_status_change";
            $notification->save();
        }
       
        return response()->json([
            'status'  => 200,
            'success' => 'success',
            'message' => trans('messages.product_status_updated_successfully'),
            'data'    => [
                'order_number'         => $orderNumber,
                'product_id'           => $productId,
                'product_varient_id'   => $variantProductId,
                'updated_status'       => $nextStatus,
            ],
        ], 200);
    }
    

    
}