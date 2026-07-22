<?php

namespace App\Http\Controllers\frontend;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Auth;
use App\Models\Enquiry;
use App\Models\Product;
use App\Models\ProductColor;
use DB;
use App\Models\ReviewRating;
use App;
use Carbon\Carbon;
use App\Models\Notification;
use App\Models\ProductEnquiryChats;
use App\Models\ProductEnquiry;
use App\Models\BlockUser;
use Validator;

class NotificationController extends Controller
{

    public function notificationList(Request $request){
        $languageId = Auth::guard('api')->user()->language;
        if($languageId == 2){
            $notificationList = Notification::with(['senderDetails','userDetails'])->where('user_id',Auth::guard('api')->user()->id)->select('id','user_id','action_user_id','title_tur as title','description_tur as description','type','is_read','send_by')->orderBy('created_at','DESC')->get();
        }else{
            $notificationList = Notification::with(['senderDetails','userDetails'])->where('user_id',Auth::guard('api')->user()->id)->select('id','user_id','action_user_id','title_en as title','description_en as description','type','is_read','send_by')->orderBy('created_at','DESC')->get();
        }
        return response()->json([
            'status' => 200,
            'message' => trans('messages.notification_list_fatched_successfully'),
            'data' => $notificationList,
        ]);
    }

    public function clearAllNotifications(){
        $user_id = Auth::guard('api')->user()->id;
        Notification::where('user_id',$user_id)->delete();
    }


    public function blockUserProduct(Request $request)
    {
        $userId     = Auth::guard('api')->user()->id;
        $productId  = $request->product_id;
        $blckUserId = $request->block_user_id;
        $enquiryId  = $request->enquiry_id;

        $blockUser                = new BlockUser;
        $blockUser->user_id       = $userId;
        $blockUser->block_user_id = $blckUserId;
        $blockUser->product_id    = $productId;
        $blockUser->enquiry_id    = $enquiryId;
        $blockUser->type          = $request->type;
        $blockUser->message       = $request->message;
        $blockUser->save();
        return response()->json([
            'status' => 200,
            'message' => trans('messages.user_block_successfully'),
        ]);
    }

    
    public function blockUserList(Request $request){
        $userId = Auth::guard('api')->user()->id;
        $notificationList = BlockUser::with(['blockUserDetails','userDetails'])->where('user_id',Auth::guard('api')->user()->id)->get();
        return response()->json([
            'status' => 200,
            'message' => trans('messages.block_list_fetched_successfully'),
            'data' => $notificationList,
        ]);
    }

    

    public function UnBlockUsers(Request $request)
    {
        $userId = Auth::guard('api')->user()->id;
        $blckUserId = $request->block_user_id;
        $user_block=BlockUser::where('user_id',$userId)->where('block_user_id',$blckUserId)->delete();
        return response()->json([
            'status' => 200,
            'message' => trans('messages.user_unblock_successfully'),
        ]);
    }

        
    // public function UnBlockUsers(Request $request){
    //     $userId = Auth::guard('api')->user()->id;
    //     $notificationList = BlockUser::with(['senderDetails','userDetails'])->where('user_id',Auth::guard('api')->user()->id)->get();
    //     return response()->json([
    //         'status' => 200,
    //         'message' => trans('messages.user_unblock_successfully'),
    //         'data' => $notificationList,
    //     ]);
    // }



}