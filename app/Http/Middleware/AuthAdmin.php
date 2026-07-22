<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Model\Admin;
use App\Models\AclAdminAction;
use Illuminate\Support\Facades\Auth;
Use Redirect;
Use Route,DB;
Use Session;
Use App;
Use Config;


class AuthAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {   
        if (Session::has('admin_applocale')) {
            App::setLocale(Session::get('admin_applocale'));
        }else {
            App::setLocale(Config::get('app.fallback_locale'));
        }

        if(!empty(Auth::guard('admin')->guest())){
			return Redirect()->to('/adminpnlx');
		}elseif(Auth::guard('admin')->user()->user_role_id != 1){
            $url = $request->url();
            $segment1	=	Request()->segment(1);
			$segment2	=	Request()->segment(2);
			$segment3	=	Request()->segment(3);
            
            $segment1_1 = explode(' ', $segment1);
			$segment1_1 = end($segment1_1);
			$segment2_2 = explode(' ', $segment2);
			$segment2_2 = end($segment2_2);
			$segment3_3 = explode(' ', $segment3);
			$segment3_3 = end($segment3_3);
            
            $user_id		=	Auth::guard('admin')->user()->id;
			$path			=	Request()->path();
			$action			=	Route::current()->getAction();
            $function_name	=	explode("\\",$action['controller']);
			$function_name	=	end($function_name);
            $user_role_id	=	Auth::guard('admin')->user()->user_role_id;
            if($function_name != 'AdminDashboardController@showdashboard'){

                $data  =  AclAdminAction::where('function_name',$function_name)->first(); 
                if(!empty($data)){
                    $permissionData_check   =	DB::table("user_permission_actions")->where('user_id',$user_id)->where('admin_module_action_id',$data->id)->where('is_active',"1")->first();
                    if(empty($permissionData_check)){
                        Session::flash('error', trans("You are not authorized to access this page.")); 
                        return back();die;
                    }
                }
            }

           

        }

	
        return $next($request);
    }
}
