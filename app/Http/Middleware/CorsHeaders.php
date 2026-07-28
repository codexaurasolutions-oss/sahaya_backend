<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CorsHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (!$response->headers->has('Access-Control-Allow-Origin')) {
            $origin = $request->header('Origin', '*');
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }
}
