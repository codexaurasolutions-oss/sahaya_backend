<?php
namespace App\Http\Controllers\frontend;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB, Session;
use App\Models\EmailAction;
use App\Models\EmailTemplate;
use App\Models\UserInformation;
use App\Models\User;
use App\Models\ContactUs;
use App\Models\ReviewRating;
use App\Models\MobileIntroScreen;
use App\Models\Lookup;
use App\Models\Faq;
use App\Models\Notification;
use App\Models\Category;
use App\Models\ProductAction;
use App\Models\Follow;
use App\Models\CategoryColor;
use App\Models\Size;
use App\Models\BlockUser;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductVariant;
use App\Models\ProductSize;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use DateTime,DateTimeZone,Config;
use Illuminate\Validation\Rules\Password;
use App\Models\UserDeviceToken;
use App\Classes\AgoraDynamicKey\RtcTokenBuilder;
use App\Models\Color;
use App\Rules\MaxVideoDuration;
use App\Traits\ImageUpload;

class UserController extends Controller
{
    use ImageUpload;

	public function __construct(Request $request)
	{
		parent::__construct();
		$this->request              =   $request;
	}
	public function signup(Request $request)
	{
		if ($request->isMethod("POST")) {
			$formData	=	$request->all();
			if (!empty($formData)) {
					$validator = Validator::make(
						$request->all(),
						array(
							'first_name' 			   		=> 'required',
							'last_name' 			   		=> 'required',
							'email' 	               		=> 'required|unique:users,email|regex:/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/',
							'phone_number' 			   		=> 'required|digits_between:10,20',
							'phone_number_prefix'           => 'required',
							'phone_number_country_code'		=> 'required',
							'gender' 						=> 'required',
							'password'                 		=> ['required', Password::min(8)],
							'confirm_password'         		=> 'required|same:password',
							'terms_and_conditions'     		=> 'required',
                            'documents_front' 				=> 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                            'documents_back' 				=> 'nullable|image|mimes:jpeg,png,jpg|max:2048',
						),
						array(
							"password.min"                      	=> trans("messages.password_validation_string"),
							"email.regex"                       	=> trans("messages.the_email_should_be_a_valid_email"),
							"phone_number.regex"                     => trans("messages.the_phone_number_should_be_a_valid_phone_number"),
							'phone_number.digits_between' 		        	=> trans('messages.the_phone_number_must_be_10_digits'),
							"email.unique"               			=> trans("messages.the_email_has_already_been_taken"),
							"phone_number.unique"               	=> trans("messages.the_phone_number_has_already_been_taken"),
							"first_name.required"               	=> trans("messages.this_field_is_required"),
							"phone_number.required"               	=> trans("messages.this_field_is_required"),
							"last_name.required"                	=> trans("messages.this_field_is_required"),
							"email.required"                  		=> trans("messages.this_field_is_required"),
							"phone_number_prefix.required"      	=> trans("messages.this_field_is_required"),
							"phone_number_country_code.required"	=> trans("messages.this_field_is_required"),
							"password.required"               		=> trans("messages.this_field_is_required"),
							"confirm_password.required"             => trans("messages.this_field_is_required"),
							"gender.required"                  		=> trans("messages.this_field_is_required"),
							"terms_and_conditions.required"     	=> trans("messages.this_field_is_required"),

                            'documents_front.image'					=> trans("messages.please_upload_an_image"),
                            'documents_front.mimes'					=> trans("messages.the_image_must_be_a_file_of_type_jpeg_png_jpg"),
                            'documents_front.max'					=> trans("messages.the_image_size_should_not_be_greater_than_2MB"),
                            'documents_back.image'					=> trans("messages.please_upload_an_image"),
                            'documents_back.mimes'					=> trans("messages.the_image_must_be_a_file_of_type_jpeg_png_jpg"),
                            'documents_back.max'					=> trans("messages.the_image_size_should_not_be_greater_than_2MB"),

						),
					);
				if ($validator->fails()) {
					if ($request->wantsJson()) {
						$response			=	$this->change_error_msg_layout($validator->errors()->getMessages());
						$response["status"] = "error";
						$response["msg"] = trans("messages.input_field_is_required");
					}
				} else {
					$language_id                       =   $this->current_language_id();
					$verification_code	            		= $this->getVerificationCode();
					$obj						    		= new User;
					$obj->user_role_id			    		= Config('constants.ROLE_ID.CUSTOMER_ROLE_ID');
					$obj->first_name			    		= ucwords($request->input('first_name'));
					$obj->last_name			        		= ucwords($request->input('last_name'));
					$obj->name			            		= ucwords($request->input('first_name') .' '. $request->input('last_name'));
					$obj->email					    		= $request->input('email');
					$obj->phone_number				 		= $request->input('phone_number');
					$obj->phone_number_prefix				= $request->input('phone_number_prefix');
					$obj->phone_number_country_code			= $request->input('phone_number_country_code');
					$obj->password                  		= Hash::make($request->password);
					$obj->gender                    		= $request->gender;
					$obj->verification_code	        		= $verification_code;
					$obj->language	        				= $language_id;
					$obj->push_notification	        		= 1;
					$obj->forgot_password_validate_string 	= md5($request->input('email') . time() . time());
                    if ($request->hasFile('documents_front')) {
                        $extension = $request->file('documents_front')->getClientOriginalExtension();
                        $fileName = time() . '-documents_front.' . $extension;
                        $folderName = strtoupper(date('M') . date('Y')) . "/";
                        $folderPath = Config('constants.USER_IMAGE_ROOT_PATH') . $folderName;
                        if (!File::exists($folderPath)) {
                            File::makeDirectory($folderPath, $mode = 0777, true);
                        }

                        if ($request->file('documents_front')->move($folderPath, $fileName)) {
                            $obj->documents_front = $folderName . $fileName;
                        }
                    }

                    if ($request->hasFile('documents_back')) {
                        $extension = $request->file('documents_back')->getClientOriginalExtension();
                        $fileName = time() . '-documents_back.' . $extension;
                        $folderName = strtoupper(date('M') . date('Y')) . "/";
                        $folderPath = Config('constants.USER_IMAGE_ROOT_PATH') . $folderName;
                        if (!File::exists($folderPath)) {
                            File::makeDirectory($folderPath, $mode = 0777, true);
                        }
                        if ($request->file('documents_back')->move($folderPath, $fileName)) {
                            $obj->documents_back = $folderName . $fileName;
                        }
                    }


					$flag = $obj->save();
					$user_validate_string	         		= $obj->forgot_password_validate_string;

					$settingsEmail 							=	Config::get('Site.from_email');
					$emailActions           				=   EmailAction::where('action', '=', 'account_verification')->get()->toArray();
					$emailTemplates         				=   EmailTemplate::where('action', '=', 'account_verification')->select("name", "action", "subject", "body")->get()->toArray();
					$cons                   				=   explode(',', $emailActions[0]['options']);
					$constants              				=   array();
					foreach ($cons as $key => $val) {
						$constants[]        				=   '{' . $val . '}';
					}
					$subject                				=   $emailTemplates[0]['subject'];
					$rep_Array              				=   array($obj->name, $obj->verification_code);
					$messageBody            				=   str_replace($constants, $rep_Array, $emailTemplates[0]['body']);
					$this->sendMail($obj->email, $obj->name, $subject, $messageBody, $settingsEmail);


					if ($flag) {
						if ($request->wantsJson()) {
							$response["status"] = "success";
							$response["msg"] = trans("messages.your_account_has_been_created_successfully");
							$response["user_validate_string"] = $user_validate_string;
							return $response;
						}
					} else {
						if ($request->wantsJson()) {
							$response["status"] = "success";
							$response["msg"] = trans("messages.something_went_wrong");
							return $response;
						}
					}
				}
			}
		}
		if ($request->wantsJson()) {
			$response["status"] = "success";
			return $response;
		}
	}
	public function otp(Request $request, $validate_string,$otp_for = null)
	{
		$userDetail = User::where('forgot_password_validate_string', $validate_string)->where('is_active', 1)->where('is_deleted', 0)->first();
		if (!empty($validate_string) && $userDetail != '') {

			if ($request->isMethod('POST')) {
				Validator::extend('check_verification_code', function ($attribute, $value, $parameters) use ($userDetail) {
					if (($userDetail->verification_code == $value)) {
						return true;
					} else {
						return false;
					}
				});
				
				$thisData = $request->all();
				if (!empty($thisData)) {
					$validator 					=	Validator::make(
						$request->all(),
						array(
							'verification_code'             			=> 'required|numeric|digits:4|check_verification_code',
						),
						array(
							'verification_code.required'    			=> trans("messages.this_field_is_required"),
							'verification_code.numeric'     			=> trans("messages.this_field_must_be_numeric"),
							'verification_code.digits'      	   		=> trans("messages.the_verification_code_field_must_be_at_least_4_characters"),
							'verification_code.check_verification_code' => trans("messages.the_verification_code_is_not_matched") 
						)
					);

					if ($validator->fails()) {
						if ($request->wantsJson()) {
							$response			=	$this->change_error_msg_layout($validator->errors()->getMessages());
							$response["status"] = "error";
							$response["msg"] = trans("messages.input_field_is_required");
							return $response;
						}
					} else {
						if (($request->wantsJson() && $otp_for == 'forget-password')) {
							$userDetail->verification_code			   = '';
							$userDetail->is_verified                     =  1;
							$userDetail->save();

							if ($request->wantsJson()) {
								$response["status"] = "success";
								$response["msg"] = trans("messages.otp_is_verified_please_create_your_new_password");
								$response["forgot_password_validate_string"] = $userDetail->forgot_password_validate_string;
								return $response;
							}
						}


							User::where('id', $userDetail->id)->update(['verification_code' => '', 'is_verified' => 1, 'forgot_password_validate_string' => '']);

							if ($userDetail) {
								$settingsEmail 			=	Config::get('Site.from_email');
								$emailActions           =   EmailAction::where('action', '=', 'registration_successful')->get()->toArray();
								$emailTemplates         =   EmailTemplate::where('action', '=', 'registration_successful')->select("name", "action", "subject", "body")->get()->toArray();

								$cons                   =   explode(',', $emailActions[0]['options']);
								$constants              =   array();
								foreach ($cons as $key => $val) {
									$constants[]        =   '{' . $val . '}';
								}
								$subject                =   $emailTemplates[0]['subject'];
								$rep_Array              =   array($userDetail->name, $userDetail->email, $userDetail->phone_number);
								$messageBody            =   str_replace($constants, $rep_Array, $emailTemplates[0]['body']);
								$this->sendMail($userDetail->email, $userDetail->name, $subject, $messageBody, $settingsEmail);
							}

							if(!empty($request->input('device_id')) && !empty($request->input('device_type')) || !empty($request->input('device_token'))){
								$userDeviceTokenDetail = UserDeviceToken::where("user_id",$userDetail->id)->first();
								if(!empty($userDeviceTokenDetail)){
									$userDeviceTokenDetail->user_id = $userDetail->id;
									$userDeviceTokenDetail->device_type = $request->input('device_type')??"";
									$userDeviceTokenDetail->device_id = $request->input('device_id')??"";
									$userDeviceTokenDetail->device_token = (!empty($request->input('device_token'))) ? $request->input('device_token') : "";
									$userDeviceTokenDetail->save();
								}else{
									$userDeviceTokenDetail = new UserDeviceToken();
									$userDeviceTokenDetail->user_id = $userDetail->id;
									$userDeviceTokenDetail->device_type = $request->input('device_type')??"";
									$userDeviceTokenDetail->device_id = $request->input('device_id')??"";
									$userDeviceTokenDetail->device_token = (!empty($request->input('device_token'))) ? $request->input('device_token') : "";
									$userDeviceTokenDetail->save();
								}
							} 
							$token                      =    $userDetail->createToken('Diabetes Virtual Care Access Client')->plainTextToken;

							$response["status"] = "success";
							$response["token"] 	= $token;
							$response["msg"] 	= trans("messages.your_account_has_been_successfully_verified");
							return $response;
						}
					}
				}
		} else {
			$response['verification_string']			=	[trans("messages.the_verification_is_expired")];
			$response["status"] = "error";
			$response["msg"] = trans("messages.the_verification_is_expired");
			return $response;

		}
	}
	public function resendOtp(Request $request, $validate_string, $otp_for = null)
	{
		$user	= User::where('forgot_password_validate_string', $validate_string)->where('is_active', 1)->where('is_deleted', 0)->first();
		if (!empty($validate_string) && $user != '') {

			$verification_code				      =  $this->getVerificationCode();
			$user->verification_code	 	      =  $verification_code;
			$user->verification_code_sent_time	  =  date("Y-m-d H:i:s");
			$SavedResponse                        = $user->save();
			if ($SavedResponse) {
				if ($request->wantsJson() && $otp_for == 'signup') {
					$email_template = DB::table('email_templates')->where('action', 'account_verification')->first();
					$body = $email_template->body;

					if (str_contains($email_template->body, '{USER_NAME}')) {
						$body = str_replace('{USER_NAME}', $user->name, $email_template->body);
					}
					if (str_contains($body, '{CODE}')) {
						$body = str_replace('{CODE}', $verification_code, $body);
					}
					$user->verification_code = $verification_code;
					(new Controller)->sendMail($user->email, $user->name, $email_template->subject, $body);

					$user->verification_code_sent_time = now();
				}
				if ($request->wantsJson() && $otp_for == 'forget-password') {
					$email_template = DB::table('email_templates')->where('action', 'user_forgot_password')->first();
					$body = $email_template->body;
					if (str_contains($email_template->body, '{USER_NAME}')) {
						$body = str_replace('{USER_NAME}', $user->name, $email_template->body);
					}
					if (str_contains($body, '{CODE}')) {
						$body = str_replace('{CODE}', $verification_code, $body);
					}
					$user->verification_code = $verification_code;
					(new Controller)->sendMail($user->email, $user->name, $email_template->subject, $body);
					$user->verification_code_sent_time = now();
				}
				if ($request->wantsJson()) {
					$response["status"] = "success";
					$response["msg"] = trans("messages.otp_has_been_resend_successfully");
					$response["user_validate_string"] = $validate_string;
					return $response;
				}
			}
		} else {
			$response['verification_string']			=	[trans("messages.the_verification_is_expired")];
			$response["status"] = "error";
			$response["msg"] = trans("messages.the_verification_is_expired");
			return $response;

		}
	}

