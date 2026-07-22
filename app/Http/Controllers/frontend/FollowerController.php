<?php

namespace App\Http\Controllers\frontend;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Auth;
use App\Models\Enquiry;
use App\Models\UserDeviceToken;
use DB;
use App;
use App\Models\Notification;
use App\Models\Product;
use App\Models\User;
use App\Models\Follow;
use Validator;

class FollowerController extends Controller
{
    public function follow(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'member_user_id' => 'required|exists:users,id',
            'type'           => 'required|in:follow,unfollow'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status'  => 400,
                'error'   => $validator->errors(),
                'message' => trans('messages.required_files_are_cannot_be_null')
            ], 400);
        }
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json([
                'status'  => 401,
                'message' => trans('messages.unauthorized')
            ], 401);
        }
        try {
            $follow = Follow::where('user_id', $user->id)
                ->where('member_user_id', $request->member_user_id)
                ->first();
            
            if ($request->type == "unfollow") {
                if (!$follow) {
                    return response()->json([
                        'status'  => 400,
                        'message' => trans('messages.you_are_not_following_this_user')
                    ], 400);
                }    
                $follow->is_follow = 0;
                $follow->type = $request->type;
                $follow->save();
    
                return response()->json([
                    'status'  => 200,
                    'message' => trans('messages.unfollow_successfully'),
                ], 200);
    
            } elseif ($request->type == "follow") {
    
                if (!$follow) {
                    $follow = new Follow;
                    $follow->user_id = $user->id;
                    $follow->member_user_id = $request->member_user_id;
                }
                $follow->is_follow = 1; 
                $follow->type = $request->type;
                $follow->save();
                $userDetail  = User::find($request->member_user_id);
                $userDetailToken =UserDeviceToken::where('user_id',$userDetail->id)->orderBy('id', 'desc')->first();
                if ($userDetail->language == 2) {
                    $order_des =  $user->name .' artık senin takipçin';
                    $msg_title = ' Takip etmek';
                } else {
                    $order_des = $user->name .' is now your follower';
                    $msg_title = ' Follow';
                }        
                $data=[
                    'user_id' => $user->id, 
                    'user_name' => $user->name,
                ];
                if($userDetail->push_notification == 1){
                  if (!empty($userDetailToken->device_token)) {
                      $this->send_push_notification(
                          $userDetailToken->device_token,
                          $userDetailToken->device_type,
                          $order_des,
                          $msg_title,
                          'start_following',
                          $data
                      );
                  $notification = new Notification;
                  $notification->user_id   = $request->member_user_id;
                  $notification->action_user_id = Auth::guard('api')->user()->id;
                  $notification->description_en = $user->name .' is now your follower';
                  $notification->title_en  = 'Follow';
                  $notification->title_tur ='Takip etmek';
                  $notification->description_tur =  $user->name .' artık senin takipçin';
                  $notification->type           = "start_following";
                  $notification->save();
                  return response()->json([
                      'status'  => 200,
                      'message' => trans('messages.follow_successfully'),
                  ], 200);
                 }
            }
        }
            
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 400,
                'message' => trans('messages.something_went_wrong'),
                'error'   => $e->getMessage()
            ], 400);
        }
    }


    public function removefollow(Request $request){
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status'  => 400,
                'error'   => $validator->errors(),
                'message' => trans('messages.required_files_are_cannot_be_null')
            ], 400);
        }
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json([
                'status'  => 401,
                'message' => trans('messages.unauthorized')
            ], 401);
        }
        $follow = Follow::where('user_id', $request->user_id)
                ->where('member_user_id', $user->id)
                ->first();
                if (!$follow) {
                    return response()->json([
                        'status'  => 400,
                        'message' => trans('messages.this_user_not_following_you')
                    ], 400);
                }    
                $follow->is_follow = 0;
                $follow->type = "unfollow";
                $follow->save();
                return response()->json([
                    'status'  => 200,
                    'message' => trans('messages.remove_follower_successfully'),
                ], 200);
    }


    public function profileFeed(Request $request){
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:users,id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status'  => 400,
                'error'   => $validator->errors(),
                'message' => trans('messages.required_files_are_cannot_be_null')
            ], 400);
        }
        $user = User::where([
            'id'			=>$request->id,
            'user_role_id'	=> Config('constants.ROLE_ID.CUSTOMER_ROLE_ID'),
            'is_active'		=>1,
            'is_deleted'	=>0,
        ])->select('id','name','image')->first();
            $userId  = $user->id;
			$user->total_followers	= Follow::where('member_user_id',$request->id)->where('type','follow')->where('is_follow',1)->count() ?? 0; 
			$user->total_following 	= Follow::where('user_id',$request->id)->where('type','follow')->where('is_follow',1)->count() ?? 0;
            $isFollow               = Follow::where('user_id',Auth::guard('api')->user()->id ?? null)->where('type', 'follow')->where('is_follow', 1)->where('member_user_id', $request->id)->exists();
            $user->is_follow        = $isFollow ? 1 : 0;
			$followerList           = Follow::where('member_user_id',$request->id)->where('type','follow')->where('is_follow',1)->with('user','userfollowing')->get()->map(function ($follow) use ($userId) {
            $isFollowBack           = Follow::where('member_user_id', $follow->user->id ?? null)->where('type', 'follow')->where('is_follow', 1)->where('member_user_id', $userId)->exists();
            $isFollow               = Follow::where('user_id', Auth::guard('api')->user()->id ?? null)->where('type', 'follow')->where('is_follow', 1)->where('member_user_id', $follow->user->id)->exists();
				return [
					'userId'       => $follow->user->id ?? null,
					'name'         => $follow->user->name ?? null,
					'image'        => $follow->user->image ?? null,
					'is_follow'    => $isFollow ? 1 : 0,
					// 'is_unfollow'  => $follow->is_follow ? 0 : 1,
					// 'is_follow_back' => $isFollowBack ? 1 : 0,
				];
			});
			    $followingList = Follow::where('user_id',$request->id)->where('type','follow')->where('is_follow',1)->with('user','userfollowing')->get()->map(function ($follow) use ($userId) {
				$isFollowBack  = Follow::where('user_id', $follow->userfollowing->id ?? null)->where('type', 'follow')->where('is_follow', 1)->where('member_user_id', $userId)->exists();
				$isFollow      = Follow::where('user_id', Auth::guard('api')->user()->id ?? null)->where('type', 'follow')->where('is_follow', 1)->where('member_user_id', $follow->userfollowing->id)->exists();
				return [
					'userId'       => $follow->userfollowing->id ?? null,
					'name'         => $follow->userfollowing->name ?? null,
					'image'        => $follow->userfollowing->image ?? null,
					'is_follow'    => $isFollow ? 1 : 0,
					// 'is_unfollow'  => $follow->is_follow ? 0 : 1,
					// 'is_follow_back' => $isFollowBack ? 1 : 0,
				];
			});
			$user->total_videos 	           = Product::where('user_id',$request->id)->where('is_active',1)->where('is_deleted',0)->count();
            $response["data"]['userDetails']   = $user;
			$response["data"]["followerList"]  = $followerList;
			$response["data"]["followingList"] = $followingList;
			$response["status"]                = "success";
			return $response;
    }
  

}