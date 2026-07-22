<?php

namespace App\Http\Controllers\adminpnlx;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Config;
use App\Models\Lookup;  
use App\Traits\ImageUpload;
use App\Models\User;
use Carbon\Carbon;
use App\Models\EmailAction;
use App\Models\EmailTemplate;
use Str;
use Redirect,Session;

class UsersController extends Controller
{
    public $model               =  'users';
    public $sectionNameSingular =  'customers';
    use ImageUpload;
    public function __construct(Request $request)
    {   
        parent::__construct();
        View()->share('model', $this->model);
        View()->share('sectionNameSingular', $this->sectionNameSingular);
        $this->request = $request;
    }
    public function index(Request $request)
    {
        $DB					=	User::query();
        $searchVariable		=	array();
        $inputGet			=	$request->all();
        if ($request->all()) {
            $searchData			=	$request->all();
            unset($searchData['display']);
            unset($searchData['_token']);

            if (isset($searchData['order'])) {
                unset($searchData['order']);
            }
            if (isset($searchData['sortBy'])) {
                unset($searchData['sortBy']);
            }
            if (isset($searchData['page'])) {
                unset($searchData['page']);
            }
            if ((!empty($searchData['date_from'])) && (!empty($searchData['date_to']))) {
                $dateS = date("Y-m-d",strtotime($searchData['date_from']));
                $dateE =  date("Y-m-d",strtotime($searchData['date_to']));
                $DB->whereBetween('users.created_at', [$dateS . " 00:00:00", $dateE . " 23:59:59"]);
            } elseif (!empty($searchData['date_from'])) {
                $dateS = $searchData['date_from'];
                $DB->where('users.created_at', '>=', [$dateS . " 00:00:00"]);
            } elseif (!empty($searchData['date_to'])) {
                $dateE = $searchData['date_to'];
                $DB->where('users.created_at', '<=', [$dateE . " 00:00:00"]);
            }
            foreach ($searchData as $fieldName => $fieldValue) {
                if ($fieldValue != "") {
                    if ($fieldName == "first_name") {
                        $DB->where("users.first_name", 'like', '%' . $fieldValue . '%');
                    }
                    if ($fieldName == "address") {
                        $DB->where("users.address", 'like', '%' . $fieldValue . '%');
                    }
                    if ($fieldName == "last_name") {
                        $DB->where("users.last_name", 'like', '%' . $fieldValue . '%');
                    }
                    if ($fieldName == "name") {
                        $DB->where("users.name", 'like', '%' . $fieldValue . '%');
                    }
                    if ($fieldName == "email") {
                        $DB->where("users.email", 'like', '%' . $fieldValue . '%');
                    }
                    if ($fieldName == "is_active") {
                        if($fieldValue == 'verified'){
                            $DB->where("users.is_verified", 1);
                        }elseif($fieldValue == 'unverified'){
                            $DB->where("users.is_verified",0);
                        }else{
                            $DB->where("users.is_active", 'like', '%' . $fieldValue . '%');
                        }
                    }
                }
                $searchVariable	=	array_merge($searchVariable, array($fieldName => $fieldValue));
            }
        }

        $DB->where("users.is_deleted", 0);
        $DB->where("users.user_role_id", 2);
        $sortBy = ($request->input('sortBy')) ? $request->input('sortBy') : 'users.created_at';
        $order  = ($request->input('order')) ? $request->input('order')   : 'DESC';
        $records_per_page	=	($request->input('per_page')) ? $request->input('per_page') : Config::get("Reading.records_per_page");
        $results = $DB->orderBy($sortBy, $order)->paginate($records_per_page);
        $complete_string		=	$request->query();
        unset($complete_string["sortBy"]);
        unset($complete_string["order"]);
        $query_string			=	http_build_query($complete_string);
        $results->appends($inputGet)->render();
        $resultcount = $results->count();
        return  View("admin.$this->model.index", compact('resultcount', 'results', 'searchVariable', 'sortBy', 'order', 'query_string'));
    }
    public function create(Request $request)
    {       

       
        $genders = Lookup::where('lookup_type', 'gender')->where('is_active', 1)->get();
    
        return  View("admin.$this->model.add",compact('genders'));
    }
    public function Save(Request $request)
        {
            if ($request->isMethod('POST')) {
                $thisData = $request->all();
        
                $validator                    =   Validator::make(
                    $request->all(), 
                    array(
                    
                        'first_name' 			   		=> 'required',
                        'last_name' 			   		=> 'required',
                        'email' 	               		=> 'required|unique:users,email|regex:/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/',
                        // 'phone_number' 			   		=> 'required|numeric|unique:users,phone_number|digits:10',
                        'phone_number' 			   		=> 'required|numeric|digits_between:10,20',
                        'phone_number_prefix'           => 'required',
                        'phone_number_country_code'		=> 'required',
                        'gender' 						=> 'required',
                        'image'                         => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                        'documents_front'               => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                        'documents_back'                => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                        'password'                 		=> ['required', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
                        'confirm_password'         		=> 'required|same:password'
                    ),
                    array(
                        "password.min"                      	=> trans("messages.password_validation_string"),
                        "email.regex"                       	=> trans("messages.the_email_should_be_a_valid_email"),
                        // 'phone_number.digits' 		        	=> trans('messages.the_phone_number_must_be_10_digits'),
                        'phone_number.digits_between' 		   	=> trans("messages.the_phone_number_must_be_10_digits"),
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
                    )
                );
                $password = $request->input('password');
                if (preg_match('#[0-9]#', $password) && preg_match('#[a-zA-Z]#', $password) && preg_match('#[\W]#', $password)) {
                    $correctPassword = Hash::make($password);
                } else {
                    $errors = $validator->messages();
                    $errors->add('password', trans("The Password must be atleast 8 characters with combination of atleast have one alpha, one numeral and one special character."));
                    return Redirect::back()->withErrors($errors)->withInput();
                }
                if ($validator->fails()) {
                    return Redirect::back()->withErrors($validator)->withInput();
                }else{

                    $language_id                        =   $this->current_language_id();
                    $user                               =   new User;
                    $user->user_role_id                 =   Config('constants.ROLE_ID.CUSTOMER_ROLE_ID');
                    $user->first_name                   =   $request->input('first_name');
                    $user->last_name                    =   $request->input('last_name');
                    $user->name                         =   $request->input('first_name') . ' ' . $request->input('last_name');
                    $user->email                        =   $request->email;
                    $user->phone_number				 	=   $request->input('phone_number');
                    $user->phone_number_prefix			=   $request->input('phone_number_prefix');
                    $user->phone_number_country_code	=   $request->input('phone_number_country_code');
                    $user->password                     =   Hash::make($request->password);
                    $user->gender                    	=   $request->gender;
                    $user->language	        			=   $language_id;
                    $phoneNumber                        =   '+'.$user->phone_number_prefix.' '.$user->phone_number;
                    // if ($request->hasFile('image')) {
                    //     $extension = $request->file('image')->getClientOriginalExtension();
                    //     $fileName = time() . '-image.' . $extension;
                    //     $folderName = strtoupper(date('M') . date('Y')) . "/";
                    //     $folderPath = Config('constants.USER_IMAGE_ROOT_PATH') . $folderName;
                    //     if (!File::exists($folderPath)) {
                    //         File::makeDirectory($folderPath, $mode = 0777, true);
                    //     }
                    //     if ($request->file('image')->move($folderPath, $fileName)) {
                    //         if($user->image){
                    //             File::delete(Config('constants.USER_IMAGE_ROOT_PATH') . $user->image);
                    //         }
                    //         $user->image = $folderName . $fileName;
                    //     }
                    // }
                    // if ($request->hasFile('documents_front')) {
                    //     $extension = $request->file('documents_front')->getClientOriginalExtension();
                    //     $fileName = time() . '-documents_front.' . $extension;
                    //     $folderName = strtoupper(date('M') . date('Y')) . "/";
                    //     $folderPath = Config('constants.USER_IMAGE_ROOT_PATH') . $folderName;
                    //     if (!File::exists($folderPath)) {
                    //         File::makeDirectory($folderPath, $mode = 0777, true);
                    //     }
                    //     if ($request->file('documents_front')->move($folderPath, $fileName)) {
                    //         if($user->documents_front){
                    //             File::delete(Config('constants.USER_IMAGE_ROOT_PATH') . $user->documents_front);
                    //         }
                    //         $user->documents_front = $folderName . $fileName;
                    //     }
                    // }
        
                    
                    // if ($request->hasFile('documents_back')) {
                    //     $extension = $request->file('documents_back')->getClientOriginalExtension();
                    //     $fileName = time() . '-documents_back.' . $extension;
                    //     $folderName = strtoupper(date('M') . date('Y')) . "/";
                    //     $folderPath = Config('constants.USER_IMAGE_ROOT_PATH') . $folderName;
                    //     if (!File::exists($folderPath)) {
                    //         File::makeDirectory($folderPath, $mode = 0777, true);
                    //     }
                    //     if ($request->file('documents_back')->move($folderPath, $fileName)) {
                    //         if($user->documents_back){
                    //             File::delete(Config('constants.USER_IMAGE_ROOT_PATH') . $user->documents_back);
                    //         }
                    //         $user->documents_back = $folderName . $fileName;
                    //     }
                    // }
                    $user->image                        =   $this->upload($request, 'image', config('constants.USER_IMAGE_ROOT_PATH'));
                    $user->documents_front              =   $this->upload($request, 'documents_front', config('constants.USER_IMAGE_ROOT_PATH'));
                    $user->documents_back               =   $this->upload($request, 'documents_back', config('constants.USER_IMAGE_ROOT_PATH'));
                    $SavedResponse                      =   $user->save();

                    $settingsEmail 						=	Config::get('Site.from_email');
					$emailActions           			=   EmailAction::where('action', '=', 'registration_successful')->get()->toArray();
					$emailTemplates         			=   EmailTemplate::where('action', '=', 'registration_successful')->select("name", "action", "subject", "body")->get()->toArray();
					$cons                   			=   explode(',', $emailActions[0]['options']);
					$constants              			=   array();
					foreach ($cons as $key => $val) {
						$constants[]        			=   '{' . $val . '}';
					}
					$subject                			=   $emailTemplates[0]['subject'];
					$rep_Array              			=   array($user->name, $user->email,$phoneNumber,$request->password);
					$messageBody            			=   str_replace($constants, $rep_Array, $emailTemplates[0]['body']);
					$this->sendMail($user->email, $user->name, $subject, $messageBody, $settingsEmail);

                    if (!$SavedResponse) {
                        Session()->flash('error', trans("Something went wrong."));
                        return Redirect()->back()->withInput();
                    } else {
                        Session()->flash('success', ucfirst(Config('constants.CUSTOMER.CUSTOMERS_TITLE')." has been added successfully"));
                        return Redirect()->route($this->model . ".index");
                    }
                }
            } 
        }
    public function edit(Request $request,  $enuserid = null)
        { 
            $user_id = '';
            if (!empty($enuserid)) {
                $user_id     = base64_decode($enuserid);
                $userDetails = User::find($user_id);
                $genders     = Lookup::where('lookup_type', 'gender')->where('is_active', 1)->get();

                return  View("admin.$this->model.edit", compact('userDetails','genders'));
            } else {
                return redirect()->route($this->model . ".index");
            }
        }
    public function update(Request $request,  $enuserid = null)
        {
            if ($request->isMethod('POST')) {
                $thisData = $request->all();
                $user_id = '';
                $image = "";
                if (!empty($enuserid)) {
                    $user_id = base64_decode($enuserid);
                } else {
                    return redirect()->route($this->model . ".index");
                }
                $validator  =   Validator::make(
                    $request->all(), 
                    array(
                        'first_name'     => "required",
                        'last_name'      => "required",
                        'email'          => 'required|email|regex:/(.+)@(.+)\.(.+)/i|unique:users,email,'.$user_id,
                        // 'phone_number' 	 => 'required',
                        'phone_number' 	 => 'required|digits_between:10,20',
                        'gender' 		 => 'required',
                    ),
                    array(
                        "name.required"              => trans("The user name field is required."),
                        "email.required"             => trans("The email field is required."),
                        "email.email"                => trans("The email must be a valid email address"),
                        'phone_number.digits_between'=> trans("messages.the_phone_number_must_be_10_digits"),
                    )
                );
                if ($validator->fails()) {
                    return Redirect::back()->withErrors($validator)->withInput();
                }else{

                    $user                               =   User::where("id",$user_id)->first();
                    $user->first_name                   =   $request->input('first_name');
                    $user->last_name                    =   $request->input('last_name');
                    $user->name                         =   $request->input('first_name') . ' ' . $request->input('last_name');
                    $user->email                        =   $request->email;
                    $user->phone_number				 	=   $request->input('phone_number');
                    $user->phone_number_prefix			=   $request->input('phone_number_prefix');
                    $user->phone_number_country_code	=   $request->input('phone_number_country_code');
                    $user->gender                    	=   $request->gender;
                    
                    if($request->image){
                        $path         = parse_url($user->image, PHP_URL_PATH);
                        $oldPath      = Str::after($path, 'user-image');
                        $user->image  = $this->upload($request,'image',Config('constants.USER_IMAGE_ROOT_PATH'),$oldPath);
                }

                if($request->documents_front){
                    $path         = parse_url($user->documents_front, PHP_URL_PATH);
                    $oldPath      = Str::after($path, 'user-image');
                    $user->documents_front   = $this->upload($request,'documents_front',Config('constants.USER_IMAGE_ROOT_PATH'),$oldPath);
                }

                if($request->documents_back){
                    $path         = parse_url($user->documents_back, PHP_URL_PATH);
                    $oldPath      = Str::after($path, 'user-image');
                    $user->documents_back   = $this->upload($request,'documents_back',Config('constants.USER_IMAGE_ROOT_PATH'),$oldPath);
                }

                    $SavedResponse = $user->save();
                    if (!$SavedResponse) {
                        Session()->flash('error', trans("Something went wrong."));
                        return Redirect()->back()->withInput();
                    }
                    Session()->flash('success', ucfirst(Config('constants.CUSTOMER.CUSTOMERS_TITLE')." has been updated successfully"));
                    return Redirect()->route($this->model . ".index");
                }
            }
        }
    public function destroy( $enuserid)
        {
            $user_id = '';
            if (!empty($enuserid)) {
                $user_id = base64_decode($enuserid);
            }
            $userDetails   =   User::find($user_id);
            if (empty($userDetails)) {
                return Redirect()->route($this->model . '.index');
            }
            if ($user_id) {
                $email              =   'delete_' . $user_id . '_' .!empty($userDetails->email);
                $phone_number       =   'delete_' . $user_id . '_' .!empty($userDetails->phone_number);

                User::where('id', $user_id)->update(array(
                    'is_deleted'    => 1, 
                    'email'         => $email, 
                ));

                Session()->flash('flash_notice', trans(ucfirst( "User has been removed successfully")));
            }
            return back();
        }
    public function changeStatus($modelId = 0, $status = 0)
        {
            if ($status == 1) {
                $statusMessage = trans(Config('constants.CUSTOMER.CUSTOMERS_TITLE') . ' has been activated successfully');
            } else {
                $statusMessage = trans(Config('constants.CUSTOMER.CUSTOMERS_TITLE') . ' has been deactivated successfully');
            }

            $user = User::find($modelId);
            if ($user) {
                $currentStatus = $user->is_active;
                if (isset($currentStatus) && $currentStatus == 0) {
                    $NewStatus = 1;
                } else {
                    $NewStatus = 0;
                }
                $user->is_active = $NewStatus;
                $ResponseStatus = $user->save();
            }
            Session()->flash('flash_notice', $statusMessage);
            return back();
        }
    public function changedPassword(Request $request, $enuserid = null)
        {
            $user_id = '';
            if (!empty($enuserid)) {
                $user_id = base64_decode($enuserid);
            } else {
                return redirect()->route($this->model . ".index");
            }
            if ($request->isMethod('POST')) {
                if (!empty($user_id)) {
                    $validator                  =   Validator::make(
                        $request->all(),
                        array(
                            'new_password'      => ['required',Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
                            'confirm_password'  => 'required|same:new_password',
                        ),
                        array(
                            "new_password.required"      => trans("The new password field is required."),
                            "new_password.min"           => trans("The Password must be atleast 8 characters with combination of atleast have one alpha, one numeral and one special character."),
                            "confirm_password.required"  => trans("The confirm password field is required."),
                            "confirm_password.same"      => trans("The confirm password not matched with new password."),
                        )
                    );
                    $password = $request->input('new_password');
                    if (preg_match('#[0-9]#', $password) && preg_match('#[a-zA-Z]#', $password) && preg_match('#[\W]#', $password)) {
                        $correctPassword = Hash::make($password);
                    } else {
                        $errors = $validator->messages();
                        $errors->add('new_password', trans("The Password must be atleast 8 characters with combination of atleast have one alpha, one numeral and one special character."));
                        return Redirect::back()->withErrors($errors)->withInput();
                    }if ($validator->fails()) {

                        return Redirect::back()->withErrors($validator)->withInput();
                    } else {

                        $userDetails   =  User::find($user_id);
                        $userDetails->password     =  Hash::make($request->new_password);
                        $SavedResponse =  $userDetails->save();

                        $settingsEmail 							=	Config::get('Site.from_email');
                        $emailActions           				=   EmailAction::where('action', '=', 'change_password')->get()->toArray();
                        $emailTemplates         				=   EmailTemplate::where('action', '=', 'change_password')->select("name", "action", "subject", "body")->get()->toArray();
                        $cons                   				=   explode(',', $emailActions[0]['options']);
                        $constants              				=   array();
                        foreach ($cons as $key => $val) {
                            $constants[]        				=   '{' . $val . '}';
                        }
                        $subject                				=   $emailTemplates[0]['subject'];
                        $rep_Array              				=   array($userDetails->name, $request->new_password);
                        $messageBody            				=   str_replace($constants, $rep_Array, $emailTemplates[0]['body']);
                        $this->sendMail($userDetails->email, $userDetails->name, $subject, $messageBody, $settingsEmail);
                        if (!$SavedResponse) {
                            Session()->flash('error', trans("Something went wrong."));
                            return Redirect()->back();
                        }
                        Session()->flash('success', trans("Password changed successfully."));
                        return Redirect()->route($this->model . '.index');
                    }
                }
            }
            $userDetails = array();
            $userDetails   =  User::find($user_id);
            $data = compact('userDetails');
            return view("admin.$this->model.change_password", $data);
        }
    public function view($enuserid = null)
        {
            $user_id = '';
            if (!empty($enuserid)) {
                $user_id = base64_decode($enuserid);
            } else {
                return redirect()->route($this->model . ".index");
            }
            $userDetails    =    User::where('users.id', $user_id)->first();
            $lookupType     =    $userDetails->gender;
            $Selectgender = Lookup::where('id',$lookupType)->where('is_active', 1)->pluck('code')->first();

            return  View("admin.$this->model.view", compact('userDetails','Selectgender'));
        }
    public function sendCredentials($id)
        {
            if(empty($id)){
                return redirect()->back();
            }
            $random = str_shuffle('abcdefghjklmnopqrstuvwxyzABCDEFGHJKLMNOPQRSTUVWXYZ234567890!$%^&!$%^&');
            $password = substr($random, 0, 10);
            $user  = 	User::find($id);
            $settingsEmail 	= 	Config::get("Site.from_email");
            $full_name 		= 	$user->name;
            $email 			=	$user->email;
            $user->password = Hash::make($password);
            $user->save();
            $emailActions 	= 	EmailAction::where('action','=','send_login_credentials')->get()->toArray();
            $emailTemplates = 	EmailTemplate::where('action','=','send_login_credentials')->get(array('name','subject','action','body'))-> toArray();
            $cons 			= 	explode(',',$emailActions[0]['options']);
            $constants 		= 	array();
            foreach($cons as $key => $val){
                $constants[] = '{'.$val.'}';
            }
            $subject 		= 	$emailTemplates[0]['subject'];
            $route_url      =  	Config('constants.WEBSITE_ADMIN_URL').'/login';		
            $rep_Array 		= 	array($full_name,$email,$password,$route_url);
            $messageBody 	= 	str_replace($constants, $rep_Array, $emailTemplates[0]['body']);
            $this->sendMail($email,$full_name,$subject,$messageBody,$settingsEmail);
            Session()->flash('flash_notice', trans("Login credentials send successfully"));
            return redirect()->back();
        }  
        
        public function changeStatusVerify($modelId = 0, $status = 0)
        {
            $user = User::find($modelId);
            if ($user) {
                $currentStatus = $user->is_verified;
                if (isset($currentStatus) && $currentStatus == 0) {
                    $NewStatus = 1;
                } else {
                    $NewStatus = 0;
                }
                if ($NewStatus == 1) {
                    $statusMessage = trans(Config('constants.CUSTOMER.CUSTOMERS_TITLE') . ' has been verified successfully');
                } else {
                    $statusMessage = trans(Config('constants.CUSTOMER.CUSTOMERS_TITLE') . ' has been unverified successfully');
                }
    
                $user->is_verified = $NewStatus;
                $ResponseStatus = $user->save();
            }
            Session()->flash('flash_notice', $statusMessage);
            return back();
        }
}