	public function login(Request $request)
	{
		if ($request->isMethod('POST')) {
			$thisData = $request->all();
			if (!empty($thisData)) {
				$validator 					=	Validator::make(
					$request->all(),
					array(
						'email'      							=> 'required|regex:/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/',
						'password'      					    => 'required',
					),
					array(
						'email.required'						=> trans("messages.this_field_is_required"),
						'password.required'						=> trans("messages.this_field_is_required"),
						"email.regex"                           => trans("messages.the_email_should_be_a_valid_email"),
					)
				);
				if ($validator->fails()) {
					if ($request->wantsJson()) {
						$response			=	$this->change_error_msg_layout($validator->errors()->getMessages());
						$response["status"] = "error";
						$response["msg"] = trans("messages.input_field_is_required");
					}
				} else {
					
					$userDetail		 = User::where(['email'=> $request->email]);
					if ($request->wantsJson()) {
						$userDetail		= $userDetail->where('user_role_id',2);
					}else{
						$userDetail 	= $userDetail->whereIn('user_role_id',[2,4]);
					}
					$userDetail 		= $userDetail->first();

					if (!empty($userDetail)) {
						$AuthAttemptUser = (!empty($userDetail)) ? Hash::check($request->input('password'), $userDetail->getAuthPassword()) : array();
						if ($AuthAttemptUser) {
                            if($userDetail->is_deleted == 1){
								if ($request->wantsJson()) {
									$response			=	$this->change_error_msg_layout($validator->errors()->getMessages());
									$response["status"] = "error";
									$response["msg"] = trans("messages.your_account_has_been_deleted_please_contact_to_admin_for_more_details");
									return $response;
								}
							} else if ($userDetail->is_active == 0) {
								if ($request->wantsJson()) {
									$response			=	$this->change_error_msg_layout($validator->errors()->getMessages());
									$response["status"] = "error";
									$response["msg"] = trans("messages.your_account_has_been_deactivated_please_contact_to_admin_for_more_details");
									return $response;
								}
							} elseif ($userDetail->is_verified == 0) {
								$verification_code				      =  $this->getVerificationCode();
								$userDetail->verification_code = $verification_code;
								$userDetail->verification_code_sent_time = now();
								$userDetail->forgot_password_validate_string	=   md5($userDetail->phone_number . time() . time());
								$user_validate_string	= $userDetail->forgot_password_validate_string;

								$userDetail->save();

								$settingsEmail 			=	Config::get('Site.from_email');
								$emailActions           =   EmailAction::where('action', '=', 'account_verification')->get()->toArray();
								$emailTemplates         =   EmailTemplate::where('action', '=', 'account_verification')->select("name", "action", "subject", "body")->get()->toArray();

								$cons                   =   explode(',', $emailActions[0]['options']);
								$constants              =   array();
								foreach ($cons as $key => $val) {
									$constants[]        =   '{' . $val . '}';
								}
								$subject                =   $emailTemplates[0]['subject'];
								$rep_Array              =   array($userDetail->name, $userDetail->verification_code);
								$messageBody            =   str_replace($constants, $rep_Array, $emailTemplates[0]['body']);
								$this->sendMail($userDetail->email, $userDetail->name, $subject, $messageBody, $settingsEmail);

								if ($request->wantsJson()) {
									$response			=	$this->change_error_msg_layout($validator->errors()->getMessages());
									$response["status"] = "error";
									$response["msg"] = trans("messages.your_account_is_not_verified_please_verify_first");
									$response["user_validate_string"] = $user_validate_string;
									return $response;
								}
							}

							if ($request->wantsJson()) {
								$token                      =    $userDetail->createToken('Ayva')->plainTextToken;

								if(!empty($request->input('device_id')) && !empty($request->input('device_type')) || !empty($request->input('device_token'))){
									$UserDeviceToken = UserDeviceToken::where("user_id",$userDetail->id)->first();
									if(!empty($UserDeviceToken)){
										$userDeviceTokenDetail = $UserDeviceToken;
										$userDeviceTokenDetail->user_id = $userDetail->id;
										$userDeviceTokenDetail->device_type = $request->input('device_type')??"";
										$userDeviceTokenDetail->device_id = $request->input('device_id')??"";
										$userDeviceTokenDetail->device_token = (!empty($request->input('device_token'))) ? $request->input('device_token') : "";
										$userDeviceTokenDetail->save();
									}else{
										$userDeviceTokenDetail = new UserDeviceToken();
										$userDeviceTokenDetail->user_id = $userDetail->id;
										$userDeviceTokenDetail->device_type = $request->input('device_type')??"";
										$userDeviceTokenDetail->device_id = $request->input('device_id')??"";
										$userDeviceTokenDetail->device_token = (!empty($request->input('device_token'))) ? $request->input('device_token') : "";
										$userDeviceTokenDetail->save();
									}
								}  
								$response["data"]['userDetails'] = $userDetail;
								//$response["data"]['userDetails']->image = $userDetail->image;
								

								$response["data"]['social_links'] = array();
								if(!empty(config('Social.facebook'))){
									$response["data"]['social_links']['facebook'] = config('Social.facebook');
								}
								if(!empty(config('Social.twitter'))){
									$response["data"]['social_links']['twitter'] = config('Social.twitter');
								}
								if(!empty(config('Social.instagram'))){
									$response["data"]['social_links']['instagram'] = config('Social.instagram');
								}
								$response["status"] 		= "success";
								$response["msg"] 			= trans("messages.you_are_now_logged_in");
								$response["token"]         = $token;
								return $response;
							}
						} else {
							if ($request->wantsJson()) {
								$response["status"] = "error";
								$response["msg"] = trans("messages.your_email_or_password_is_incorrect");
								return $response;
							}
						}
					} else {
						if ($request->wantsJson()) {
							$response["status"] = "error";
							$response["msg"] = trans("messages.your_email_or_password_is_incorrect");
							return $response;
						}
					}
				}
			}
		}
	}
	public function forgot_password(Request $request)
	{
		if ($request->isMethod('POST')) {
			Validator::extend('check_email', function ($attribute, $value, $parameters) {
				$user	= User::where('is_active', 1)->where('email', $value)->where('is_deleted', 0)->first();
				if ((!empty($user))) {
					return true;
				} else {
					return false;
				}
			});
			$thisData = $request->all();
			if (!empty($thisData)) {
				$validator 					=	Validator::make(
					$request->all(),
					array(
						'email'                 => 'required|email|regex:/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/',
					),
					array(
						'email.email'		    => trans("messages.the_email_should_be_a_valid_email"),
						'email.required'		=> trans("messages.this_field_is_required"),
						'email.check_email'		=> trans("messages.the_email_should_be_a_valid_email"),
						"email.regex"           => trans("messages.the_email_should_be_a_valid_email"),

					)
				);

				if ($validator->fails()) {
					if ($request->wantsJson()) {
						$response			=	$this->change_error_msg_layout($validator->errors()->getMessages());
						$response["status"] = "error";
						$response["msg"] = trans("messages.input_field_is_required");
						return $response;
					}
				} else {
					$user	= User::where('is_active', 1)->where('email', $request->email)->where('is_deleted', 0)->whereIn('user_role_id',[2,4])->first();
					if($user == null){
						if ($request->wantsJson()) {
							$response["status"] = "error";
							$response["msg"] = trans("messages.email_id_not_found_please_enter_your_registered_email_id");
							return $response;
						}
					}
					$verification_code	=	$this->getVerificationCode();
					$user->forgot_password_validate_string	=   md5($user->phone_number . time() . time());
					$user->verification_code	 			=  $verification_code;
					$user->verification_code_sent_time	 	=  date("Y-m-d H:i:s");
					$SavedResponse = $user->save();

					$user_validate_string	= $user->forgot_password_validate_string;
					if (!$SavedResponse) {
						if ($request->wantsJson()) {
							$response			=	$this->change_error_msg_layout($validator->errors()->getMessages());
							$response["status"] = "error";
							$response["msg"] = trans("messages.something_went_wrong");
							return $response;
						}
					} else {
						$settingsEmail 							=	Config::get('Site.from_email');
						$emailActions           				=   EmailAction::where('action', '=', 'user_forgot_password')->get()->toArray();
						$emailTemplates         				=   EmailTemplate::where('action', '=', 'user_forgot_password')->select("name", "action", "subject", "body")->get()->toArray();
						$cons                   				=   explode(',', $emailActions[0]['options']);
						$constants              				=   array();
						foreach ($cons as $key => $val) {
							$constants[]        				=   '{' . $val . '}';
						}
						$subject                				=   $emailTemplates[0]['subject'];
						$rep_Array              				=   array($user->name, $user->verification_code);
						$messageBody            				=   str_replace($constants, $rep_Array, $emailTemplates[0]['body']);
						$this->sendMail($user->email, $user->name, $subject, $messageBody, $settingsEmail);

						if($request->wantsJson()){
							$response["status"] = "success";
							$response['user_validate_string'] = $user_validate_string;
							$response["msg"] = trans("messages.if_you_are_a_register_user_a_password_reset_email_will_be_sent");
							return $response;
						}
					}
				}
			}
		}
	}
	public function reset_password(Request $request, $validate_string)
	{
		$user = User::where('forgot_password_validate_string', $validate_string)->where('is_active', 1)->where('is_deleted', 0)->first();
		if (!empty($validate_string) && $user != '') {
			if ($request->isMethod('POST')) {
				$thisData = $request->all();
				if (!empty($thisData)) {
					$validator 					=	Validator::make(
						$request->all(),
						array(
							'password'          => ['required', Password::min(8)],
							'confirm_password'  => 'required|same:password',
						),
						array(
							'password.required'	=> trans("messages.this_field_is_required"),
							'password.min'	=> trans("messages.password_validation_string"),
						)
					);
					if ($validator->fails()) {
						if ($request->wantsJson()) {
							$response			=	$this->change_error_msg_layout($validator->errors()->getMessages());
							$response["status"] = "error";
							$response["msg"] = trans("messages.input_field_is_required");
							return $response;
						}
					} else {
						$user->password   = Hash::make($request->password);
						$user->forgot_password_validate_string   = null;
						$user->save();
						$SavedResponse = $user->save();
						if (!$SavedResponse) {
							if ($request->wantsJson()) {
								$response["status"] = "error";
								$response["msg"] = trans("messages.something_went_wrong");
								return $response;
							}
						} else {
							if ($request->wantsJson()) {
								$response["status"] = "success";
								$response["msg"] = trans("messages.your_password_has_been_updated_successfully");
								return $response;
							}
						}
					}
				}
			}
		} else {
			$response['verification_string']			=	[trans("messages.the_verification_is_expired")];
			$response["status"] = "error";
			$response["msg"] = trans("messages.the_verification_is_expired");
			return $response;
		}
	}
	public function socialLoginCallback(Request $request)
	{
		$social_user = $request->data;
		$provider 	 = $request->provider;
		$language_id                       =   $this->current_language_id();
	
		if($provider == 'apple'){
			$dataArr = [
				'name'        	=> $social_user['user'],
				'first_name'    => $social_user['first_name'] ?? '',
				'last_name'     => $social_user['last_name'] ?? '',
				'social_type' 	=> $provider,
				'social_id'   	=> $social_user['id'],
				'user_role_id'	=> Config('constants.ROLE_ID.CUSTOMER_ROLE_ID'),
				'is_active'   	=> 1,
				'language'   	=> $language_id,
				// 'is_approved' 	=> 1,
				'is_verified' 	=> 1
			];
			if(!empty($social_user['phone_number'])){
				$dataArr['phone_number_prefix']     	= $social_user['phone_number_prefix'] ?? '';
				$dataArr['phone_number_country_code']   = $social_user['phone_number_country_code'] ?? '';
				$dataArr['phone_number']     			= $social_user['phone_number'] ?? '';
			}
			$user = User::firstOrCreate($dataArr);
		}else{
			if (!empty($social_user) && $social_user['email']) {
				$condition = ['email' => $social_user['email'], 'is_deleted' => 0];
			} else {
				$condition = ['social_type' => $provider, 'social_id' => $social_user['id'], 'is_deleted' => 0,'user_role_id'=>Config('constants.ROLE_ID.CUSTOMER_ROLE_ID')];
			}
			$user = User::firstOrNew($condition);
			$user->name 			= $social_user['name'];
			$user->first_name 		= $social_user['first_name'] ?? '';
			$user->last_name 		= $social_user['last_name'] ?? '';
			$user->language 		= $language_id;
			$user->social_type 	= $provider;
			$user->social_id 		= $social_user['id'];
			$user->user_role_id 	= Config('constants.ROLE_ID.CUSTOMER_ROLE_ID');
			$user->is_active 		= 1;
			// $user->is_approved 	= 1;
			$user->is_verified 		= 1;
			if(!empty($social_user['phone_number'])){
				$user->phone_number_prefix 			= $social_user['phone_number_prefix'] ?? '';
				$user->phone_number_country_code	= $social_user['phone_number_country_code'] ?? '';
				$user->phone_number 				= $social_user['phone_number'] ?? '';
			}
			$user->save();
		}
	
		if ($user) {
			$user_devices 				= UserDeviceToken::where('user_id',$user->id)->first();
			!empty($user_devices) ? $userDevices = $user_devices : $userDevices = new UserDeviceToken;
			$obj						= $userDevices;
			$obj->user_id 				= $user->id;
			$obj->device_token			= $request->device_token ?? '';
			$obj->device_type			= $request->device_type ?? '';
			$obj->device_id				= $request->device_id ?? '';
			$obj->save();
			if ($user->image) {
				$user->image = $user->image ?? '';
			}
			$user_data = $user->only(['id',
				'user_role_id',
				'name',
				'first_name',
				'last_name',
				'email',
				'phone_number_prefix',
				'phone_number_country_code',
				'phone_number',
				'address',
				'gender',
				'dob',
				'is_approved',
				'is_verified',
				'is_active',
				'government_id',
				'emergency_contact',
				'image'
			]);
			$response['token'] = $user->createToken('authToken')->plainTextToken;
			$response['status'] = 'success';
			$response["msg"]	= trans('messages.login_successful');
			$response['data'] = $user_data;
		} else {
			$response['status'] = 'success';
			$response["msg"]	= trans("messages.something_went_wrong");
			$response['data'] = (object) [];
		}
		return response()->json($response);
	}

