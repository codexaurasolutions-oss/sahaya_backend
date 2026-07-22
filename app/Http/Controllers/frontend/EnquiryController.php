<?php

namespace App\Http\Controllers\frontend;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Auth;
use App\Models\Enquiry;
use App\Models\Product;
use App\Models\Notification;
use App\Models\ProductColor;
use DB;
use App\Models\BlockUser;
use App\Models\ReviewRating;
use App\Models\UserDeviceToken;
use App;
use Carbon\Carbon;
use App\Models\User;
use App\Models\ProductEnquiryChats;
use App\Models\ProductEnquiry;
use Validator;

class EnquiryController extends Controller
{
    public function enquiry_submit(Request $request){
        $validator = Validator::make($request->all(), [
            'product_id'          => 'required',
            'description'             => 'required|max:200',
            'product_varient_id'  => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 400,
                'errors' => $validator->errors()->all(),
            ]);
        }
        $userId = Auth::guard('api')->user()->id;
        if (!$userId) {
            return response()->json([
                'status' => 401,
                'message' => 'Unauthorized user',
            ]);
        }
    
        $productId = $request->product_id;
        $variantId = $request->product_varient_id;
        $existingEnquiry = ProductEnquiry::where('sender_id', $userId)->where('product_id', $productId)->first();
        if ($existingEnquiry) {
            $existingEnquiry->update(['is_read' => 0]);
            $newEnquiryChat = new ProductEnquiryChats;
            $newEnquiryChat->enquiry_id = $existingEnquiry->id;
            $newEnquiryChat->product_id = $productId;
            $newEnquiryChat->product_varient_id = $variantId;
            $newEnquiryChat->sender_id = $userId;
            $newEnquiryChat->reciever_id = $existingEnquiry->sender_id;
            $newEnquiryChat->message = $request->description;
            $newEnquiryChat->is_sent = "user_send";
            $newEnquiryChat->save();
        } else {
            $newEnquiry = new ProductEnquiry;
            $newEnquiry->sender_id = $userId;
            $newEnquiry->product_id = $productId;
            $newEnquiry->reciever_id = Product::find($productId)->user_id;
            $newEnquiry->product_varient_id = $variantId;
            if ($newEnquiry->save()) {
                $newEnquiryChat = new ProductEnquiryChats;
                $newEnquiryChat->enquiry_id = $newEnquiry->id;
                $newEnquiryChat->product_id = $productId;
                $newEnquiryChat->product_varient_id = $variantId;
                $newEnquiryChat->sender_id = $userId;
                $newEnquiryChat->reciever_id = $newEnquiry->reciever_id;
                $newEnquiryChat->message = $request->description;
                $newEnquiryChat->is_sent = "user_send";
                $newEnquiryChat->save();
            }
            $userDetail  = User::find(Product::find($productId)->user_id);
            $userDetailToken =UserDeviceToken::where('user_id',$userDetail->id)->orderBy('id', 'desc')->first();
            if ($userDetail->language == 2) {
                $order_des = Auth::guard('api')->user()->name .' sana yeni bir soruşturma gönder';
                $msg_title =  'Yeni Sorgu Alındı';
            } else {
                $order_des = Auth::guard('api')->user()->name .' send you a new enquiry';
                $msg_title = 'New Enquiry Received';
            }       
            $data=[
               'enquiry_id' => $newEnquiry->id,
            ];
            if (!empty($userDetailToken->device_token)) {
                $this->send_push_notification(
                    $userDetailToken->device_token,
                    $userDetailToken->device_type,
                    $order_des,
                    $msg_title,
                    'new_enquiry_recevied',
                    $data
                );
                $notification                  = new Notification;
                $notification->user_id         = $userDetail->id;
                $notification->action_user_id  = Auth::guard('api')->user()->id;
                $notification->description_en  = Auth::guard('api')->user()->name .' send you a new enquiry';
                $notification->title_en        = 'New Enquiry Received';
                $notification->title_tur       = 'Yeni Sorgu Alındı';
                $notification->description_tur = Auth::guard('api')->user()->name .' sana yeni bir soruşturma gönder';
                $notification->type            = "new_enquiry_recevied";
                $notification->save();
        }
        return response()->json([
            'status' => 200,
            'message' => trans('messages.enquiry_and_chat_saved_successfully'),
        ]);
    }
    return response()->json([
        'status' => 200,
        'message' => trans('messages.enquiry_and_chat_saved_successfully'),
    ]);
   }

    public function sendProductEnquiry(Request $request)
    {
     

        $validator = Validator::make($request->all(), [
            'product_id'          => 'required',
            'message'             => 'required|max:200',
            'is_sent'             => 'required',
        //    'product_varient_id'  => 'required',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'status' => 400,
                'errors' => $validator->errors()->all(),
            ]);
        }
        $userId = Auth::guard('api')->user()->id;
        $productId = $request->product_id;
        $variantId = $request->product_varient_id;
        $senderId  = $request->sender_id;

        if($request->is_sent == "seller_send"){
            $existingEnquiry = ProductEnquiry::where('reciever_id', $userId)->where('sender_id',$senderId)->where('product_id', $productId)->first();
        }else{
        $existingEnquiry = ProductEnquiry::where('sender_id', $userId)->where('reciever_id',$senderId)->where('product_id', $productId)->first();
        }
        if ($existingEnquiry) {
            $existingEnquiry->update(['is_read' => 0]);
            $newEnquiryChat = new ProductEnquiryChats;
            $newEnquiryChat->enquiry_id = $existingEnquiry->id;
            $newEnquiryChat->product_id = $productId;
            $newEnquiryChat->product_varient_id = $variantId;
            $newEnquiryChat->sender_id = $userId;
            $newEnquiryChat->reciever_id = $existingEnquiry->sender_id;
            $newEnquiryChat->message = $request->message;
            $newEnquiryChat->is_sent = $request->is_sent;
            $newEnquiryChat->save();
        } else {
            $newEnquiry = new ProductEnquiry;
            $newEnquiry->sender_id = $userId;
            $newEnquiry->product_id = $productId;
            $newEnquiry->reciever_id = Product::find($productId)->user_id;
            $newEnquiry->product_varient_id = $variantId;
            if ($newEnquiry->save()) {
                $newEnquiryChat = new ProductEnquiryChats;
                $newEnquiryChat->enquiry_id = $newEnquiry->id;
                $newEnquiryChat->product_id = $productId;
                $newEnquiryChat->product_varient_id = $variantId;
                $newEnquiryChat->sender_id = $userId;
                $newEnquiryChat->reciever_id = $newEnquiry->reciever_id;
                $newEnquiryChat->message = $request->message;
                $newEnquiryChat->is_sent = $request->is_sent;
                $newEnquiryChat->save();
            }
        }
        return response()->json([
            'status' => 200,
            'message' => trans('messages.enquiry_and_chat_saved_successfully'),
        ]);
    }

    public function ProductEnquiryUserList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer|exists:products,id',
          //  'product_varient_id' => 'required|integer|exists:product_variants,id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 400,
                'errors' => $validator->errors()->all(),
            ]);
        }
        $userId = Auth::guard('api')->id();
        $allBlockUsersId = BlockUser::where('user_id',$userId)->pluck('block_user_id');
        $enquiries = ProductEnquiry::where('sender_id', $userId)
            ->where('product_id', $request->product_id)
            ->whereNotIn('reciever_id',$allBlockUsersId)
            ->orderBy('id','DESC')
            ->get();
        if ($enquiries->isEmpty()) {
            return response()->json([
                'status' => 404,
                'message' => trans('messages.enquiry_not_found'),
            ]);
        }
        return response()->json([
            'status' => 200,
            'message' => trans('messages.enquiry_and_chat_fatched_successfully'),
            'data' => $enquiries,
        ]);
    }
    

        public function ProductEnquirySellerList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id'         => 'required|integer|exists:products,id',
        //    'product_varient_id' => 'required|integer|exists:product_variants,id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 400,
                'errors' => $validator->errors()->all(),
            ]);
        }
        $sellerId = Auth::guard('api')->user()->id;
        $allBlockUsersId = BlockUser::where('user_id',$sellerId)->pluck('block_user_id');
        $productId = $request->product_id;
        $variantId = $request->product_varient_id;
        $product = Product::find($productId);
        if (!$product) {
            return response()->json([
                'status' => 404,
                'message' => trans('messages.product_not_found'),
            ]);
        }
        $sellerUserId = $product->user_id;
        if ($sellerId != $sellerUserId) {
            return response()->json([
                'status' => 403,
                'message' => trans('messages.not_authorized_to_view_enquiries'),
            ]);
        }
        $enquiries = ProductEnquiry::where('reciever_id', $sellerId)
            ->where('product_id', $productId)
            ->whereNotIn('sender_id',$allBlockUsersId)
            ->orderBy('id','DESC')
            ->get();
            $sortedEnquiries = $enquiries->sortByDesc(function ($enquiry) {
                $latestChat = ProductEnquiryChats::where('enquiry_id', $enquiry->id)
                    ->orderBy('id', 'DESC')
                    ->first();            
                return $latestChat ? $latestChat->id : 0;
            });
        if ($enquiries->isEmpty()) {
            return response()->json([
                'status' => 404,
                'message' => trans('messages.enquiry_not_found'),
            ]);
        }
        return response()->json([
            'status' => 200,
            'message' => trans('messages.enquiry_and_chat_fatched_successfully'),
            'data' => $enquiries,
        ]);
    }


    public function ProductEnquiryChat(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id'         => 'required|integer|exists:products,id',
         //   'product_varient_id' => 'required|integer|exists:product_variants,id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 400,
                'errors' => $validator->errors()->all(),
            ]);
        }
        $userId = Auth::guard('api')->user()->id;
        $productId = $request->product_id;
        $variantId = $request->product_varient_id;
        $sellerUserId = Product::find($productId)->user_id;
        if($userId !== $sellerUserId){
        $enquiry = ProductEnquiry::where('user_id', $userId)->where('seller_user_id',$sellerUserId)->where('product_id', $productId)
      //  ->where('product_varient_id', $variantId)
        ->first();
        }else{
            $enquiry = ProductEnquiry::where('seller_user_id',$sellerUserId)->where('product_id', $productId)
           // ->where('product_varient_id', $variantId)
            ->first();
        }
        if (!$enquiry) {
            return response()->json([
                'status' => 404,
                'message' => trans('messages.enquiry_not_found'),
            ]);
        }
        $allMessagesOfSeller = ProductEnquiryChats::where('enquiry_id', $enquiry->id)->get();
        return response()->json([
            'status' => 200,
            'message' => trans('messages.enquiry_and_chat_fatched_successfully'),
            'data' => $allMessagesOfSeller,
        ]);
    }

    public function userQuery(Request $request)
    {
        $userId = Auth::guard('api')->user()->id;   
        $allBlockUsersId = BlockUser::where('user_id',$userId)->pluck('block_user_id'); 
        $enquiries = ProductEnquiry::with([
            'productDetails.parentCategoryDetails',
            'productDetails.subCategoryDetails',
            'productDetails',
            'productVarientDetails',
            'reciever','sender'
        ])->where('sender_id', $userId)->whereNotIn('reciever_id',$allBlockUsersId)->get();
    
        $data = $enquiries->map(function ($enquiry) {
            $enquiryData = $enquiry;
            $variantColorDetails = ProductColor::with(['colorDetails', 'colorDetails.ColorsDescription'])
                ->where('product_id', $enquiry->product_id)
                ->where('color_id', $enquiry->productVarientDetails->color_id ?? null)
                ->first();
            $videoBaseUrl = "https://" . (env("CDN_HOSTNAME") ?? 'default-cdn-hostname');
            if ($variantColorDetails && $variantColorDetails->video) {
                $videoPath = $variantColorDetails->video;
                $variantColorDetails->video = $videoBaseUrl . "/" . $videoPath . "/playlist.m3u8";
                $variantColorDetails->video_thumbnail = $videoBaseUrl . "/" . $videoPath . "/thumbnail.jpg";
            } else {
                if ($variantColorDetails) {
                    $variantColorDetails->video = null;
                    $variantColorDetails->video_thumbnail = null;
                }
            }
            $avgRatingReview = ReviewRating::where('product_id', $enquiry->product_id)->avg('rating');
            $avgRatingReview = $avgRatingReview == 0 ? '0' : number_format($avgRatingReview, 1);
            //->where('product_varient_id',$enquiry->product_varient_id)
            $ratingReviewed = ReviewRating::where('product_id', $enquiry->product_id)->where('user_id',Auth::guard('api')->user()->id)->first();
            $ratingReviewArray = ReviewRating::where('product_id', $enquiry->product_id)->get()->map(function($ratingReview) {  return array_merge($ratingReview->toArray(),['userName' => $ratingReview->user->name,'userImage' => $ratingReview->user->image,'created_at' => Carbon::parse($ratingReview->created_at)->diffForHumans(),]);});
            $ratingReviewCount = ReviewRating::where('product_id', $enquiry->product_id)->count();
            $formattedRatingReviewCount = formatCount($ratingReviewCount);
            if (strpos($formattedRatingReviewCount, 'k') !== false) {
                $numericCount = floatval(str_replace('k', '', $formattedRatingReviewCount));
                if ($numericCount <= 1) {
                    $formattedRatingReviewCount = $formattedRatingReviewCount ;
                } else {
                    $formattedRatingReviewCount = $formattedRatingReviewCount ;
                }
            } else {
                if ($formattedRatingReviewCount <= 1) {
                    $formattedRatingReviewCount = $formattedRatingReviewCount ;
                } else {
                    $formattedRatingReviewCount = $formattedRatingReviewCount ;
                }
            }
            return [
                'productDetails' => $enquiry->productDetails,
                'productVarientDetails' => $enquiry->productVarientDetails,
                'reciever' => $enquiry->reciever,
                'sender' => $enquiry->sender,
                'variantColorDetails' => $variantColorDetails,
                'categoryDetails' => $enquiry->productDetails->parentCategoryDetails,
                'subcategoryDetails' => $enquiry->productDetails->subCategoryDetails,
                'RatingReviewArray' => [
                    'AvgRatingReview' => $avgRatingReview,
                    'RatingReviewList' => $ratingReviewArray,
                    'formattedRatingReviewCount' => $formattedRatingReviewCount,
                    'ratingReviewed'  => $ratingReviewed,
                ],
                'enquiries' => $enquiryData,
            ];
        });
        $data = $data->sortByDesc(function ($item) {
            $latestChat = ProductEnquiryChats::where('enquiry_id', $item['enquiries']->id)
                ->orderBy('id', 'DESC')
                ->first();
            return $latestChat ? $latestChat->id : 0;
        })->values();
    
        return response()->json([
            'status' => 200,
            'message' => trans('messages.enquiry_list_fetched_successfully'),
            'data' => $data,
        ]);
    }
    


    public function sellerQuery(Request $request)
    {
        $userId = Auth::guard('api')->user()->id;   
        $allBlockUsersId = BlockUser::where('user_id',$userId)->pluck('block_user_id');  
        $enquiries = ProductEnquiry::with([
            'productDetails.parentCategoryDetails',
            'productDetails.subCategoryDetails',
            'productDetails',
            'productVarientDetails',
            'reciever','sender',
        ])->where('reciever_id', $userId)->whereNotIn('sender_id',$allBlockUsersId)->get();
    
        $data = $enquiries->map(function ($enquiry) {
            $enquiryData = $enquiry;

            $variantColorDetails = ProductColor::with(['colorDetails', 'colorDetails.ColorsDescription'])
                ->where('product_id', $enquiry->product_id)
                ->where('color_id', $enquiry->productVarientDetails->color_id ?? null)
                ->first();
            $videoBaseUrl = "https://" . (env("CDN_HOSTNAME") ?? 'default-cdn-hostname');
            if ($variantColorDetails && $variantColorDetails->video) {
                $videoPath = $variantColorDetails->video;
                $variantColorDetails->video = $videoBaseUrl . "/" . $videoPath . "/playlist.m3u8";
                $variantColorDetails->video_thumbnail = $videoBaseUrl . "/" . $videoPath . "/thumbnail.jpg";
            } else {
                if ($variantColorDetails) {
                    $variantColorDetails->video = null;
                    $variantColorDetails->video_thumbnail = null;
                }
            }
            $avgRatingReview = ReviewRating::where('product_id', $enquiry->product_id)->avg('rating');
            $avgRatingReview = $avgRatingReview == 0 ? '0' : number_format($avgRatingReview, 1);
            //->where('product_varient_id',$enquiry->product_varient_id)
            $ratingReviewed = ReviewRating::where('product_id', $enquiry->product_id)->where('user_id',Auth::guard('api')->user()->id)->first();
            $ratingReviewArray = ReviewRating::where('product_id', $enquiry->product_id)->get()->map(function($ratingReview) {  return array_merge($ratingReview->toArray(),['userName' => $ratingReview->user->name,'userImage' => $ratingReview->user->image,'created_at' => Carbon::parse($ratingReview->created_at)->diffForHumans(),]);});
            $ratingReviewCount = ReviewRating::where('product_id', $enquiry->product_id)->count();
            $formattedRatingReviewCount = formatCount($ratingReviewCount);
            if (strpos($formattedRatingReviewCount, 'k') !== false) {
                $numericCount = floatval(str_replace('k', '', $formattedRatingReviewCount));
                if ($numericCount <= 1) {
                    $formattedRatingReviewCount = $formattedRatingReviewCount ;
                } else {
                    $formattedRatingReviewCount = $formattedRatingReviewCount ;
                }
            } else {
                if ($formattedRatingReviewCount <= 1) {
                    $formattedRatingReviewCount = $formattedRatingReviewCount ;
                } else {
                    $formattedRatingReviewCount = $formattedRatingReviewCount ;
                }
            }
            return [
                'productDetails' => $enquiry->productDetails,
                'productVarientDetails' => $enquiry->productVarientDetails,
                'reciever' => $enquiry->reciever,
                'sender' => $enquiry->sender,
                'variantColorDetails' => $variantColorDetails,
                'categoryDetails' => $enquiry->productDetails->parentCategoryDetails ?? "",
                'subcategoryDetails' => $enquiry->productDetails->subCategoryDetails ?? "",
                'RatingReviewArray' => [
                    'AvgRatingReview' => $avgRatingReview,
                    'RatingReviewList' => $ratingReviewArray,
                    'formattedRatingReviewCount' => $formattedRatingReviewCount,
                    'ratingReviewed'  => $ratingReviewed,
                ],
                'enquiries' => $enquiryData,

                
            ];
        });

        $data = $data->sortByDesc(function ($item) {
            $latestChat = ProductEnquiryChats::where('enquiry_id', $item['enquiries']->id)
                ->orderBy('id', 'DESC')
                ->first();
            return $latestChat ? $latestChat->id : 0;
        })->values();
    
        return response()->json([
            'status' => 200,
            'message' => trans('messages.enquiry_list_fetched_successfully'),
            'data' => $data,
        ]);
    }

    public function enquiryDetails(Request $request)
    {


        try {
            $enquiryId = $request->input('enquiryId');
            $userId = Auth::guard('api')->user()->id;    
            $existingEnquiry = ProductEnquiry::with([
                'productDetails.parentCategoryDetails',
                'productDetails.subCategoryDetails',
            ])->find($enquiryId);
    
            if (!$existingEnquiry) {
                return response()->json([
                    'status' => 404,
                    'message' => trans('messages.enquiry_not_found'),
                ]);
            }
            $existingEnquiry->update(['is_read' => 1]);
            $is_user_block_first  =  '';
            $productDetails        = $existingEnquiry->productDetails;
            $productVarientDetails = $existingEnquiry->productVarientDetails;
            $avgRatingReview       = ReviewRating::where('product_id', $existingEnquiry->product_id)->avg('rating');
            $avgRatingReview       = $avgRatingReview == 0 ? '0' : number_format($avgRatingReview, 1);
            $ratingReviewed        = ReviewRating::where('product_id', $existingEnquiry->product_id)->where('product_varient_id',$existingEnquiry->product_varient_id)->where('user_id',Auth::guard('api')->user()->id)->first();
            $ratingReviewArray     = ReviewRating::where('product_id', $existingEnquiry->product_id)->get()->map(function($ratingReview) {  return array_merge($ratingReview->toArray(),['userName' => $ratingReview->user->name,'userImage' => $ratingReview->user->image,'created_at' => Carbon::parse($ratingReview->created_at)->diffForHumans(),]);});
            $ratingReviewCount     = ReviewRating::where('product_id', $existingEnquiry->product_id)->count();
            $is_user_block_first   = BlockUser::where('block_user_id',$userId)->where('user_id',$existingEnquiry->reciever_id)->first();
            if($is_user_block_first){
            $is_block_user         = 1;
            }else{
            $is_block_user         = 0;
            }

            $formattedRatingReviewCount = formatCount($ratingReviewCount);
            if (strpos($formattedRatingReviewCount, 'k') !== false) {
                $numericCount = floatval(str_replace('k', '', $formattedRatingReviewCount));
                if ($numericCount <= 1) {
                    $formattedRatingReviewCount = $formattedRatingReviewCount ;
                } else {
                    $formattedRatingReviewCount = $formattedRatingReviewCount ;
                }
            } else {
                if ($formattedRatingReviewCount <= 1) {
                    $formattedRatingReviewCount = $formattedRatingReviewCount ;
                } else {
                    $formattedRatingReviewCount = $formattedRatingReviewCount ;
                }
            }
            $variantColorDetails = ProductColor::with(['colorDetails', 'colorDetails.ColorsDescription'])
                ->where('product_id', $existingEnquiry->product_id)
                ->where('color_id', $existingEnquiry->productVarientDetails->color_id ?? null)
                ->first();
            $videoBaseUrl = "https://" . (env("CDN_HOSTNAME") ?? 'default-cdn-hostname');
            if ($variantColorDetails && $variantColorDetails->video) {
                $videoPath = $variantColorDetails->video;
                $variantColorDetails->video = "{$videoBaseUrl}/{$videoPath}/playlist.m3u8";
                $variantColorDetails->video_thumbnail = "{$videoBaseUrl}/{$videoPath}/thumbnail.jpg";
            } elseif ($variantColorDetails) {
                $variantColorDetails->video = null;
                $variantColorDetails->video_thumbnail = null;
            }    
            $allChats = ProductEnquiryChats::where('enquiry_id', $enquiryId)->get();    
            $chatsWithDetails = $allChats->map(function ($chat) {
                return [
                    'id'          => $chat->id,
                    'enquiry_id'  => $chat->enquiry_id,
                    'message'     => $chat->message,
                    'sender_id'   => $chat->sender_id,
                    'sender'      => $chat->sender,
                    'receiver_id' => $chat->receiver_id,
                    'is_sent'     => $chat->is_sent,
                    'receiver'    => $chat->receiver,
                    'created_at'  => $chat->created_at,
                ];
            });
            return response()->json([
                'status'                => 200,
                'message'               => trans('messages.enquiry_list_fetched_successfully'),
                'allChats'              => $chatsWithDetails,
                'existingEnquiry'       => $existingEnquiry,
                'categoryDetails'       => $productDetails->parentCategoryDetails ?? null,
                'subcategoryDetails'    => $productDetails->subCategoryDetails ?? null,
                'variantColorDetails'   => $variantColorDetails,
                'productVarientDetails' => $productVarientDetails,
                'is_block_user'         => $is_block_user,
                'productDetails'        => $productDetails,
                'RatingReviewArray'     => [
                    'AvgRatingReview'            => $avgRatingReview,
                    'RatingReviewList'           => $ratingReviewArray,
                    'formattedRatingReviewCount' => $formattedRatingReviewCount,
                    'ratingReviewed'             => $ratingReviewed,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => trans('messages.something_went_wrong'),
                'error' => $e->getMessage(),
            ]);
        }
    }
    

    
    

}