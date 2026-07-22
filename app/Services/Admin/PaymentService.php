<?php 


namespace App\Services\Admin;

use GuzzleHttp\Client;

class PaymentService
{
    protected $client;
    
    public function __construct()
    {
        $this->client = new Client();
    }

    // Method to send the payment data to PayTR API and get the payment URL
    public function generateToken($data)
    {
        // Prepare the data for PayTR API
        $merchant_oid = 'ORDER_'  . '_' . time();
        $payment_amount =100;
        $payment_type ='card';
        $paytrData = [
            'merchant_id' => env('PAYTR_MERCHANT_ID'),
            'api_key' => env('PAYTR_API_KEY'),
            'api_salt' => env('PAYTR_API_SALT'),
            'order_id' => $data['order_id'],
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'email' => $data['email'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'address' => $data['address_data'],
            'phone_number' => $data['phone_number'],
            'language' => $data['language'],
            'user_type' => $data['user_type'],
            'card_number' => '9792030394440796',
            'expiry_month' => '12',
            'expiry_year' => '30',
            'user_ip' => request()->ip(),
            'cvv' => '000',
            'installment_count'=>"0",
            "user_name"=>'as',
            'user_basket'=>$data['order_products'],
            'merchant_ok_url'=>'https://ayva.stage04.obdemo.com',
            'merchant_fail_url'=>'https://ayva.stage04.obdemo.com',
            'currency'=>'TL',
            'merchant_oid'=>$merchant_oid,
            'payment_amount'=>$payment_amount,
            'payment_type'=>$payment_type,
        ];
        $paytrUrl = 'http://www.paytr.com/odeme'; // For production, change to `https://www.paytr.com/odeme`

            // Initialize cURL
     // Initialize cURL
$ch = curl_init();

// Set cURL options
curl_setopt($ch, CURLOPT_URL, "https://www.paytr.com/odeme"); // Make sure to use the sandbox URL
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data)); // Send data properly
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
]);

// Enable cURL verbose mode to get detailed logs
curl_setopt($ch, CURLOPT_VERBOSE, true);

// Create a file handle to capture the debug information
$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

// Execute the request
$response_body = curl_exec($ch);
if (curl_errno($ch)) {
    echo 'cURL error: ' . curl_error($ch);
} else {
    echo 'Response: ' . $response_body;
}

// Close cURL and the verbose log
curl_close($ch);
fclose($verbose);


        die;
        // Send a POST request to PayTR API to generate a payment token
        try {
            $response = $this->client->post($paytrUrl, [
                'json' => $paytrData
            ]);
            dd($response);

            // Decode the response
            $responseData = json_decode($response->getBody()->getContents(), true);
            // Check for success and return the payment URL
            if ($responseData['status'] == 'success' && isset($responseData['url'])) {
                return $responseData['url']; // Return the PayTR payment page URL
            } else {
                return ['error' => 'Payment URL generation failed', 'details' => $responseData];
            }
        } catch (\Exception $e) {
            return ['error' => 'Request failed', 'message' => $e->getMessage()];
        }
    }



}