	public function manageProfile(Request $request) 
	{
		if ($request->wantsJson()) {
			$user = Auth::guard('api')->user();
			$user->dob              = $user->dob ? Carbon::createFromFormat('Y-m-d', $user->dob)->format(Config('Reading.date_format')) : NULL;
			$user->documents_front  = $user->documents_front ?  Config('constants.USER_IMAGE_PATH').$user->documents_front : null ;
			$user->documents_back   = $user->documents_back ?  Config('constants.USER_IMAGE_PATH').$user->documents_back : null ;
			$user->image 			= $user->image;
			$response["data"]['userDetails'] = $user;
			$response["status"] = "success";
			return $response;
		}
	}
	public function updatePersonDetails(Request $request)
	{ 
		$formData = $request->all();
	
		if (!empty($formData)) {
			if($request->wantsJson()){
				$user = Auth::guard('api')->user();	
			}
			$validator = Validator::make(
				$request->all(),
			[
				'first_name'     						=> 'required',
				'last_name' 	 						=> 'required',
				'phone_number' 			   				=> 'required|unique:users,phone_number,'.$user->id.'|digits_between:10,20',
				'phone_number_prefix'           		=> 'required',
				'phone_number_country_code'				=> 'required',
				'gender' 								=> 'required',
				'image' 								=> 'nullable|image|mimes:jpeg,png,jpg|max:2048',
				'documents_front' 						=> 'nullable|image|mimes:jpeg,png,jpg|max:2048',
				'documents_back' 						=> 'nullable|image|mimes:jpeg,png,jpg|max:2048',
			], [
				'phone_number.digits_between' 		        	=> trans("messages.the_phone_number_must_be_10_digits"),
				"phone_number.unique"               	=> trans("messages.the_phone_number_has_already_been_taken"),
				"first_name.required"               	=> trans("messages.this_field_is_required"),
				"phone_number.required"               	=> trans("messages.this_field_is_required"),
				"last_name.required"                	=> trans("messages.this_field_is_required"),
				"phone_number_prefix.required"      	=> trans("messages.this_field_is_required"),
				"phone_number_country_code.required"	=> trans("messages.this_field_is_required"),
				"gender.required"                  		=> trans("messages.this_field_is_required"),
				'image.required'						=> trans("messages.this_field_is_required"),
				'image.image'							=> trans("messages.please_upload_an_image"),
				'image.mimes'							=> trans("messages.the_image_must_be_a_file_of_type_jpeg_png_jpg"),
				'image.max'								=> trans("messages.the_image_size_should_not_be_greater_than_2MB"),

				'documents_front.image'					=> trans("messages.please_upload_an_image"),
				'documents_front.mimes'					=> trans("messages.the_image_must_be_a_file_of_type_jpeg_png_jpg"),
				'documents_front.max'					=> trans("messages.the_image_size_should_not_be_greater_than_2MB"),
				'documents_back.image'					=> trans("messages.please_upload_an_image"),
				'documents_back.mimes'					=> trans("messages.the_image_must_be_a_file_of_type_jpeg_png_jpg"),
				'documents_back.max'					=> trans("messages.the_image_size_should_not_be_greater_than_2MB"),
			]);
			if ($validator->fails()) {
				if ($request->wantsJson()) {
					$response			=	$this->change_error_msg_layout($validator->errors()->getMessages());
					$response["status"] = "error";
					$response["msg"] = trans("messages.input_field_is_required");
					return $response;
				}
			}
		
			$user->first_name			    	= ucwords($request->input('first_name'));
			$user->last_name			     	= ucwords($request->input('last_name'));
			$user->name			            	= ucwords($request->input('first_name') .' '. $request->input('last_name'));
			$user->phone_number				 	= $request->input('phone_number');
			$user->phone_number_prefix			= $request->input('phone_number_prefix');
			$user->phone_number_country_code	= $request->input('phone_number_country_code');
			$user->gender                    	= $request->gender;
			if ($request->hasFile('image')) {
				$user->image =	$this->upload($request,'image',Config('constants.USER_IMAGE_ROOT_PATH'));

				// $extension = $request->file('image')->getClientOriginalExtension();
				// $fileName = time() . '-image.' . $extension;
				// $folderName = strtoupper(date('M') . date('Y')) . "/";
				// $folderPath = Config('constants.USER_IMAGE_ROOT_PATH') . $folderName;
				// if (!File::exists($folderPath)) {
				// 	File::makeDirectory($folderPath, $mode = 0777, true);
				// }
				// if ($request->file('image')->move($folderPath, $fileName)) {
				// 	if($user->image){
				// 		File::delete(Config('constants.USER_IMAGE_ROOT_PATH') . $user->image);
				// 	}
				// 	dd($folderName . $fileName);

				// 	$user->image = $folderName . $fileName;
				// }
			}

			
			if ($request->hasFile('documents_front')) {
				$extension = $request->file('documents_front')->getClientOriginalExtension();
				$fileName = time() . '-documents_front.' . $extension;
				$folderName = strtoupper(date('M') . date('Y')) . "/";
				$folderPath = Config('constants.USER_IMAGE_ROOT_PATH') . $folderName;
				if (!File::exists($folderPath)) {
					File::makeDirectory($folderPath, $mode = 0777, true);
				}
				if ($request->file('documents_front')->move($folderPath, $fileName)) {
					if($user->documents_front){
						File::delete(Config('constants.USER_IMAGE_ROOT_PATH') . $user->documents_front);
					}
					$user->documents_front = $folderName . $fileName;
				}
			}

			
			if ($request->hasFile('documents_back')) {
				$extension = $request->file('documents_back')->getClientOriginalExtension();
				$fileName = time() . '-documents_back.' . $extension;
				$folderName = strtoupper(date('M') . date('Y')) . "/";
				$folderPath = Config('constants.USER_IMAGE_ROOT_PATH') . $folderName;
				if (!File::exists($folderPath)) {
					File::makeDirectory($folderPath, $mode = 0777, true);
				}
				if ($request->file('documents_back')->move($folderPath, $fileName)) {
					if($user->documents_back){
						File::delete(Config('constants.USER_IMAGE_ROOT_PATH') . $user->documents_back);
					}
					$user->documents_back = $folderName . $fileName;
				}
			}
            
			$SavedResponse = $user->save();
			if ($SavedResponse) {
				if($request->wantsJson()){
	                 $response["status"] = "success";
					 $response["msg"]   = trans("messages.personal_details_has_been_updated_successfully");
					 $response["userDetails"]   = $user;
					 return $response;
				}
			} else {
				if($request->wantsJson()){
	                 $response["status"] = "error";
					 $response["msg"]   = trans("messages.invalid_request");
					 return $response;
				}
			}
		}
	}
	public function notificationSetting(Request $request)
	{
		if ($request->wantsJson()) {
			$user = Auth::guard('api')->user();
		}
	}
	public function updateSetting(Request $request)
	{
		$notificationName = $request->input('notification');
		$value = $request->input('value');
		if($request->wantsJson()){
			$information = Auth::guard('api')->user();
		}
		if ($notificationName === 'push_notification') {
			$information->push_notification = $value;
		} elseif ($notificationName === 'email_notification') {
			$information->email_notification = $value;
		} elseif ($notificationName === 'chat_notification') {
			$information->chat_notification = $value;
		}

		$information->save();

        if ($request->wantsJson()) {
			$response["status"] = "success";
			$response["msg"]    = trans("messages.notification_setting_updated_successfully");
			return $response;
		}
		return response()->json(['message' => trans("messages.notification_setting_updated_successfully")]);
	}
	public function accountSettings(Request $request)
	{

		if ($request->isMethod('POST')) {
			$validator = Validator::make(	
				$request->all(),
				[
					'old_password'          => 'required',
					'password'          	=> ['required', 'min:8'],
					'confirm_password'  	=> 'required|same:password',
				],
				[
					"old_password.required"      => trans("messages.this_field_is_required"),
					"password.required"          => trans("messages.this_field_is_required"),
					"password.min"               => trans("messages.password_validation_string"),
					"confirm_password.required"  => trans("messages.this_field_is_required"),
					"confirm_password.same"      => trans("messages.the_confirm_password_not_matched"),
				]
			);
			if ($validator->fails()) {
				if ($request->wantsJson()) {
					$response			=	$this->change_error_msg_layout($validator->errors()->getMessages());
					$response["status"] = "error";
					$response["msg"] = trans("messages.input_field_is_required");
					return $response;
				}
			}

            if($request->wantsJson()){
				$information = User::find(Auth::guard('api')->user()->id);
            }
			$oldpassword = $request->old_password;
			if (Hash::check($oldpassword, $information->getAuthPassword())) {
				$information->password = Hash::make($request->password);
				$information->save();

                if($request->wantsJson()){
					$response["status"] = "success";
					$response["msg"] = trans("messages.your_password_has_been_updated_successfully");
					return $response;
				}
			} else {
				if($request->wantsJson()){
					$response["status"] = "error";
					$response["msg"] = trans("messages.your_old_password_is_incorrect");
					return $response;
				}
			}
		}
	}
	public function genrateAgoraToken($booking_id, $uid='')
    {
        $appID = "5546e3bb987b49e08c44618be5d1a095";
        $appCertificate = "62dabb248869451d989ab8136b029331";
        $channelName = $booking_id;
        $ts = 1111111;
        $salt = 1;
        $uid = $uid ?? 1000000001;
        $role = RtcTokenBuilder::RolePublisher;
        $expireTimeInSeconds = 3600;
        $currentTimestamp = (new DateTime("now", new DateTimeZone('UTC')))->getTimestamp();
        $privilegeExpiredTs = time()+3600;
        $expected = "006970CA35de60c44645bbae8a215061b33IACV0fZUBw+72cVoL9eyGGh3Q6Poi8bgjwVLnyKSJyOXR7dIfRBXoFHlEAABAAAAR/QQAAEAAQCvKDdW";

        return $token = RtcTokenBuilder::buildTokenWithUid($appID, $appCertificate, $channelName,$uid, $role, $privilegeExpiredTs);
    }
	function blockMinutesRound($hour, $minutes = '5', $format = "H:i")
    {
        $seconds = strtotime($hour);
        $rounded = round($seconds / ($minutes * 60)) * ($minutes * 60);
        return date($format, $rounded);
    }
	public function userDestroy(Request $request){
		$response    =    array();
		$user = Auth::guard('api')->user();
		Auth::guard('api')->user()->tokens()->delete();
		$user_id =  $user->id;
		$userDetails   		=   User::where("id",$user_id)->first();
        $email              =   'delete_' . $user_id . '_' .!empty($userDetails->email);
        $phone_number       =   'delete_' . $user_id . '_' .!empty($userDetails->phone_number);
        User::where('id', $user_id)->update(array(
            'is_deleted'    => 1, 
            'email'         => $email, 
            'phone_number'  => $phone_number,
        ));
        $response["status"]        =    "success";
        $response["data"]        =    (object)array();
        return response()->json($response);
	}
	public function termsAndConditions(Request $request)
	{
		$cmsObj = DB::table('cms')->where('slug', 'term-conditions')->first();
		$language_id                       =   $this->current_language_id();
		$cmsDescriptionObj = DB::table('cms_descriptions')->where(['parent_id' => $cmsObj->id,'language_id' => $language_id])->first();
		if($request->wantsJson()){
			$response['status']     = "success";
			$response['data']       = $cmsDescriptionObj;
			return $response;
		}
	}
	public function privacyPolicy(Request $request)
	{
		$cmsObj = DB::table('cms')->where('slug', 'privacy-policy')->first();
		$language_id                       =   $this->current_language_id();
		$cmsDescriptionObj = DB::table('cms_descriptions')->where(['parent_id' => $cmsObj->id, 'language_id' => $language_id])->first();
		if($request->wantsJson()){
			$response['status']     = "success";
			$response['data']       = $cmsDescriptionObj;
			return $response;
		}
	}
	public function socialMediaLinks(Request $request)
	{
		$response['status']     = "success";
		$response["data"]['social_links'] = array();
		if(!empty(config('Social.facebook'))){
			$response["data"]['social_links']['facebook'] = config('Social.facebook');
		}
		if(!empty(config('Social.twitter'))){
			$response["data"]['social_links']['twitter'] = config('Social.twitter');
		}
		if(!empty(config('Social.youtube'))){
			$response["data"]['social_links']['youtube'] = config('Social.youtube');
		}
		if(!empty(config('Social.linkedin'))){
			$response["data"]['social_links']['linkedin'] = config('Social.linkedin');
		}
		if(!empty(config('Social.instagram'))){
			$response["data"]['social_links']['instagram'] = config('Social.instagram');
		}
		return $response;
	}
	public function aboutUs(Request $request)
	{
		$cmsObj = DB::table('cms')->where('slug', 'about-us')->first();
		$language_id                       =   $this->current_language_id();
		$cmsDescriptionObj = DB::table('cms_descriptions')->where(['parent_id' => $cmsObj->id, 'language_id' => $language_id])->first();
		if($request->wantsJson()){
			$response['status']     = "success";
			$response['data']       = $cmsDescriptionObj;
			return $response;
		}
		
	}
	public function shippingPolicy(Request $request)
	{
		$cmsObj = DB::table('cms')->where('slug', 'shipping-policy')->first();
		$language_id                       =   $this->current_language_id();
		$cmsDescriptionObj = DB::table('cms_descriptions')->where(['parent_id' => $cmsObj->id, 'language_id' => $language_id])->first();
		if($request->wantsJson()){
			$response['status']     = "success";
			$response['data']       = $cmsDescriptionObj;
			return $response;
		}
		
	}
	public function refundPolicy(Request $request)
	{
		$cmsObj = DB::table('cms')->where('slug', 'refund-policy')->first();
		if($request->wantsJson()){
			$response['status']     = "success";
			$response['data']       = $cmsObj;
			return $response;
		}
	}
	public function contactUs(Request $request)
	{
		if ($request->isMethod('POST')) {
			$request->replace($this->arrayStripTags($request->all()));
			$formData = $request->all();
			if (!empty($formData)) {
			    if($request->wantsJson()){
					$validator = Validator::make(
						$request->all(),
						array(
							'name' 	               			=> 'required',
							'email' 	               		=> 'required|regex:/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/',
							'phone_number' 			   		=> 'nullable|digits_between:10,20',
							'subject' 	               		=> 'required',
							'note' 	               			=> 'required',
						),
						array(
							"email.regex"              		=> trans("messages.the_email_should_be_a_valid_email"),
							'phone_number.digits_between' 			=> trans('messages.the_phone_number_must_be_10_digits'),
							"name.required"            		=> trans("messages.this_field_is_required"),
							"email.required"           		=> trans("messages.this_field_is_required"),
							"phone_number.required"    		=> trans("messages.this_field_is_required"),
							"subject.required"        		=> trans("messages.this_field_is_required"),
							"note.required"            		=> trans("messages.this_field_is_required"),
						)
					);
				}
				if ($validator->fails()) {
					if ($request->wantsJson()) {
						$response			=	$this->change_error_msg_layout($validator->errors()->getMessages());
						$response["status"] = "error";
						$response["msg"] = trans("messages.input_field_is_required");
						return $response;
					} else {
						$errors = [];
						$msgArr = (array) $validator->messages();
						$msgArr = array_shift($msgArr);
						$count = 0;
						foreach ($msgArr as $key => $val) {
							$errors[$key . "_error"] = array_shift($val);
							$count++;
						}
					}
				} else {
					$language_id                       =   $this->current_language_id();
					$user                              =   new ContactUs;
					$user->name                        =   $request->name;
					$user->mobile_number               =   $request->phone_number ?? '';
					$user->email                       =   $request->email;
					$user->subject                     =   $request->subject;
					$user->message                     =   $request->note;
					$SavedResponse                     =   $user->save();
					if ($user->save()) {
						$settingsEmail = Config("Site.from_email");
						$full_name = $user->name;
						$email = $user->email;
						$emailActions = EmailAction::where('action', '=', 'contact_enquiry')->get()->toArray();
						$emailTemplates 	= EmailTemplate::where('action', '=', 'contact_enquiry')->select("name", "action", "subject", "body")->get()->toArray();
						$cons = explode(',', $emailActions[0]['options']);
						$constants = array();
						foreach ($cons as $key => $val) {
							$constants[] = '{' . $val . '}';
						}
						$subject = $emailTemplates[0]['subject'];
						$rep_Array = array($user->name, $user->email, $user->message, $user->mobile_number, $user->mobile_number);
						$messageBody = str_replace($constants, $rep_Array, $emailTemplates[0]['body']);
						$this->sendMail($email, $full_name, $subject, $messageBody, $settingsEmail);
						$SettingsEmail 		= Config("Site.from_email");
						$emailActions 		= EmailAction::where('action', '=', 'contact_enquiry_admin')->get()->toArray();
						$EmailTemplates 	= EmailTemplate::where('action', '=', 'contact_enquiry_admin')->select("name", "action", "subject", "body")->get()->toArray();

						$cons 				= explode(',', $emailActions[0]['options']);
						$constants_admin 	= array();

						foreach ($cons as $key => $val) {
							$constants_admin[] = '{' . $val . '}';
						}
						$Subject 			= $EmailTemplates[0]['subject'];
						$admin_email 		= Config("Contact.admin_email");
						$Rep_Array 			= array($user->name, $user->email, $user->message, $user->mobile_number, $user->mobile_number);
						$MessageBody 		= str_replace($constants_admin, $Rep_Array, $EmailTemplates[0]['body']);
						$this->sendMail($admin_email, $full_name, $Subject, $MessageBody, $SettingsEmail);

						if ($request->wantsJson()) {
							$response["status"]	 		= "success";
							$response["msg"]			= trans("messages.enquiry_sent_successfully");
							return $response;
						}
					}
				}
			}
		}
	}
	public function introScreen(Request $request){
		$MobileIntroScreen = MobileIntroScreen::with(['MobileIntroScreenDiscription' => function ($query){
			$query->where('language_id', $this->current_language_id());
		}])
        ->orderBy("row_order","asc")
        ->where(['is_active'=> 1])->get();

		foreach ($MobileIntroScreen as $introScreen) {
			if (isset($introScreen->MobileIntroScreenDiscription)) {
				$introScreen->MobileIntroScreenDiscription->description = html_entity_decode($introScreen->MobileIntroScreenDiscription->description);
			}
		}
		if($request->wantsJson()){
			$response['status']     = "success";
			$response['data']       = $MobileIntroScreen;
			return $response;
		}
	}
	public function updateLanguage(Request $request){
		$response    =    array();
		$user = Auth::guard('api')->user();
		$language_id                       =   $this->current_language_id();
		$userObj = User::find($user->id);
		$userObj->language = $request->language_id;
		$userObj->save();
		//->update(['language'=>$language_id]);
        $response["status"]	=    "success";
		$response["msg"]	= trans("messages.language_changed_successfully");
        $response["data"]  	=    (object)array();
        return response()->json($response);
	}
	
