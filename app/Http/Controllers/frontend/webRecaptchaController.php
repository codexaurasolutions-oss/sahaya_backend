<?php

namespace App\Controllers\frontend;

use Illuminate\Support\Facades\Http;

class webRecaptchaController
{
    public static function verify($token)
    {
        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => config('services.recaptcha.secret'),
            'response' => $token,
        ]);
        return $response->json();
    }
}
