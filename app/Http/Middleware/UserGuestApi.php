<?php
namespace App\Http\Middleware;

use Closure;
use App;

class UserGuestApi
{
    /**
    * Run the request filter.
    *
    * @param  \Illuminate\Http\Request  $request
    * @param  \Closure  $next
    * @return mixed
    */
    public function handle($request, Closure $next){
        if(!empty($request->header('Accept-Language'))){
            App::setLocale($request->header('Accept-Language'));
            session()->put('admin_applocale',$request->header('Accept-Language'));
        }else{
            App::setLocale("en");
            session()->put('admin_applocale',"en");
        }
	    return $next($request);
	}
}
