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

class GuestFront 
{
    /**
     * Run the request filter.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */ 
    public function handle($request, Closure $next) {
        return $next($request);
    }

}
