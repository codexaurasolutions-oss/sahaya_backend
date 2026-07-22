<?php

namespace App\Http\Controllers;

use App\Models\EmailAction;
use App\Models\EmailTemplate;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\path;
use Illuminate\Support\Facades\DB;
use App\Models\EmailLog;
use Config;
use Mail;
use Request;

class Controller
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

	protected $user;
	
	public function __construct() {
		$url	=	Request::fullUrl();
	}// end function __construct()

	public function checkPermission($url){
		$segment1	=	Request()->segment(1);
		$segment2	=	Request()->segment(2);
		$segment3	=	Request()->segment(3);
		
		$segment1_1 = explode(' ', $segment1);
		$segment1_1 = end($segment1_1);
		$segment2_2 = explode(' ', $segment2);
		$segment2_2 = end($segment2_2);
		$segment3_3 = explode(' ', $segment3);
		$segment3_3 = end($segment3_3);
		
		if (in_array($segment1_1,$actions_arr) || in_array($segment2_2,$actions_arr) || in_array($segment3_3,$actions_arr)){
			return 1;
		}
		
		$user_id				=	Auth::user()->id;
		$user_role_id			=	Auth::user()->user_role_id;
		$path					=	Request()->path();
		$action					=	Route::current()->getAction();
		
		$function_name	=	explode("\\",$action['controller']);
		$function_name	=	end($function_name);
		$permissionData			=	DB::table("user_permission_actions")
											->select("user_permission_actions.is_active")
											->leftJoin("acl_admin_actions","acl_admin_actions.id","=","user_permission_actions.admin_module_action_id")
											->where('user_permission_actions.user_id',$user_id)
											->where('user_permission_actions.is_active',1)
											->where('acl_admin_actions.function_name',$function_name)
											->first();
		
		$byDefaultPermissionData = DB::table("acl_admin_actions")
		->where('acl_admin_actions.is_show',0)
		->where('acl_admin_actions.function_name',$function_name)
		->first();
		if(!empty($permissionData) || !empty($byDefaultPermissionData)){
			return 1;
		}else{
			return 0;
		}
	}

    public function buildTree($parentId = 0){
		$user_id	    =	Auth::guard('admin')->user()->id;
		$user_role_id	=	Auth::guard('admin')->user()->user_role_id;
		$branch         =   array();
		$elements       =   array();
		$superadmin = Config('constants.ROLE_ID.SUPER_ADMIN_ROLE_ID');
        $language_id  = Session()->get('sel_lang');
		if($user_role_id == $superadmin){
			$elements = DB::table("acls")
                ->select("acls.*","acls.title as title")
                ->where("acls.parent_id",$parentId)
                ->where("acls.is_active",1)
                ->orderBy('acls.module_order','ASC')
                ->get();
		}else {
			if($parentId == 0){
				$elements = DB::table("acls")
                    ->select("acls.*","acls.title as title")
                    ->where("acls.parent_id",$parentId)
                    ->where("acls.is_active",1)
                    ->where("acls.id",DB::raw("(select admin_module_id from user_permissions where user_permissions.admin_module_id = acls.id AND is_active = 1 AND user_id = $user_id LIMIT 1)"))
                    ->orderBy('acls.module_order','ASC')
                    ->get();
			}else{ 
				$elements = 	DB::table("acls")
                    ->where("acls.parent_id",$parentId)
                    ->where("acls.is_active",1)
                    ->where("acls.id",DB::raw("(select admin_sub_module_id from user_permission_actions where user_permission_actions.admin_sub_module_id = acls.id AND is_active = 1 AND user_id = $user_id LIMIT 1)"))
                    ->orderBy('acls.module_order','ASC')
                    ->get();  
			}
		}
        
		foreach($elements as $element){
			if ($element->parent_id == $parentId){
				$children = $this->buildTree($element->id);
				if ($children){
					$element->children = $children;
				}
				$branch[] = $element;
			}
		}
		return $branch;
	}

	public function arrayStripTags($array)
    {
        $result = array();
        foreach ($array as $key => $value) {
            // Don't allow tags on key either, maybe useful for dynamic forms.
            $key = strip_tags($key, config('constants.ALLOWED_TAGS_XSS'));

            // If the value is an array, we will just recurse back into the
            // function to keep stripping the tags out of the array,
            // otherwise we will set the stripped value.
            if (is_array($value)) {
                $result[$key] = $this->arrayStripTags($value);
            } else {
                // I am using strip_tags(), you may use htmlentities(),
                // also I am doing trim() here, you may remove it, if you wish.
                $result[$key] = trim(strip_tags($value, config('constants.ALLOWED_TAGS_XSS')));
            }
        }

        return $result;

    }

	public function change_error_msg_layout($errors = array())
    {
        $response = array();
        $response["status"] = "error";
        if (!empty($errors)) {
            $error_msg = "";
            foreach ($errors as $errormsg) {
                $error_msg1 = (!empty($errormsg[0])) ? $errormsg[0] : "";
                $error_msg .= $error_msg1 . ", ";
            }
            $response["msg"] = trim($error_msg, ", ");
        } else {
            $response["msg"] = "";
        }
        $response["data"] = (object) array();
        $response["errors"] = $errors;
        return $response;
    }

    public function change_error_msg_layout_with_array($errors = array())
    {
        $response = array();
        $response["status"] = "error";
        if (!empty($errors)) {
            $error_msg = "";
            foreach ($errors as $errormsg) {
                $error_msg1 = (!empty($errormsg[0])) ? $errormsg[0] : "";
                $error_msg .= $error_msg1 . ", ";
            }
            $response["msg"] = trim($error_msg, ", ");
        } else {
            $response["msg"] = "";
        }
        $response["data"] = array();
        $response["errors"] = $errors;
        return $response;
    }

	public function getVerificationCode(){
		$code	=	rand(1000,9999);
	   
		return $code;
	}
	public function sendMail($to, $fullName, $subject, $messageBody, $from = '', $files = false, $path = '', $attachmentName = '')
    {
        $from = Config::get("Site.from_email");
        $data = array();
        $data['to'] = $to;
        $data['from'] = (!empty($from) ? $from : Config::get("Site.email"));
        $data['fullName'] = $fullName;
        $data['subject'] = $subject;
        $data['filepath'] = $path;
        $data['attachmentName'] = $attachmentName;
        try{
            if ($files === false) {
                Mail::send('emails.template', array('messageBody' => $messageBody), function ($message) use ($data) {
                    $message->to($data['to'], $data['fullName'])->from($data['from'])->subject($data['subject']);
                });
            } else {
                if ($attachmentName != '') {
                    Mail::send('emails.template', array('messageBody' => $messageBody), function ($message) use ($data) {
                        $message->to($data['to'], $data['fullName'])->from($data['from'])->subject($data['subject'])->attach($data['filepath'], array('as' => $data['attachmentName']));
                    });
                } else {
                    Mail::send('emails.template', array('messageBody' => $messageBody), function ($message) use ($data) {
                        $message->to($data['to'], $data['fullName'])->from($data['from'])->subject($data['subject'])->attach($data['filepath']);
                    });
                }
            }
        } catch (\Swift_TransportException $e) {
            \Log::error($e);
            } catch (\Exception $e) {
        
                \Log::error($e);
            }
        
        $obj                  =  new EmailLog;
        $obj->email_to        =  $data['to'];
        $obj->email_from      =  $from;
        $obj->subject         =  $data['subject'];
        $obj->message         =  $messageBody;
        $obj->save();
    }

    public function current_language_id(){
		$language_code  = session()->get('admin_applocale');
        $language        = DB::table('languages')->where('lang_code',$language_code)->first();
        $language_id    = $language->id ?? 1;
		
		return $language_id;
	}
    
    public function saveCkeditorImages()
    {
        if (!empty($_GET['CKEditorFuncNum'])) {
            $image_url = "";
            $msg = "";
            // Will be returned empty if no problems
            $callback = ($_GET['CKEditorFuncNum']); // Tells CKeditor which function you are executing
            $image_details = getimagesize($_FILES['upload']["tmp_name"]);
            $image_mime_type = (isset($image_details["mime"]) && !empty($image_details["mime"])) ? $image_details["mime"] : "";
            if ($image_mime_type == 'image/jpeg' || $image_mime_type == 'image/jpg' || $image_mime_type == 'image/gif' || $image_mime_type == 'image/png') {
                $ext = $this->getExtension($_FILES['upload']['name']);
                $fileName = "ck_editor_" . time() . "." . $ext;
                $upload_path = config('constants.CK_EDITOR_ROOT_PATH');
                if (move_uploaded_file($_FILES['upload']['tmp_name'], $upload_path . $fileName)) {
                    $image_url = config('constants.CK_EDITOR_URL') . $fileName;
                }
            } else {
                $msg = 'error : Please select a valid image. valid extension are jpeg, jpg, gif, png';
            }
            $output = '<script type="text/javascript">window.parent.CKEDITOR.tools.callFunction(' . $callback . ', "' . $image_url . '","' . $msg . '");</script>';
            echo $output;
            exit;
        }
    }

    public function getExtension($str)
    {
        $i = strrpos($str, ".");
        if (!$i) {return "";}
        $l = strlen($str) - $i;
        $ext = substr($str, $i + 1, $l);
        $ext = strtolower($ext);
        return $ext;
    }


    public function setEmailTemplate($action='',$sendData="",$email='')
	{
		$email                                  =  $email ?? Config('Site.email');
        $settingsEmail                          =  Config('Site.email');
        $emailActions                           =  EmailAction::where('action', '=',$action)->get()->toArray();
        $emailTemplates                         =  EmailTemplate::where('action', '=',$action)->select("name", "action","body",'subject')->get()->toArray();
        $cons = explode(',', $emailActions[0]['options']);
        $constants = array();
        foreach ($cons as $key => $val) {
            $constants[] = '{' . $val . '}';
        }
        $subject     = $emailTemplates[0]['subject'];
        $messageBody = str_replace($constants, $sendData, $emailTemplates[0]['body']);
        $this->sendMail($email,$emailTemplates[0]['subject'],$emailTemplates[0]['subject'],$messageBody, $settingsEmail);
	}   
    
    public function uploadVideoOnCDN($videoFile, $title = "")
    {
        if ($videoFile) {
            $cdnKey = env("CDN_API_KEY");
            $VIDEO_LIBRARY_ID = env("VIDEO_LIBRARY_ID");
            $header     = array("Content-Type: application/json", "Accept: application/json", "AccessKey: $cdnKey");
            $data = array("title" => $title, "collectionId" => "");

            $url  =    "http://video.bunnycdn.com/library/$VIDEO_LIBRARY_ID/videos";
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 0);
            curl_setopt($curl, CURLOPT_TIMEOUT, 30); //timeout in seconds
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
            $result = curl_exec($curl);
            $result    =    json_decode($result, true);

            $file = fopen("upload_video_errors.txt", "a+");
            fwrite($file, date('Y-m-d H:i:s'));
            fwrite($file, json_encode($result));
            fwrite($file, "\n\n");
            fclose($file);

            if (!curl_error($curl)) {
                $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                if ($httpcode == 201 || $httpcode == 200) {
                    $guid    =     !empty($result["guid"]) ? $result["guid"] : "";
                    if ($guid != "") {
                        $cdnKey = env("CDN_API_KEY");
                        $VIDEO_LIBRARY_ID = env("VIDEO_LIBRARY_ID");
                        $header     = array("Accept: application/json", "AccessKey: $cdnKey");
                        $localFile     = $videoFile->getRealPath();
                        $fp         = fopen($localFile, 'r');
                        $url  =    "http://video.bunnycdn.com/library/$VIDEO_LIBRARY_ID/videos/$guid";
                        $curl = curl_init($url);
                        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
                        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
                        curl_setopt($curl, CURLOPT_UPLOAD, 1);
                        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
                        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 0);
                        curl_setopt($curl, CURLOPT_TIMEOUT, 30); //timeout in seconds
                        curl_setopt($curl, CURLOPT_INFILE, $fp);
                        $result = curl_exec($curl);
                        $result    =    json_decode($result, true);


                        $file = fopen("upload_video_errors.txt", "a+");
                        fwrite($file, date('Y-m-d H:i:s'));
                        fwrite($file, json_encode($result));
                        fwrite($file, "\n\n");
                        fclose($file);

                        if (!curl_error($curl)) {
                            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                            if ($httpcode == 201 || $httpcode == 200) {
                                return array("status" => "success", "message" => "", "guid" => $guid);
                            } else {
                                return array("status" => "error", "message" => "We are unable to process you request. Please try again");
                            }
                        } else {
                            return array("status" => "error", "message" => "We are unable to process you request. Please try again");
                        }
                    } else {
                        return array("status" => "error", "message" => "We are unable to process you request. Please try again");
                    }
                } else {
                    return array("status" => "error", "message" => "We are unable to process you request. Please try again");
                }
            } else {
                return array("status" => "error", "message" => "We are unable to process you request. Please try again");
            }
        }
    }

    public function deleteVideoOnCDN($guid)
    {
        if ($guid) {
            $cdnKey = env("CDN_API_KEY");
            $VIDEO_LIBRARY_ID = env("VIDEO_LIBRARY_ID");
            $header = array("Accept: application/json", "AccessKey: $cdnKey");
            $url = "http://video.bunnycdn.com/library/$VIDEO_LIBRARY_ID/videos/$guid";
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 0);
            curl_setopt($curl, CURLOPT_TIMEOUT, 30); // Timeout in seconds

            // Set CURLOPT_RETURNTRANSFER to true to capture the response
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

            $result = curl_exec($curl);
            // Check for cURL errors or HTTP status code
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if ($httpCode !== 200) {
                // Handle the error here if needed
                // For example, log the error, throw an exception, etc.
            }

            $result = json_decode($result, true);
            $file = fopen("upload_video_errors.txt", "a+");
            fwrite($file, date('Y-m-d H:i:s'));
            fwrite($file, json_encode($result));
            fwrite($file, "\n\n");
            fclose($file);
        }
    }

    function getFirebaseAccessToken() {
        $cacheKey = 'firebase_access_token';
        $cached = cache()->get($cacheKey);
        if ($cached) {
            return $cached;
        }

        // Priority 1: Read from env var (base64 encoded) — for Railway deployment
        $serviceAccountJson = env('FIREBASE_SERVICE_ACCOUNT', '');
        if (!empty($serviceAccountJson)) {
            $decoded = base64_decode($serviceAccountJson, true);
            if ($decoded !== false) {
                $key = json_decode($decoded, true);
            } else {
                $key = json_decode($serviceAccountJson, true);
            }
        } else {
            // Priority 2: Read from file — for local development
            $keyFilePath = public_path('sahayya-firebase.json');
            if (!file_exists($keyFilePath)) {
                throw new Exception('Service account file not found. Set FIREBASE_SERVICE_ACCOUNT env var or place sahayya-firebase.json in public/');
            }
            $key = json_decode(file_get_contents($keyFilePath), true);
        }

        if (!$key || !isset($key['client_email'])) {
            throw new Exception('Invalid service account credentials');
        }

        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT'
        ];

        $now = time();
        $payload = [
            'iss' => $key['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($header)));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload)));

        $signature = '';
        openssl_sign("$base64UrlHeader.$base64UrlPayload", $signature, $key['private_key'], 'sha256');

        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        $jwt = "$base64UrlHeader.$base64UrlPayload.$base64UrlSignature";

        $postData = http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception('Curl error: ' . curl_error($ch));
        }

        curl_close($ch);

        $jsonResponse = json_decode($response, true);

        if (isset($jsonResponse['error'])) {
            throw new Exception('Error fetching access token: ' . $jsonResponse['error']);
        }

        $token = $jsonResponse['access_token'];
        cache()->put($cacheKey, $token, now()->addMinutes(55));
        return $token;
    }

    public function send_push_notification($deviceToken = "", $device_type = "", $message = "", $notification_title = "", $notification_type = "", $data = [])
{
    $notificationData = array_merge(
        $data ?: [],
        [
            'type' => $notification_type,
            'title' => $notification_title,
            'body' => $message,
        ]
    );
    $notificationData = array_map('strval', $notificationData);

    $notification = [
        "token" => $deviceToken,
        "notification" => [
            "title" => $notification_title ?: 'Sahayya',
            "body"  => $message,
        ],
        "data" => $notificationData,
        "android" => [
            "priority" => "HIGH",
            "notification" => [
                "channel_id" => "sahayya-notifications",
                "sound" => "default",
                "default_sound" => true,
                "default_vibrate_timings" => true,
                "notification_priority" => "PRIORITY_HIGH",
            ],
        ],
        "apns" => [
            "headers" => [
                "apns-priority" => "10",
            ],
            "payload" => [
                "aps" => [
                    "alert" => [
                        "title" => $notification_title ?: 'Sahayya',
                        "body" => $message,
                    ],
                    "sound" => "default",
                    "badge" => 1,
                    "content-available" => 1,
                ],
            ],
        ],
    ];

    $body = json_encode(["message" => $notification]);

    $baerer_token = $this->getFirebaseAccessToken();

    $headers = [
        'Authorization: Bearer ' . $baerer_token,
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    $ch = curl_init();
    $fcmProjectId = env('FCM_PROJECT_ID', 'sahayya-a6422');
    curl_setopt($ch, CURLOPT_URL, "https://fcm.googleapis.com/v1/projects/{$fcmProjectId}/messages:send");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        \Log::info("FCM Push OK [{$notification_type}] HTTP {$httpCode}");
    } else {
        \Log::error("FCM Push FAILED [{$notification_type}] HTTP {$httpCode}: " . substr($result, 0, 500));
    }

    return ["response" => $result, "request" => $body];
}


    // public function send_push_notification($deviceToken = "",$device_type = "",$message = "",$notification_title = "",$notification_type = "",$data = array()){
    //     if(empty($data)){
    //         $body = json_encode([
    //             "message" => [
    //                 "notification" => [
    //                     "title" => $notification_title,
    //                     "body" => $message,
    //                 ],
    //                 "token" => $deviceToken,
    //             ]
    //         ]);
    //     }else {
    //         $body = json_encode([
    //             "message" => [
    //                 "notification" => [
    //                     "title" => $notification_title,
    //                     "body" => $message,
    //                 ],
                    
    //                 "data" => array_merge($data, ["type" => $notification_type]), 
    //                 "data"=>$data,
    //                 "token" => $deviceToken,
    //                 "type" => $notification_type
    //             ]
    //         ]);
    //     }
    //     $baerer_token = $this->getFirebaseAccessToken(); // firebase token

    //     $headers = [
    //         'Authorization: Bearer ' . $baerer_token,
    //         'Content-Type: application/json',
    //         'Accept: application/json',
    //     ];

    //     $ch = curl_init();
    //     curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/v1/projects/ayva-fc350/messages:send');
    //     curl_setopt($ch, CURLOPT_POST, true);
    //     curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    //     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    //     curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    //     curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    //     $result = curl_exec($ch);
    //     curl_close($ch);

    //     $file = fopen("pushnotifications.txt", "a+");
    //     fwrite($file, "\n\n");
    //     fwrite($file, $body);
    //     fwrite($file, "\n\n");
    //     fwrite($file, json_encode($result));
    //     fwrite($file, "\n\n");
    //     fclose($file);

    //     return ["response" => $result, "request" => $body];
    // }

}
