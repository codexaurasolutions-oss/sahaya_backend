<?php

namespace App\Http\Middleware;

use Closure;
Use Auth;
Use Redirect;
Use Session;
Use Response;
Use App;
Use Config;
use Illuminate\Http\Request;

class AuthFront 
{
    /**
     * Run the request filter.
     *
     * @param  \Illuminate\Http\Request  $request 
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next) {
       

        if(!empty(Auth::user())){
            if(Auth::user()->is_deleted == 1 || Auth::user()->is_active == 0){
                Session()->flash('error', trans("messages.your_account_has_been_deactivated_please_contact_to_admin_for_more_details"));
                Auth::logout();
            }
        }
		if (Session::has('applocale')) {
            App::setLocale(Session::get('applocale'));
        }else {
            App::setLocale(Config::get('app.fallback_locale'));
        }
		if (Auth::guest()){
            if ($request->ajax()) {       
            } else {
                return Redirect::to('/'); 
            }
				
		}
        if (!empty(Auth::user()) && Auth::user()->user_role_id == 1) {
            return Redirect::to('/adminpnlx');
        }
        getCurrentLanguage();
        return $next($request);
    }

}