	public function logout(Request $request)
	{
		if($request->wantsJson()){
			Auth::guard('api')->user()->tokens()->delete();
			$response["status"] = "success";
			$response["msg"]   = trans('messages.you_are_logged_out');
			return $response; 
		}
	}
	
	public function masters(Request $request)
	{
		if($request->wantsJson()){
			$lookupObj = Lookup::where(['is_active'=>1])->with(['LookupDiscription' => function ($query){
				$query->where('language_id', $this->current_language_id());
			}])->get();
			$master = array();
			foreach($lookupObj as $lookup){
				if(!isset($master[$lookup->lookup_type])){
					$master[$lookup->lookup_type] = array();
				}
				$count = count($master[$lookup->lookup_type]);
				$master[$lookup->lookup_type][$count]['value'] = $lookup->id;
				$master[$lookup->lookup_type][$count]['label'] = $lookup->LookupDiscription->code ?? null;
			}
			$response["status"] = "success";
			$response["data"] = $master;
			return $response; 
		}
	}
	public function faqs(Request $request)
	{
		$cmsObj = Faq::where('is_active', 1)
        ->orderBy('faq_order','asc')->with(['faqDiscription' => function ($query){
			$query->where('language_id', $this->current_language_id());
		}])->get();
		if($request->wantsJson()){
			$response['status']     = "success";
			$response['data']       = $cmsObj;
			return $response;
		}
	}

