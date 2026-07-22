<?php

namespace App\Http\Controllers\adminpnlx;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Config;
use App\Models\Admin;
use App\Models\EmailAction;
use App\Models\EmailTemplate;
use URL;
use Session,App;

class LoginController extends Controller
{
    public function __construct(Request $request)
    {   
        parent::__construct();
        $this->request = $request;
    }

    public function login(Request $request)
    {
        
        $language_id = $this->current_language_id();
        if (Auth::guard('admin')->check()) {
            return redirect()->route('dashboard');
        }
        if ($request->isMethod('POST')) {
            $rules = array(
                'email'   => 'required|email',
                'password' => 'required'
            );
            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            } else {
                $userdata = array(
                    'email'       => $request->email,
                    'password'    => $request->password,
                    'is_deleted'  => 0,
                );
                
                session()->put('sel_lang',1);
                if (auth()->guard('admin')->attempt($userdata)) {
                    if (auth()->guard('admin')->user()->is_active == 0) {
                        auth()->guard('admin')->logout();
                        Session()->flash('error', 'Your account is deactivated. Please contact to the admin.');
                        return back()->withInput();
                    } 
                   $admin_modules	=	$this->buildTree(0);
					Session()->put('acls',$admin_modules);

                    Session::put('admin_applocale', 'en');
            		App::setLocale('en');
					
                    Session()->flash('flash_notice', 'You are now logged in!');
                    return Redirect()->route('dashboard');
                } else {
                    Session()->flash('error', 'Email or Password is incorrect.');
                    return back()->withInput();
                }
            }
        }
        return view('admin.login.index');
    }

    public function logout(){
        auth()->guard('admin')->logout();
        Session()->flash('flash_notice', 'You are now logged out!');
        return Redirect()->route('adminpnlx');
    }

    public function forgetPassword(Request $request){
       
        if (Auth::guard('admin')->check()) {
            return redirect()->route('dashboard');
        }
        if ($request->isMethod('POST')) {
            $rules = array(
                'email'   => 'required|email',
            );
            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            } else {
                $email              =    $request->email;
                $userDetail         =    Admin::where('email', $email)->first();
             
                if (!empty($userDetail)) {
                    if ($userDetail->is_active == 1) {
                        $forgot_password_validate_string    =     md5($userDetail->email . time() . time());
                        Admin::where('email', $email)->update(array('forgot_password_validate_string' => $forgot_password_validate_string));
                        $settingsEmail          =  Config('Site.email');
                        $email                  =  $userDetail->email;
                        $full_name              =  $userDetail->full_name;
                        URL::to('adminpnlx/reset_password/'.$forgot_password_validate_string);
                        $route_url              =  URL::to('adminpnlx/reset_password/'.$forgot_password_validate_string);
                        $emailActions           =  EmailAction::where('action', '=', 'forgot_password')->get()->toArray();
                        $language_id            =  1;
                        $emailTemplates         =  EmailTemplate::where('action', '=', 'forgot_password')->select("name", "action", DB::raw("(select subject from email_template_descriptions where parent_id=email_templates.id AND language_id=$language_id) as subject"), DB::raw("(select body from email_template_descriptions where parent_id=email_templates.id AND language_id=$language_id) as body"))->get()->toArray();
                        $cons = explode(',', $emailActions[0]['options']);
                        $constants = array();
                        foreach ($cons as $key => $val) {
                            $constants[] = '{' . $val . '}';
                        }
                        $subject             =  $emailTemplates[0]['subject'];
                        $rep_Array             = array($email, $route_url);
                        $messageBody        =  str_replace($constants, $rep_Array, $emailTemplates[0]['body']);
                        $this->sendMail($email, $full_name, $subject, $messageBody, $settingsEmail);
                     
                        Session()->flash('flash_notice', trans('An email has been sent to your inbox. To reset your password please follow the steps mentioned in the email.'));
                        return Redirect()->route('adminpnlx');
                    } else {
                        return Redirect()->route('forget_password')->with('error', trans('Your account has been temporarily disabled. Please contact administrator to unlock.'));
                    }
                } else {
                    return Redirect()->route('adminpnlx')->with('error', "Your email is not registered with Us");
                }
            }
        }
        return view('admin.login.forget_password');
    }

    public function resetPassword($validate_string = null, Request $request) {
        if ($validate_string != "" && $validate_string != null) {
            $userDetail    =    Admin::where('is_active', '1')->where('forgot_password_validate_string', $validate_string)->first();
            if (!empty($userDetail)) {
                return View('admin.login.reset_password', compact('validate_string'));
            } else {
                return Redirect()->route('adminpnlx')
                    ->with('error', trans('Sorry, you are using wrong link.'));
            }
        } else {
            return Redirect()->route('adminpnlx')->with('error', trans('Sorry, you are using wrong link.'));
        }
    }

    public function save_password($validate_string = null, Request $request){
        $thisData                =    $request->all();
        $newPassword        =    $request->input('new_password');
        $validate_string    =    $request->input('validate_string');
        $messages = array(
            'new_password.required'                 => trans('The new password field is required.'),
            'new_password_confirmation.required'     => trans('The confirm password field is required.'),
            'new_password.confirmed'                 => trans('The confirm password must be match to new password.'),
            'new_password.min'                         => trans('The password must be at least 8 characters.'),
            'new_password_confirmation.min'         => trans('The confirm password must be at least 8 characters.'),
            "new_password.custom_password"            =>    "Password must have combination of numeric, alphabet and special characters.",
        );
        Validator::extend('custom_password', function ($attribute, $value, $parameters) {
            if (preg_match('#[0-9]#', $value) && preg_match('#[a-zA-Z]#', $value) && preg_match('#[\W]#', $value)) {
                return true;
            } else {
                return false;
            }
        });
        $validator = Validator::make(
            $request->all(),
            array(
                'new_password'                => 'required|min:8|custom_password',
                'new_password_confirmation' => 'required|same:new_password',
            ),
            $messages
        );
        if ($validator->fails()) {
            return Redirect()->to('adminpnlx/reset_password/' . $validate_string)
                ->withErrors($validator)->withInput();
        } else {
            $userInfo = Admin::where('forgot_password_validate_string', $validate_string)->first();
            Admin::where('forgot_password_validate_string', $validate_string)
                ->update(array(
                    'password'                            =>    Hash::make($newPassword),
                    'forgot_password_validate_string'    =>    ''
                ));
            $settingsEmail         = Config('Site.email');
            $action                = "reset_password";
            $emailActions        =    EmailAction::where('action', '=', 'reset_password')->get()->toArray();
            $emailTemplates        =    EmailTemplate::where('action', '=', 'reset_password')->get(array('name', 'subject', 'action', 'body'))->toArray();
            $cons                 =     explode(',', $emailActions[0]['options']);
            $constants             =     array();
            foreach ($cons as $key => $val) {
                $constants[] = '{' . $val . '}';
            }
            $subject             =  $emailTemplates[0]['subject'];
            $rep_Array             = array($userInfo->name);
            $messageBody        =  str_replace($constants, $rep_Array, $emailTemplates[0]['body']);
            $this->sendMail($userInfo->email, $userInfo->name, $subject, $messageBody, $settingsEmail);
            Session()->flash('flash_notice', trans('Thank you for resetting your password. Please login to access your account.'));
            return Redirect()->route('adminpnlx');
        }
    } 
}
