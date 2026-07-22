<?php
/**
 * @author Gizem Sever <gizemsever68@gmail.com>
 */
return [
    'merchant_id' => env('PAYTR_MERCHANT_ID'),
    'merchant_key' => env('PAYTR_MERCHANT_KEY'),
    'merchant_salt' => env('PAYTR_MERCHANT_SALT'),
    'api_url' => env('PAYTR_API_URL', 'https://www.paytr.com'),
];