	public function categoryList(Request $request, $id=null){
	
		if($id != null){
			$Categories   = Category::where('categories.parent_id', $id)->where(['categories.is_active' => 1, 'categories.is_deleted' => 0])
			->with(['categoryDescription' => function($query){
			 $query->where('language_id', $this->current_language_id()); 
			}])->get(); 	
		}else{	
			$c_ids        = Product::where('user_id',$request->user_id)->where('is_active',1)->where('is_approved','1')->where('is_deleted',0)->pluck('parent_category');
             //->whereIn('id',$c_ids)
			$Categories   = Category::where('parent_id', NULL)->where(['categories.is_active' => 1, 'categories.is_deleted' => 0])
			->with(['categoryDescription' => function($query){
				$query->where('language_id', $this->current_language_id()); 
			}])->get(); 
			$allName = $this->current_language_id() == 1 ? 'All' : 'Tüm';
			$allCategory = (object)[
				'id' => 0,
				'parent_id' => null,
				'is_active' => 1,
				'is_deleted' => 0,
				'category_description' => [
					(object)[
						'language_id' => $this->current_language_id(),
						'name' => $allName,
					],
				],
			];
		
			$Categories->prepend($allCategory);
		}
						

		if($request->wantsJson()){
			$response               		=  array();
			$response['status']     		= 'success';
			$response['data']['category']   = $Categories;
			return $response;
		}
	}

	public function categoryColorsList(Request $request, $id){
		$colorList = CategoryColor::with(['Colors',
			'Colors.ColorsDescription' => function($query){
			$query->where('language_id', $this->current_language_id()); 
		   }])->where("category_id",$id)->get();
		   $colorListArry = array();
		foreach($colorList as $color){
			$colorListArry[] = array(
				'id' => $color->Colors->id,
				'color_code' => $color->Colors->color_code,
				'name' => $color->Colors->ColorsDescription->name
			);
		}
		if($request->wantsJson()){
			$response               		=  array();
			$response['status']     		= 'success';
			$response['data']['colorList']   = $colorListArry;
			return $response;
		}
	}

