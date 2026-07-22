<?php

namespace App\Http\Middleware;

use Closure;
Use Auth;
Use Redirect;
Use Session;
Use App;

class GuestAdmin 
{
    /**
     * Run the request filter.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next) {
		App::setLocale("en");
		/* if(!empty(Auth::guard('admin')->user())){
			return Redirect::to('/adminpnlx/dashboard');
		} */
        return $next($request);
    }
}
