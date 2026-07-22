<?php

namespace App\Http\Controllers\frontend;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use Auth;
use App\Models\Order;
use App\Models\User;
use App\Models\Product;
use App\Models\Coupon;
use App\Models\ProductVariant;
use DB;
use Illuminate\Validation\Rule;
use App;
use Validator,Config;


class CouponsController extends Controller
{
    public function CouponsList(Request $request) {
        $user_id = Auth::guard('api')->user()->id;
        $price_cart = $request->total;   
        $cartData   = Cart::where('user_id',$user_id)->get();
        $productIds = $cartData->pluck('product_id');
        $allUserIds   = Product::whereIn('id',$productIds)->pluck('user_id');
        $all_used_coupons = Order::pluck('coupon_code');
        $coupon_counts = $all_used_coupons->countBy();        
        $coupon_counts_array = $coupon_counts->toArray();
        $coupons_list = Coupon::where('status', 1)
        ->where('is_deleted', 0)
        ->where(function ($query) use ($user_id, $allUserIds) {
            $query->whereIn('user_id', $allUserIds)
                  ->where('user_id', '!=', $user_id);
        })
        ->orderBy('id', 'desc')
        ->get();
        $coupons_response = $coupons_list->map(function($coupon) use ($coupon_counts_array, $price_cart) {
            $code = $coupon->code;
            $used_count = $coupon_counts_array[$code] ?? 0;    
            
            $is_valid_end_date = !$coupon->end_date || $coupon->end_date >= now();
            $coupon->is_enable = ($coupon->max_uses <= 0 || $used_count < $coupon->max_uses) 
                && $coupon->min_amount <= $price_cart
                && $is_valid_end_date
                ? 1 : 0;

                $coupon->user_details = [
                    'id' => $coupon->userDetails->id,
                    'name' => $coupon->userDetails->name,
                    'image' => $coupon->userDetails->image,
                ];
    
            return $coupon;
        });
      
        return response()->json([
            'success' => true,
            'message' => trans('messages.coupons_List'),
            'data' => $coupons_list,
        ], 200);
    }
    public function couponsAdd(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('coupons')->where(fn($query) => $query->where('is_deleted', 0)),
            ],
            'title'             => 'required|string|max:255',
            'start_time'        => 'required',
            'end_time'          => 'required',
            'per_person_use'    => 'nullable|numeric',
            'max_uses'          => 'required|numeric|gt:per_person_use',
            'min_amount'        => 'required|numeric',
            'maximum_amount'    => 'required|numeric|gt:min_amount',
            'start_date'        => 'required|date_format:m/d/Y|after_or_equal:' . now()->toDateString(),
            'end_date'          => 'required|date_format:m/d/Y|after:start_date',
            'type'              => 'required|string|in:discount_by_per,discount_by_amount',
        ], [
            'start_date.after_or_equal' => 'The start date cannot be in the past. Please select today or a future date.',
            'end_date.after'            => 'The end date must be after the start date.',
            'type.in'                   => 'Invalid coupon type. Allowed types: discount_by_per, discount_by_amount.',
        ]);
        
        if ($request->type === 'discount_by_per') {
            $validator->after(function ($validator) use ($request) {
                if (empty($request->is_per)) {
                    $validator->errors()->add('is_per', 'The percentage field is required.');
                } elseif (!is_numeric($request->is_per)) {
                    $validator->errors()->add('is_per', 'The percentage must be a number.');
                } elseif ($request->is_per < 0 || $request->is_per > 100) {
                    $validator->errors()->add('is_per', 'The percentage must be between 0 and 100.');
                }
            });
        } elseif ($request->type === 'discount_by_amount') {
            $validator->after(function ($validator) use ($request) {
                if (empty($request->is_amount)) {
                    $validator->errors()->add('is_amount', 'The amount field is required.');
                } elseif (!is_numeric($request->is_amount)) {
                    $validator->errors()->add('is_amount', 'The amount must be a number.');
                } elseif ($request->is_amount >= $request->min_amount) {
                    $validator->errors()->add('is_amount', 'The amount must be less than the minimum amount.');
                }
            });
        }
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 400,
                'errors' => $validator->errors()->all(),
            ]);
        }
        $startDatetime = \Carbon\Carbon::createFromFormat('m/d/Y', $request->start_date);
        $endDatetime = \Carbon\Carbon::createFromFormat('m/d/Y', $request->end_date);
        $coupon = new Coupon();
        $coupon->code = $request->code;
        $coupon->title = $request->title;
        $coupon->start_date = $startDatetime->toDateString();
        $coupon->start_time = date('H:i:s', strtotime( $request->start_time));
        $coupon->end_date = $endDatetime->toDateString();
        $coupon->end_time = date('H:i:s', strtotime( $request->end_time));
        $coupon->user_id = Auth::guard('api')->user()->id;
        $coupon->add_type = "vendor";
        $coupon->type = $request->type;
        $coupon->maximum_amount = $request->maximum_amount;
        $coupon->min_amount = $request->min_amount;
        $coupon->quantity = $request->quantity;
        $coupon->per_person_use = $request->per_person_use;
        $coupon->max_uses = $request->max_uses;
        if ($request->type === 'discount_by_per') {
            $coupon->is_per = $request->is_per;
            $coupon->is_amount = null;
        } elseif ($request->type === 'discount_by_amount') {
            $coupon->is_amount = $request->is_amount;
            $coupon->is_per = null;
        }
        if ($coupon->save()) {
            return response()->json([
                'success' => true,
                'message' => trans('messages.coupon_add_by_vendor_successfully'),
                'data'    => $coupon,
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => trans('messages.coupon_add_by_vendor_unsuccessfully'),
                'data'    => null,
            ], 400);
        }
    }

    public function couponsListVendor(Request $request){
        $user_id = Auth::guard('api')->user()->id;
        $coupon = Coupon::where('user_id',Auth::guard('api')->user()->id)->where('is_deleted',0)->orderBy('id', 'desc')->get();
        $coupon = $coupon->map(function ($coupon) {
            $usedCount = DB::table('orders')
                ->where('coupon_code', $coupon->code)
                ->count(); 
    
            $coupon->coupons_left = max(0, $coupon->max_uses - $usedCount);
            return $coupon;
        });
    
      
        return response()->json([
            'success' => true,
            'message' => trans('messages.coupon_list_fatch_successfully'),
            'data'    => $coupon,
            'user_id' => Auth::guard('api')->user()->id, 
        ], 200);
    }
    public function couponsEdit(Request $request){
        $validator = Validator::make($request->all(), [
            'couponId'              => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 400,
                'errors' => $validator->errors()->all()
            ]);
        }
        $id         = $request->couponId;
        $couponData = Coupon::find($id);
        return response()->json([
            'success' => true,
            'message' => trans('messages.coupon_list_fatch_successfully'),
            'data'    => $couponData,
            'coupon_id' => $id, 
        ], 200);
    }

    public function couponsDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'couponId' => 'required',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'status' => 400,
                'errors' => $validator->errors()->all()
            ]);
        }
        $id = $request->couponId;    
        $couponData = Coupon::where('user_id', Auth::guard('api')->user()->id)->where('id', $id)->first();
        if ($couponData) {
            $couponData->is_deleted = 1;
            $couponData->save();
            return response()->json([
                'success' => true,
                'message' => trans('messages.coupons_was_successfully_removed'),
                'data' => $couponData,
                'coupon_id' => $id,
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => trans('messages.coupon_not_found'),
                'data' => null,
            ], 400);
        }
    }
    
     public function couponsUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'couponId' => 'required|exists:coupons,id',
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('coupons')->ignore($request->couponId)->where(fn($query) => $query->where('is_deleted', 0)),
            ],
            'title'             => 'required|string|max:255',
            'start_time'        => 'required',
            'end_time'          => 'required',
            'per_person_use'    => 'nullable|numeric',
            'max_uses'          => 'required|numeric|gt:per_person_use',
            'min_amount'        => 'required|numeric',
            'maximum_amount'    => 'required|numeric|gt:min_amount',
            'start_date'        => 'required|date_format:m/d/Y|after_or_equal:' . now()->toDateString(),
            'end_date'          => 'required|date_format:m/d/Y|after:start_date',
            'type'              => 'required|string|in:discount_by_per,discount_by_amount',
        ], [
            'start_date.after_or_equal' => 'The start date cannot be in the past. Please select today or a future date.',
            'end_date.after'            => 'The end date must be after the start date.',
            'type.in'                   => 'Invalid coupon type. Allowed types: discount_by_per, discount_by_amount.',
        ]);
        
        if ($request->type === 'discount_by_per') {
            $validator->after(function ($validator) use ($request) {
                if (empty($request->is_per)) {
                    $validator->errors()->add('is_per', 'The percentage field is required.');
                } elseif (!is_numeric($request->is_per)) {
                    $validator->errors()->add('is_per', 'The percentage must be a number.');
                } elseif ($request->is_per < 0 || $request->is_per > 100) {
                    $validator->errors()->add('is_per', 'The percentage must be between 0 and 100.');
                }
            });
        } elseif ($request->type === 'discount_by_amount') {
            $validator->after(function ($validator) use ($request) {
                if (empty($request->is_amount)) {
                    $validator->errors()->add('is_amount', 'The amount field is required.');
                } elseif (!is_numeric($request->is_amount)) {
                    $validator->errors()->add('is_amount', 'The amount must be a number.');
                } elseif ($request->is_amount >= $request->min_amount) {
                    $validator->errors()->add('is_amount', 'The amount must be less than the minimum amount.');
                }
            });
        }
        if ($validator->fails()) {
            return response()->json([
                'status' => 400,
                'errors' => $validator->errors()->all(),
            ]);
        }
         $coupon = Coupon::where('id', $request->couponId)
             ->where('user_id', Auth::guard('api')->user()->id)
             ->first();
         if (!$coupon) {
             return response()->json([
                 'success' => false,
                 'message' => 'Coupon not found or does not belong to you.',
             ], 404);
         }        
        $startDatetime = \Carbon\Carbon::createFromFormat('m/d/Y', $request->start_date);
        $endDatetime = \Carbon\Carbon::createFromFormat('m/d/Y', $request->end_date);    
        $coupon->code = $request->code;
        $coupon->title = $request->title;
        $coupon->start_date = $startDatetime->toDateString();
        $coupon->start_time = date('H:i:s', strtotime( $request->start_time));
        $coupon->end_date = $endDatetime->toDateString();
        $coupon->end_time = date('H:i:s', strtotime( $request->end_time));
        $coupon->type = $request->type;
        $coupon->maximum_amount = $request->maximum_amount;
        $coupon->min_amount = $request->min_amount;
        $coupon->quantity = $request->quantity ?? $coupon->quantity;
        $coupon->per_person_use = $request->per_person_use;
        $coupon->max_uses = $request->max_uses;
        if ($request->type === 'discount_by_per') {
            $request->validate([
                'is_per' => 'required|numeric|between:0,100',
            ], [
                'is_per.required'  => 'The percentage field is required.',
                'is_per.numeric'   => 'The percentage must be a number.',
                'is_per.between'   => 'The percentage must be between 0 and 100.',
            ]);
            $coupon->is_per = $request->is_per;
            $coupon->is_amount = null;
        } elseif ($request->type === 'discount_by_amount') {
            $request->validate([
                'is_amount' => 'required|numeric',
            ], [
                'is_amount.required' => 'The amount field is required.',
                'is_amount.numeric'  => 'The amount must be a number.',
            ]);
            $coupon->is_amount = $request->is_amount;
            $coupon->is_per = null;
        }
    
        if ($coupon->save()) {
            return response()->json([
                'success' => true,
                'message' => 'Coupon updated successfully.',
                'data'    => $coupon,
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update coupon.',
            ], 400);
        }
    }


    public function applyCoupon(Request $request)
    {
        $user_id = Auth::guard('api')->user()->id;
        $cartData = Cart::where('user_id', $user_id)->get();
        $productIds = $cartData->pluck('product_id');
        $allUserIds = Product::whereIn('id', $productIds)->pluck('user_id');
        $couponCode = $request->coupon_code;
        $shipping_amount = Config::get('shipping.shipping_amount') ?? "0";
        $orderAmount = $request->amount - $shipping_amount;
        $currentDate = now();
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
                'message' => trans('messages.coupon_not_started'),
            ], 200);
        }
        if ($coupon->end_date < $currentDate) {
            return response()->json([
                'message' => trans('messages.coupon_expired'),
            ], 200);
        }
        $maximum_use          = $coupon->max_uses;
        $userUseCountCoupon   = Order::where('coupon_code', $couponCode)->where('coupon_id', $coupon->id)->where('user_id',Auth::guard('api')->user()->id)->count();
        $couponUsageCount     = Order::where('coupon_code', $couponCode)->count();
        if ($userUseCountCoupon >= $coupon->per_person_use) {
            return response()->json([
                'message' => trans('messages.you_coupon_usage_limit_reached'),
            ], 200);
        } 
        if ($couponUsageCount >= $coupon->max_uses) {
            return response()->json([
                'message' => trans('messages.coupon_usage_limit_reached_this_coupon_is_no_longer_valid'),
            ], 200);
        }    
        // $applicableProducts = Product::whereIn('id', $productIds)
        //                           ->where('user_id', $coupon->user_id)
        //                           ->pluck('id');
        //  if ($applicableProducts->isEmpty()) {
        //      return response()->json([
        //          'message' => trans('messages.coupon_not_applicable_to_cart_products'),
        //      ], 200);
        //  }
                $discountAmount = 0;
                foreach ($cartData as $cartProduct) {
                    $vendorProductId = Product::find($cartProduct->product_id)->user_id; 
                    $productVarientPrice = ProductVariant::find($cartProduct->product_varient_id)->price;          
                    if ($vendorProductId == $coupon->user_id) {                
                        $productPrice = $productVarientPrice * $cartProduct->qty;                
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
                        // $discountAmount += $productDiscount;
                    }
                }
             $finalAmount = $orderAmount - $discountAmount;
             $finalAmount = number_format($finalAmount, 2, '.', '');
         
             $is_default = $orderAmount > $coupon->min_amount ? 1 : 0;
         
             return response()->json([
                 'success'         => true,
                 'message'         => trans('messages.coupon_applied_successfully'),
                 'id'              => $coupon->id,
                 'coupon_code'     => $coupon->code,
                 'amount'          => $orderAmount,  
                 'discount'        => $discountAmount,
                 'total'           => $finalAmount,
                 'is_default'      => $is_default,
             ], 200);
    }


    public function removeCoupon(Request $request)
    {
        $couponCode = $request->input('coupon_code');
        $removeCoupon = $request->input('is_remove_coupon');
        $userDetails = Auth::guard('api')->user();
    
        $coupon = Coupon::where('code', $couponCode)->first();
    
        if (!$coupon) {
            return response()->json([
                'message' => trans('messages.Invalid_coupon'),
            ], 200);
        }
    
        $totalPrice = DB::table('carts')
            ->where('user_id', $userDetails->id)
            ->sum('price');
    
        return response()->json([
            'success'            => true,
            'message'            => trans('messages.coupon_removed_successfully'),
            'id'                 => $coupon->id,
            'coupon_code'        => $coupon->code,
            'is_remove_coupon'   => $removeCoupon,
            'discount_amount'    => 0,
            'amount'             => $totalPrice,
        ], 200);
    }
    
    

    
    
}