	public function productAction(Request $request){

		if($request->wantsJson()){
			$user 		 = Auth::guard('api')->user();
			$language_id = $this->current_language_id();
		}else{
			$user        = Auth::user();
			$language_id = $this->current_language_id();
		}

		$checkProductAction = ProductAction::where(['user_id' => $user->id, 'product_id' => $request->product_id, 'action_type' => $request->action_type])->first();
		if($checkProductAction){

			$checkProductAction->delete();
			$messages = '';
			if($request->action_type == 'favorite'){
				$messages = trans('messages.The_product_has_removed_from_your_favorite');
			}elseif($request->action_type == 'save'){
				$messages = trans('messages.The_product_has_removed_from_your_save_list');
			}elseif($request->action_type == 'like'){
				$messages = trans('messages.The_product_has_removed_from_your_like_list');
			}

			if($request->wantsJson()){
				$response               		= array();
				$response['status']     		= 'success';
				$response['msg']                = $messages;
				return $response;
			}
	
			session()->flash('success', $messages);
			return redirect()->back();

		}

		$productWishlist  			    = new ProductAction;
		$productWishlist->user_id       = $user->id;
		$productWishlist->product_id    = $request->product_id;
		$productWishlist->action_type   = $request->action_type;
		$productWishlist->save();

		$message = '';
		if($request->action_type == 'favorite'){
			$userDetail  = User::find(Product::find($request->product_id)->user_id);
            $userDetailToken =UserDeviceToken::where('user_id',$userDetail->id)->orderBy('id', 'desc')->first();
            if ($userDetail->language == 2) {
                $order_des = Auth::guard('api')->user()->name .'favorilere ekle';
                $msg_title =  'favorilere ekle';
            } else {
                $order_des = Auth::guard('api')->user()->name .'add to favorite';
                $msg_title = 'Add to favorite';
            }       
            $data=[
               'productId'         => $request->product_id,
			   'userId'            => $user->id,
			   'productWishlistId' => $productWishlist,
            ];
            if (!empty($userDetailToken->device_token)) {
                $this->send_push_notification(
                    $userDetailToken->device_token,
                    $userDetailToken->device_type,
                    $order_des,
                    $msg_title,
                    'add_to_favorite',
                    $data
                );
                $notification = new Notification;
                $notification->user_id   = $userDetail->id;
                $notification->action_user_id = Auth::guard('api')->user()->id;
                $notification->description_en = Auth::guard('api')->user()->name .'add to favorite';
                $notification->title_en  = 'Add to favorite';
                $notification->title_tur = 'favorilere ekle';
                $notification->description_tur = Auth::guard('api')->user()->name .'favorilere ekle';
                $notification->type           = "add_to_favorite";
                $notification->save();
			$message = trans('messages.Product_has_been_added_to_your_favorite_successfully');
		}elseif($request->action_type == 'save'){
			$message = trans('messages.Product_has_been_added_to_your_save_list_successfully');
		}elseif($request->action_type == 'like'){
			$userDetail  = User::find(Product::find($request->product_id)->user_id);
            $userDetailToken =UserDeviceToken::where('user_id',$userDetail->id)->orderBy('id', 'desc')->first();
            if ($userDetail->language == 2) {
                $order_des = Auth::guard('api')->user()->name .'ürününüzü beğendim';
                $msg_title =  'Yeni Beğenilenler Alındı';
            } else {
                $order_des = Auth::guard('api')->user()->name .'liked your product';
                $msg_title = 'New Liked Received';
            }       
            $data=[
				'productId'         => $request->product_id,
				'userId'            => $user->id,
				'productWishlistId' => $productWishlist,            ];
            if (!empty($userDetailToken->device_token)) {
                $this->send_push_notification(
                    $userDetailToken->device_token,
                    $userDetailToken->device_type,
                    $order_des,
                    $msg_title,
                    'new_liked_recevied',
                    $data
                );
                $notification = new Notification;
                $notification->user_id   = $userDetail->id;
                $notification->action_user_id = Auth::guard('api')->user()->id;
                $notification->description_en = Auth::guard('api')->user()->name .'liked your product';
                $notification->title_en  = 'New Liked Received';
                $notification->title_tur = 'Yeni Beğenilenler Alındı';
                $notification->description_tur = Auth::guard('api')->user()->name .'ürününüzü beğendim';
                $notification->type           = "new_liked_recevied";
                $notification->save();
			$message = trans('messages.Product_has_been_added_to_your_like_list_successfully');
		}
	}
}
		if($request->wantsJson()){
			$response               		=  array();
			$response['status']     		= 'success';
			$response['msg']                = $message;
			return $response;
		}

		session()->flash('success', $message);
		return redirect()->back();
	}

	public function getProducts(Request $request){

		if($request->wantsJson()){
			$user 		 = Auth::guard('api')->user();
			$language_id = $this->current_language_id();
		}else{
			$user        = Auth::user();
			$language_id = $this->current_language_id();
		}

        $DB					=	Product::query();
		
		$DB->with([
			'userDetails',

			'parentCategoryDetails',
			'parentCategoryDetails.categoryDescription' => function($query){
				$query->where('language_id', $this->current_language_id()); 
			},

			'subCategoryDetails',
			'subCategoryDetails.categoryDescription' => function($query){
				$query->where('language_id', $this->current_language_id()); 
			},
			
			'prodcutColorDetails',
			'prodcutColorDetails.colorDetails',
			'prodcutColorDetails.colorDetails.ColorsDescription',
		]);
		$DB->where('is_deleted',0);
		$allBlockUsersId = BlockUser::where('user_id',$user->id)->pluck('block_user_id');
		$DB->whereNotIn('user_id',$allBlockUsersId);
		if($request->type == null){//All product for video sare products without login user id
			$DB->where('is_active', 1)
			->where('is_approved', '1')
			->where("user_id","!=",$user->id)
			->where("is_deleted",0);
		}else if($request->type == 'product_detail'){
			$DB->where('id', $request->product_id);
		}else if($request->type == 'my_product'){//All My products(by product id) sare products with open video id
			$productDetails = Product::find($request->product_id);
			$DB->orderByRaw("CASE WHEN id = ".$request->product_id." THEN 0 ELSE 1 END, ".(($request->input('sortBy')) ? $request->input('sortBy') : 'products.id')." ".(($request->input('order')) ? $request->input('order') : 'DESC'));
			$DB->where('user_id',$productDetails->user_id);
			if($productDetails->user_id != $user->id){
				$DB->where('is_active',1)->where('is_approved','1');
			}
		}else if($request->type == 'other_user_product'){//All My products(by product id) sare products with open video id
			$DB->where('user_id',$request->user_id);
			if($request->user_id && $user->id != $request->user_id ){
				$DB->where('is_approved','1');
			}
		}else if($request->type == 'saved_products'){ //All My saved products with login user id
			$saveProductIds  = ProductAction::where(['user_id' => $user->id, 'action_type' => 'save'])->pluck('product_id')->toArray();
			$DB->whereIn('id', $saveProductIds);
			$userId = $user->id;
			$DB->when($userId, function($query, $userId) {
				$query->where(function($query) use ($userId) {
					$query->where('user_id', '!=', $userId)
						  ->where(['is_active' => 1, 'is_approved' => '1']);
				});
			});
		}else if($request->type == 'fav_products'){//All My fav products with login user id
			$favProductIds   = ProductAction::where(['user_id' => $user->id, 'action_type' => 'favorite'])->pluck('product_id')->toArray();
			$DB->whereIn('id', $favProductIds);
			$userId = $user->id;
			$DB->when($userId, function($query, $userId) {
				$query->where(function($query) use ($userId) {
					$query->where('user_id', '!=', $userId)
						  ->where(['is_active' => 1, 'is_approved' => '1']);
				});
			});
		}else if($request->type == 'liked_products'){//All My liked products with login user id
			$likeProductIds  = ProductAction::where(['user_id' => $user->id, 'action_type' => 'like'])->pluck('product_id')->toArray();
			$DB->whereIn('id', $likeProductIds);
			$userId = $user->id;
			$DB->when($userId, function($query, $userId) {
				$query->where(function($query) use ($userId) {
					$query->where('user_id', '!=', $userId)
						  ->where(['is_active' => 1, 'is_approved' => '1']);
				});
			});
		}else if($request->type == 'categories'){//All My products with category filter  with login user id
			$DB->where('parent_category',$request->category_id);
			if($request->subcategory_id){
				$DB->where('category_level_2',$request->subcategory_id);
			}
			$userId = $user->id;
			if($request->user_id){
				$userId = $request->user_id;
			}
			if($request->user_id && $user->id != $request->user_id ){
				$DB->where('is_approved','1');
			}
			$DB->where('user_id',$userId);

		}

		if($request->type != 'my_product'){
			$sortBy = ($request->input('sortBy')) ? $request->input('sortBy') : 'products.id';
			$order  = ($request->input('order')) ? $request->input('order')   : 'DESC';
			$DB->orderBy($sortBy, $order);
		}
		$records_per_page	=	($request->input('per_page')) ? $request->input('per_page') : Config::get("Reading.records_per_page");
		$results = $DB->paginate($records_per_page);

		if(!$results->isEmpty()){
            foreach($results as &$item){
                $item->created_date = date(config("Reading.date_format"),strtotime($item->created_at));
				if($item->prodcutColorDetails->count()){
					foreach($item->prodcutColorDetails as &$prodcutColorDetail){
						if(!empty($prodcutColorDetail->video)){
							$prodcutColorDetail->video_thumbnail = "https://". env("CDN_HOSTNAME"). "/" . $prodcutColorDetail->video . "/thumbnail.jpg"; 
							$prodcutColorDetail->video =  "https://". env("CDN_HOSTNAME"). "/" . $prodcutColorDetail->video . "/playlist.m3u8";
							if($item->video_thumbnail == null && $item->video == null  ){
								$item->video_thumbnail = $prodcutColorDetail->video_thumbnail;
								$item->video =  $prodcutColorDetail->video;
							}
						}else{
							$prodcutColorDetail->video_thumbnail = ""; 
							$prodcutColorDetail->video =  "";
						}
						// $prodcutColorDetail->video_thumbnail = asset('/storage/uploads/product_video/thumbnail/') . '/' . $prodcutColorDetail->video_thumbnail;
						// $prodcutColorDetail->video = asset('/storage/uploads/product_video/') . '/' .  $prodcutColorDetail->video;
						$prodcutColorDetail->product_variant = ProductVariant::where([
							'product_id'=>$prodcutColorDetail->product_id,
							'color_id'=>$prodcutColorDetail->color_id,
						])->with(['sizeDetails','sizeDetails.SizesDescription'])->get();
					}
				}
				$avgRatingReview = ReviewRating::where('product_id', $item->id)->avg('rating');
				$avgRatingReview = $avgRatingReview == 0 ? '0' : number_format($avgRatingReview, 1);							
				$ratingReviewArray = ReviewRating::where('product_id', $item->id)->get()->map(function($ratingReview) {  
				return array_merge($ratingReview->toArray(),['userName' => $ratingReview->user->name,'userImage' => $ratingReview->user->image,'created_at' => Carbon::parse($ratingReview->created_at)->diffForHumans(),]);});
				$ratingReviewCount = ReviewRating::where('product_id', $item->id)->count();
				$formattedRatingReviewCount = formatCount($ratingReviewCount);
				if (strpos($formattedRatingReviewCount, 'k') !== false) {
					$numericCount = floatval(str_replace('k', '', $formattedRatingReviewCount));
					if ($numericCount <= 1) {
						$formattedRatingReviewCount = $formattedRatingReviewCount;
					} else {
						$formattedRatingReviewCount = $formattedRatingReviewCount ;
					}
				} else {
					if ($formattedRatingReviewCount <= 1) {
						$formattedRatingReviewCount = $formattedRatingReviewCount;
					} else {
						$formattedRatingReviewCount = $formattedRatingReviewCount ;
					}
				}
				$item->RatingReviewArray = [
					'AvgRatingReview' => $avgRatingReview,
					'RatingReviewList' => $ratingReviewArray,
					'formattedRatingReviewCount' => $formattedRatingReviewCount,
				];
				$item->is_favorite = ProductAction::where(['product_id'=>$item->id,'action_type'=>'favorite','user_id'=>$user->id])->count();
				
				$item->total_rating = 0;
				$item->total_likes = ProductAction::where(['product_id'=>$item->id,'action_type'=>'like'])->count();
				$item->is_liked = ProductAction::where(['product_id'=>$item->id,'action_type'=>'like','user_id'=>$user->id])->count();
				$item->url                                = route('share-url-product', ['id' => base64_encode($item->id)]);
                $item->url_text                           = $item->name;
                $item->url_with_text                      = $item->name . ' ' . $item->url;
				$item->total_commentes = 0;
				
				$item->total_share = 0;
	
				$item->total_save = ProductAction::where(['product_id'=>$item->id,'action_type'=>'save'])->count();
				$item->is_saved = ProductAction::where(['product_id'=>$item->id,'action_type'=>'save','user_id'=>$user->id])->count();
				
				$item->total_ask = 0;
				$item->user_profile = 0;
				$item->userDetails->is_follow = Follow::where('user_id', Auth::guard('api')->user()->id)
				->where('member_user_id', $item->userDetails->id)
				->where('is_follow', 1)
				->where('type', 'follow')
				->exists() ? 1 : 0;
						}
        }
		
		$response               		=  array();
		$response['status']     		= 'success';
		$response['data']['ProductList']        = $results;
		return $response;
	}

