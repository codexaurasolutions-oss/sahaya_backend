<?php

namespace App\Http\Controllers\frontend;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use Auth;
use App\Models\Order;
use App\Models\User;
use App\Models\ProhibitedWord;
use App\Models\ReviewRating;
use App\Models\UserDeviceToken;
use DB;
use App;
use Validator,Config;


class RatingReviewController extends Controller
{
  public function ratingReviewSubmit(Request $request){
       $validator = Validator::make($request->all(), [
           'product_id'           => 'required',
           'review'               => 'required|max:100',
           'rating'               => 'required|integer|min:1|max:5',
           'product_varient_id'   => 'required',
       ]);
       if ($validator->fails()) {
           return response()->json([
               'status' => 400,
               'errors' => $validator->errors()->all()
           ]);
       }
       $prohibitedWords = ProhibitedWord::where('status', 1)->pluck('word')->toArray();
       $review          = $request->input('review');
       $containsProhibitedWord = false;
       foreach ($prohibitedWords as $word) {
           if (stripos($review, $word) !== false) {
               $containsProhibitedWord = true;
               break;
           }
       }
       if ($containsProhibitedWord) {
           return response()->json([
            'status' => 400,
               'success' => false,
               'message' => trans('messages.this_content_is_not_allowed_your_review_contains_prohibited_words'),
               'data' => null
           ], 400);
       }else{
             $newRating                     = new ReviewRating;
             $newRating->user_id            = Auth::guard('api')->user()->id;
             $newRating->product_id         = $request->product_id;
             $newRating->product_varient_id = $request->product_varient_id;
        //     $newRating->order_number       = $request->order_number;
             $newRating->rating             = $request->rating;
             $newRating->review             = $request->review;
             $saveRating                    = $newRating->save();
                if ($saveRating) {
                    // $product_details = Product::where('id',$request->product_id)->first();
                    // $seller = User::where('id', $product_details->user_id)->first();      
                    // $userDetailToken = UserDeviceToken::where('user_id', $product_details->user_id)->first();      
                    // $data=[];
                    // if($seller->push_notification == 1){
                    //    if (!empty($userDetailToken->device_token)) {
                    //     if($seller->language == 2){
                    //        $seller_order_des = Auth::guard('api')->user()->name.' Ürününüzü incelediniz';
                    //        $seller_msg_title = 'İncelenen ürün';
                    //     }else{
                    //         $seller_order_des = Auth::guard('api')->user()->name.' you have reviewd your product';
                    //         $seller_msg_title = 'Reviewd product';

                    //     }
                    //        $this->send_push_notification(
                    //            $userDetailToken->device_token,
                    //            $userDetailToken->device_type,
                    //            $seller_order_des,
                    //            $seller_msg_title,
                    //            'seller_rating',
                    //            $data
                    //        );
                    //        $notification                  = new Notification;
                    //        $notification->user_id         = $seller->id;
                    //        $notification->action_user_id  = Auth::guard('api')->user()->id;
                    //  //      $notification->order_number    = $newOrder->order_number ?? "";
                    //        $notification->description_en  = Auth::guard('api')->user()->name.' you have reviewd your product';
                    //        $notification->title_en        = 'Reviewd product';
                    //        $notification->title_tur       = 'İncelenen ürün';
                    //        $notification->description_tur = Auth::guard('api')->user()->name.' Ürününüzü incelediniz'
                    //        $notification->type            = "seller_rating";
                    //        $notification->send_by         = 1;
                    //        $notification->save();
                    //    }
                    // }

                   return response()->json([
                    'status' => 200,
                       'success' => true,
                       'message' => trans('messages.review_rating_saved_successfully'),
                       'data' => $saveRating
                   ], 200);
               } else {
                   return response()->json([
                    'status' => 400,
                       'success' => false,
                       'message' => trans('messages.review_rating_saved_unsuccessfully'),
                       'data' => null
                   ], 400);
               }
             }
        }

    
}