	public function addProduct(Request $request){

		if ($request->isMethod("POST")) {
			$formData	=	$request->all();
			if (!empty($formData)) {
					$validator = Validator::make(
						$request->all(),
						array(
							'name' 			   					=> 'required',
							'description' 			   			=> 'required',
							'parent_category' 			   		=> 'required',
							'category_level_2' 			   		=> 'required',
							'variant_video.*'					=> ['required','mimes:mp4,mov,avi,mkv,wmv,flv,webm,3gp,ogg'/* , new MaxVideoDuration(60) */]
						),
						array(
							"name.required"             		=> trans("messages.this_field_is_required"),
							"description.required"             	=> trans("messages.this_field_is_required"),
							"parent_category.required"          => trans("messages.this_field_is_required"),
							"category_level_2.required"         => trans("messages.this_field_is_required"),
							"variant.*.mimes"         	        => trans("messages.the_file_must_be_a_video"),
						),
					);
					if ($validator->fails()) {
						if ($request->wantsJson()) {
							$response			=	$this->change_error_msg_layout($validator->errors()->getMessages());
							$response["status"] = "error";
							$response["msg"] = trans("messages.input_field_is_required");
							return $response;
						}
					} else {
					$user = Auth::guard('api')->user();

					$product                                    = new Product;
					$product->user_id                   		= $user->id;
					$product->name    	                        = $request->name;
					$product->description    	                = $request->description;
					$product->parent_category                   = $request->parent_category;
					$product->is_active                   		= 1;
					$product->is_approved                   	= '0';
					$product->is_deleted                   		= 0;
					$product->category_level_2                  = $request->category_level_2;
					
					$product->save(); 
					$productId								= $product->id;
	
					if(!$productId){
						Session()->flash('error', trans("Something went wrong.")); 
						return Redirect()->back()->withInput();
					}
					$sizeIds_arr = [];
					if(isset($formData['variant'])){
						foreach($formData['variant'] as $colorId => $variant){
							foreach($variant as $key => $value){
								if(isset($value['price'])){
									$productVariant                  = new ProductVariant;
									$productVariant->product_id      = $productId;
									$productVariant->color_id        = $colorId;
									$productVariant->size_id         = $value['size_id'];
									$productVariant->price           = $value['price'];
									$productVariant->stock_qty       = $value['stock_qty'];
									$productVariant->save();
									if(!in_array($value['size_id'], $sizeIds_arr)){
										$sizeIds_arr[] = $value['size_id'];
									}
								}
							}
							$ProductColor              		= new ProductColor; 
							$ProductColor->product_id  		= $productId;
							$ProductColor->color_id    		= $colorId;
							if ($request->File('variant_video.'.$colorId)) {
								$name = 'variant_video'.$colorId . time();
								$uploadedResponse = $this->uploadVideoOnCDN($request->File('variant_video.'.$colorId), $name);
								if (!empty($uploadedResponse) && $uploadedResponse['status'] == 'success') {
									$ProductColor->video = $uploadedResponse['guid'];
								}
								// $ProductColorvideo                	= $this->upload($request, 'variant_video.'.$colorId, '/uploads/product_video/');
								// $ProductColor->video                = $ProductColorvideo;
								// $ProductColor->video_thumbnail		= time() . '-video-thumbnail.jpg';
								// generateThumbnail('storage/uploads/product_video/'.$ProductColorvideo, 'storage/uploads/product_video/thumbnail/'.$ProductColor->video_thumbnail );
							}
							$ProductColor->save();
						}
					}
					if(count($sizeIds_arr)){
						foreach($sizeIds_arr as $sizeId){
							 $ProductSize              		= new ProductSize; 
							 $ProductSize->product_id  		= $productId;
							 $ProductSize->size_id    		= $sizeId;
							 $ProductSize->save();
						}
					}
					$response               		=  array();
					$response['status']     		= 'success';
					$response['msg']		        = trans('messages.the_product_has_been_added_please_wait_for_approval');
					$response['data']     			= [];
			
					return $response;
				}
			}
		}
		$c_ids        = Product::where('user_id',Auth::guard('api')->user()->id)->pluck('parent_category');
		//->whereIn('id',$c_ids)
		$categories   = Category::where('categories.parent_id', null)->where(['categories.is_active' => 1, 'categories.is_deleted' => 0])
			->with(['categoryDescription' => function($query){
			 $query->where('language_id', $this->current_language_id()); 
			}])->get(); 

		$sizes   = Size::where(['is_active' => 1, 'is_deleted' => 0])
			->with(['SizesDescription' => function($query){
			 $query->where('language_id', $this->current_language_id()); 
			}])->get(); 
		$response               		=  array();
		$response['status']     		= 'success';
		$response['data']     			= array(
			'categories'	=> $categories,
			'sizes'			=> $sizes
		);

		return $response;

	}

	public function getProfile(Request $request) 
	{
		if ($request->wantsJson()) {
			$whereArr = array();
			if($request->user_id){
				$user = User::where([
					'id'			=>$request->user_id,
					'user_role_id'	=> Config('constants.ROLE_ID.CUSTOMER_ROLE_ID'),
					'is_active'		=>1,
					'is_deleted'	=>0,
				])->first();
				$whereArr = [
					'user_id' => $user->id,
					'is_approved' => '1',
					'is_deleted' => 0,
				];
			
			}else{
				$user = Auth::guard('api')->user();
				$whereArr = [
					'user_id' => $user->id,
					'is_deleted' => 0,
				];
			}
			if($user == null){
				$response["status"] = "error";
				$response["msg"] = trans("messages.account_not_found");
				return $response;				
			}
			$user->documents_front  = $user->documents_front ?  Config('constants.USER_IMAGE_PATH').$user->documents_front : null ;
			$user->documents_back   = $user->documents_back ?  Config('constants.USER_IMAGE_PATH').$user->documents_back : null ;
			//$user->image 			= $user->image;
			$userId  = $user->id;
			$user->total_followers	= Follow::where('member_user_id',Auth::guard('api')->user()->id)->where('type','follow')->where('is_follow',1)->count(); 
			$user->total_following 	= Follow::where('user_id',Auth::guard('api')->user()->id)->where('type','follow')->where('is_follow',1)->count();
			$followerList           = Follow::where('member_user_id',Auth::guard('api')->user()->id)->where('type','follow')->where('is_follow',1)->with('user','userfollowing')->get()->map(function ($follow) use ($userId) {
				$isFollowBack = Follow::where('member_user_id', $follow->user->id ?? null)->where('type', 'follow')->where('is_follow', 1)->where('member_user_id', $userId)->exists();
				$isFollow     = Follow::where('user_id', $userId ?? null)->where('type', 'follow')->where('is_follow', 1)->where('member_user_id', $follow->userfollowing->id)->exists();
				return [
					'userId'       => $follow->user->id ?? null,
					'name'         => $follow->user->name ?? null,
					'image'        => $follow->user->image ?? null,
					'is_follow'    => $isFollow ? 1 : 0,
					'is_unfollow'  => $follow->is_follow ? 0 : 1,
					'is_follow_back' => $isFollowBack ? 1 : 0,
				];
			});
			    $followingList= Follow::where('user_id',Auth::guard('api')->user()->id)->where('type','follow')->where('is_follow',1)->with('user','userfollowing')->get()->map(function ($follow) use ($userId) {
				$isFollowBack = Follow::where('user_id', $follow->userfollowing->id ?? null)->where('type', 'follow')->where('is_follow', 1)->where('member_user_id', $userId)->exists();
				$isFollow     = Follow::where('user_id', $userId ?? null)->where('type', 'follow')->where('is_follow', 1)->where('member_user_id', $follow->userfollowing->id)->exists();
				return [
					'userId'         => $follow->userfollowing->id ?? null,
					'name'           => $follow->userfollowing->name ?? null,
					'image'          => $follow->userfollowing->image ?? null,
					'is_follow'      => $isFollow ? 1 : 0,
					'is_unfollow'    => $follow->is_follow ? 0 : 1,
					'is_follow_back' => $isFollowBack ? 1 : 0,
				];
			});
			$user->total_videos 	= Product::where('user_id',Auth::guard('api')->user()->id)->where('is_active',1)->where('is_deleted',0)->count();
			$userProductCategories  = Product::where($whereArr)
			->groupBy('parent_category')
			->pluck('parent_category')
			
			->toArray();
			if(count($userProductCategories)>0){
				// $user->categories  		=  ;
				$user->categories = Category::whereIn('id',$userProductCategories)
				->where([
					"is_active" => 1,
					"is_deleted" => 0
				])->with(['categoryDescription' => function($query){
					$query->where('language_id', $this->current_language_id()); 
				}])->get();
				if($user->categories->count()){
					foreach($user->categories as &$category){
						$userProductSubCategories = Product::where([
							'user_id'		 	=> $user->id,
							'is_approved' 		=> '1',
							'parent_category'	=> $category->id
						])
						->groupBy('category_level_2')
						->pluck('category_level_2')->toArray();
						$category->subCategories = Category::whereIn('id',$userProductSubCategories)
						->where([
							"is_active" => 1,
							"is_deleted" => 0
						])->with(['categoryDescription' => function($query){
							$query->where('language_id', $this->current_language_id()); 
						}])->get();
					}
					
				}

			}
			
			$response["data"]['userDetails'] = $user;
			$response["data"]["followerList"] = $followerList;
			$response["data"]["followingList"] = $followingList;
			$response["status"] = "success";
			return $response;
		}
	}
	
	public function productDetails(Request $request,$id){

		if($request->wantsJson()){
			$user 		 = Auth::guard('api')->user();
			$language_id = $this->current_language_id();
		}else{
			$user        = Auth::user();
			$language_id = $this->current_language_id();
		}

        $DB					=	Product::query();
		
		$DB->with([
			'parentCategoryDetails',
			'parentCategoryDetails.categoryDescription' => function($query){
				$query->where('language_id', $this->current_language_id()); 
			},

			'subCategoryDetails',
			'subCategoryDetails.categoryDescription' => function($query){
				$query->where('language_id', $this->current_language_id()); 
			},
			
			'prodcutColorDetails',
			'prodcutColorDetails.colorDetails',
			'prodcutColorDetails.colorDetails.ColorsDescription',
		]);
		
		
		$DB->where(['is_deleted' => 0]);
		$DB->where("id",$id);
		$DB->where("user_id",$user->id);
		
		$item = $DB->first();
		if($item != null){
                $item->created_date = date(config("Reading.date_format"),strtotime($item->created_at));
				if($item->prodcutColorDetails->count()){
					foreach($item->prodcutColorDetails as &$prodcutColorDetail){
						// $prodcutColorDetail->video_thumbnail = asset('/storage/uploads/product_video/thumbnail/') . '/' . $prodcutColorDetail->video_thumbnail;
						// $prodcutColorDetail->video = asset('/storage/uploads/product_video/') . '/' .  $prodcutColorDetail->video;
						if(!empty($prodcutColorDetail->video)){
							$prodcutColorDetail->video_thumbnail = "https://". env("CDN_HOSTNAME"). "/" . $prodcutColorDetail->video . "/thumbnail.jpg"; 
							$prodcutColorDetail->video =  "https://". env("CDN_HOSTNAME"). "/" . $prodcutColorDetail->video . "/playlist.m3u8";
							
							if($item->video_thumbnail == null && $item->video == null  ){
								$item->video_thumbnail = $prodcutColorDetail->video_thumbnail ;
								$item->video =  $prodcutColorDetail->video ;
							}
						}else{
							$prodcutColorDetail->video_thumbnail = ""; 
							$prodcutColorDetail->video =  "";
						}
						
						
						$prodcutColorDetail->product_variant = ProductVariant::where([
							'product_id'=>$prodcutColorDetail->product_id,
							'color_id'=>$prodcutColorDetail->color_id,
						])->with(['sizeDetails','sizeDetails.SizesDescription'])->get();
					}
				}
				$item->is_favorite = ProductAction::where(['product_id'=>$item->id,'action_type'=>'favorite','user_id'=>$user->id])->count();
				
				$item->total_rating = 0;
				$item->total_likes = ProductAction::where(['product_id'=>$item->id,'action_type'=>'like'])->count();
				$item->is_liked = ProductAction::where(['product_id'=>$item->id,'action_type'=>'like','user_id'=>$user->id])->count();
				
				$item->total_commentes = 0;
				
				$item->total_share = 0;
	
				$item->total_save = ProductAction::where(['product_id'=>$item->id,'action_type'=>'save'])->count();
				$item->is_saved = ProductAction::where(['product_id'=>$item->id,'action_type'=>'save','user_id'=>$user->id])->count();
				
				$item->total_ask = 0;
				$item->user_profile = 0;
				$item->userDetails->image = $item->userDetails->image;
		
				$response               		=  array();
				$response['status']     		= 'success';
				$response['data']['product_details']        = $item;
				return $response;
            }else{
				if($request->wantsJson()){
					$response               		=  array();
					$response['status']     		= 'error';
					$response['msg']                = trans("messages.product_not_found");
					return $response;
				}
			}
		

	}

	public function updateProduct(Request $request,$id){
		$formData	=	$request->all();
        $product = Product::where(['is_deleted' => 0])
			->where("id",$id)
			->where("user_id",Auth::guard('api')->user()->id)
			->first();
		if($product == null){
			$response               		=  array();
			$response['status']     		= 'error';
			$response['msg']                = trans("messages.product_not_found");
			return $response;
		}
		
		$validator = Validator::make(
			$request->all(),
			array(
				'name' 			   					=> 'required',
				'description' 			   			=> 'required',
				'parent_category' 			   		=> 'required',
				'category_level_2' 			   		=> 'required',
				'variant_video.*'					=> ['nullable','mimes:mp4,mov,avi,mkv,wmv,flv,webm,3gp,ogg'/* , new MaxVideoDuration(30) */]
			),
			array(
				"name.required"             		=> trans("messages.this_field_is_required"),
				"description.required"             	=> trans("messages.this_field_is_required"),
				"parent_category.required"          => trans("messages.this_field_is_required"),
				"category_level_2.required"         => trans("messages.this_field_is_required"),
				"variant.*.mimes"         	=> trans("messages.the_file_must_be_a_video"),
			),
		);
		if ($validator->fails()) {
			if ($request->wantsJson()) {
				$response			=	$this->change_error_msg_layout($validator->errors()->getMessages());
				$response["status"] = "error";
				$response["msg"] = trans("messages.input_field_is_required");
				return $response;
			}
		} else {
			$product->name    	                        = $request->name;
			$product->description    	                = $request->description;
			$product->parent_category                   = $request->parent_category;
			$product->category_level_2                  = $request->category_level_2;
			
			$product->save(); 
			$productId								= $product->id;

			if(!$productId){
				Session()->flash('error', trans("Something went wrong.")); 
				return Redirect()->back()->withInput();
			}

			// ............................................
			$sizeIds_arr = [];
			$colorIds_arr = [];
			$variantIds_arr = [];
			if(isset($formData['variant'])){
				foreach($formData['variant'] as $colorId => $variant){
					foreach($variant as $value){
						if(isset($value['price'])){
							if(isset($value['variant_id'])){
								$variantIds_arr[] = $value['variant_id'];
							}
							if(!in_array($value['size_id'], $sizeIds_arr)){
								$sizeIds_arr[] = $value['size_id'];
							}
						}
					}
					$colorIds_arr[] = $colorId;
				}
			}
			ProductVariant::whereNotIn('id',$variantIds_arr)->where(['product_id'=>$productId])->delete();
			ProductColor::whereNotIn('color_id',$colorIds_arr)->where('product_id',$productId)->delete();
			ProductSize::where(["product_id"=>$productId])->delete();
			// ............................................

			if(isset($formData['variant'])){
				foreach($formData['variant'] as $colorId => $variant){
					foreach($variant as $key => $value){
						if(isset($value['price'])){
							$productVariant                  = ProductVariant::where([
								'product_id'=>$productId,
								'color_id'	=>$colorId,
								'size_id'	=>$value['size_id']
							])->first();
							if(!$productVariant){
							$productVariant                  = new ProductVariant;
							}
							$productVariant->product_id      = $productId;
							$productVariant->color_id        = $colorId;
							$productVariant->size_id         = $value['size_id'];
							$productVariant->price           = $value['price'];
							$productVariant->stock_qty       = $value['stock_qty'];
							$productVariant->save();
						}
					}
					$ProductColor              		    = ProductColor::where(['product_id'=>$productId,'color_id'=>$colorId])->first(); 
					if($ProductColor == null){
						$ProductColor              		= new ProductColor; 
						$ProductColor->product_id  		= $productId;
						$ProductColor->color_id    		= $colorId;
					}

					if ($request->hasFile('variant_video.'.$colorId)) {
						// $ProductColorvideo                	= $this->upload($request, 'variant_video.'.$colorId, '/uploads/product_video/');
						// $ProductColor->video                = $ProductColorvideo;
						// $ProductColor->video_thumbnail		= time() . '-video-thumbnail.jpg';
						// generateThumbnail('storage/uploads/product_video/'.$ProductColorvideo, 'storage/uploads/product_video/thumbnail/'.$ProductColor->video_thumbnail );
						$this->deleteVideoOnCDN($ProductColor->video);
						$name = 'variant_video'.$colorId . time();
						$uploadedResponse = $this->uploadVideoOnCDN($request->File('variant_video.'.$colorId), $name);
						if (!empty($uploadedResponse) && $uploadedResponse['status'] == 'success') {
							$ProductColor->video = $uploadedResponse['guid'];
						}
					}
					$ProductColor->save();
				}
			}
			if(count($sizeIds_arr)){
				foreach($sizeIds_arr as $sizeId){
					$ProductSize              		= new ProductSize; 
					$ProductSize->product_id  		= $productId;
					$ProductSize->size_id    		= $sizeId;
					$ProductSize->save();
				}
			}
			$response               		=  array();
			$response['status']     		= 'success';
			$response['msg']		        = trans('messages.the_product_has_been_updated');
			$response['data']     			= [];
	
			return $response;
		}
	}
	public function deleteProduct(Request $request,$id){
		if($request->wantsJson()){
			$user 		 = Auth::guard('api')->user();
		}else{
			$user        = Auth::user();
		}
		$Details   =  Product::where(['id'=>$id,'user_id'=>$user->id]);
		if ($Details) {
			Product::where(['id'=>$id,'user_id'=>$user->id])
			->update(['is_deleted'=>1]);
		}
		$response               		=  array();
		$response['status']     		= 'success';
		$response['msg']		        = trans('messages.the_product_has_been_deleted');
		$response['data']     			= [];

		return $response;
	}
}

