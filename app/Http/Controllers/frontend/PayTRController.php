<?php

namespace App\Http\Controllers\frontend;


use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use Auth;
use App\Models\ProductVariant;
use App\Models\ReviewRating;
use Carbon\Carbon;
use App\Models\User;
use App\Models\ProductSize;
use App\Models\BlockUser;
use App\Models\ShippingAddressModel;
use App\Models\ProductColor;
use App\Models\Coupon;
use App\Models\Category;
use Past\Paytr\Payment;
use Past\Paytr\Request\Order;
use Past\Paytr\Request\Option;
use Past\Paytr\Enums\TransactionType;
use Past\Paytr\Enums\PaymentType;
use Past\Paytr\Enums\CardType;
use Past\Paytr\Request\Basket;
use Past\Paytr\Request\Product;
use DB;
use Past\Paytr\Enums\Currency;
use Config;
use App,Str;
use Validator;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayTRController extends Controller
{

    private $merchant_id = 'MAGAZA_NO';
    private $merchant_key = 'XXXXXXXXXXX';
    private $merchant_salt = 'YYYYYYYYYYY';
    private $merchant_ok_url = 'http://site-ismi/basarili';
    private $merchant_fail_url = 'http://site-ismi/basarisiz';
    private $post_url = 'https://www.paytr.com/odeme';
  
    public function __construct()
{
    // No need to load from env anymore since you are using static values
    $this->merchantKey = 'EkrPt6wsUqxrxddM';
    $this->merchantSalt = 'dq9PYiZWe95QPqP2';
    $this->merchantId = '523380';  // Static merchant ID
}
// public function generateToken(Request $request)
// {
//     // Static data for testing payment
//     $userIp = '192.168.1.1';
//     $orderId = '123456';
//     $orderProducts = '[{"product_id":1,"quantity":2},{"product_id":2,"quantity":1}]';
//     $amount = 100;
//     $email = 'customer@example.com';
//     $firstName = 'John';
//     $lastName = 'Doe';
//     $currency = 'TL';
//     $addressData = ['houseNo' => '1234 Elm Street'];
//     $phoneNumber = '1234567890';
//     $language = 'en';
    
//     // Updated card details for testing
//     $ccOwner = 'PAYTR TEST';  // Card name
//     $cardNumber = '4355084355084358';  // Updated card number (no spaces)
//     $expiryMonth = '12';  // Expiration month
//     $expiryYear = '30';  // Expiration year (2000+30 for 2030)
//     $cvv = '000';  // CVV
    
//     $failUrl = url('/paytr/fail');
//     $successUrl = url('/paytr/success');
    
//     $merchantOid = $orderId . Str::random(4);
//     $name = $firstName . ' ' . $lastName;
    
//     // Payload to send to PayTR
//     $payload = [
//         'merchant_id' => $this->merchantId,  // Static Merchant ID
//         'email' => $email,
//         'payment_amount' => $amount,
//         'merchant_oid' => $merchantOid,
//         'user_name' => $name,
//         'user_address' => $addressData['houseNo'] ?? '',
//         'user_phone' => $phoneNumber,
//         'user_basket' => json_encode(json_decode($orderProducts)),
//         'user_ip' => $userIp,
//         'debug_on' => 1,
//         'test_mode' => '0',
//         'client_lang' => $language,
//         'currency' => $currency,
//         'installment_count' => "0",
//         'non_3d' => "1",  // 0 to use 3D Secure authentication
//         'payment_type' => "card",
//         'cc_owner' => $ccOwner,
//         'card_number' => $cardNumber,
//         'expiry_month' => $expiryMonth,
//         'expiry_year' => $expiryYear,
//         'cvv' => $cvv,
//         'merchant_ok_url' => $successUrl,
//         'merchant_fail_url' => $failUrl,
//     ];

//     // Log the request payload

//     // Generate the hash for token creation
//     $hashStr = $payload['merchant_id'] . $payload['user_ip'] . $payload['merchant_oid'] . $payload['email'] . $payload['payment_amount'] . $payload['payment_type'] . $payload['installment_count'] . $payload['currency'] . $payload['test_mode'] . $payload['non_3d'];
//     $paytrToken = $hashStr . $this->merchantSalt;  // Use static merchant salt
//     $token = base64_encode(hash_hmac('sha256', $paytrToken, $this->merchantKey, true));  // Use static merchant key

//     // Append the token to the payload
//     $payload['paytr_token'] = $token;

//     // Send the payment request to PayTR
//     $response = Http::asForm()->post('https://www.paytr.com/odeme', $payload);

//     // Log the raw response body
// // dd($response,$response->body(),$response->json());
//     // Check for successful response
//     if ($response->successful()) {
//         // If the response contains a URL (for 3D Secure)
//         if (isset($response->json()['url'])) {
//             // Redirect to the PayTR 3D Secure page for user authentication
//             return redirect($response->json()['url']);
//         } else {
//             // If no URL is returned, display error
//             return response()->json([
//                 'message' => 'Unexpected response from PayTR.',
//                 'response' => $response->json(),
//             ]);
//         }
//     } else {
//         return response()->json([
//             'message' => 'Payment request failed.',
//             'error' => $response->json(),
//         ]);
//     }
// }


// public function sendPayment(Request $request)
// {
//     // Static Payment Data (for testing)
//     $merchant_id = '523380';  // Your Merchant ID
//     $merchant_key = 'EkrPt6wsUqxrxddM';  // Your Merchant Password (API Key)
//     $merchant_salt = 'dq9PYiZWe95QPqP2';  // Your Merchant Salt (Secret Key)
//      // Cardholder name - ensure this is not empty
//      $cc_owner = "John Doe"; // Hardcoded or dynamic name (make sure it's a valid non-empty string)
//      $user_name = "PayTar Kk";
//      // Ensure cardholder name is provided
//      if (empty($cc_owner)) {
//          return response()->json(['error' => 'Cardholder name is required.'], 400);
//      }
    
//     $merchant_oid = uniqid(); // Unique order ID (e.g., a combination of timestamp + unique ID)
//     $payment_amount = 1000; // Example amount in smallest unit (1000 = 10.00 TRY)
//     $currency = "TL"; // Currency code
//     $payment_type = "card"; // Payment type
//     $user_ip = $request->ip(); // Get user's IP address
//     $email = "customer@example.com"; // Customer's email (this should be dynamically passed)
//     $test_mode = "1"; // Test mode (1 for sandbox, 0 for live)

//     // Success and failure URLs (where PayTR will redirect after payment processing)
//     $merchant_ok_url = url('/paytr/success'); // URL to redirect after successful payment
//     $merchant_fail_url = url('/paytr/fail'); // URL to redirect after failed payment

//     // User basket data (in JSON format)
//     $user_basket = json_encode([
//         ["item_name" => "Product 1", "price" => "500", "quantity" => 1],
//         ["item_name" => "Product 2", "price" => "500", "quantity" => 1]
//     ]);

//     // Generate the hash string for PayTR (for security and verification)
//     // $hash_str = $merchant_id . $user_ip . $merchant_oid . $email . $amount . $payment_type . $currency . $test_mode;
//     // $token = base64_encode(hash_hmac('sha256', $hash_str . $merchant_salt, $merchant_key, true));
//     $installment_count = 0;
//     $non_3d="0";
//     $hash_str = $merchant_id . $user_ip . $merchant_oid . $email . $payment_amount . $payment_type . $installment_count. $currency. $test_mode. $non_3d;
//     $token = base64_encode(hash_hmac('sha256',$hash_str.$merchant_salt,$merchant_key,true));
    
//     // PayTR payment URL (sandbox URL in this case)
//     $post_url = 'https://www.paytr.com/odeme'; // Use the correct URL for sandbox or production
    
//     $card_type = 'saglamkart';
//     // Data to send to PayTR
//     $payment_data = [
//         'merchant_id' => $merchant_id,
//         'merchant_oid' => $merchant_oid,
//         'payment_amount' => $payment_amount,
//         'currency' => $currency,
//         'payment_type' => $payment_type,
//         'user_ip' => $user_ip,
//         'email' => $email,
//         'user_name' => $user_name,
//         'cc_owner' => $cc_owner, 
//         'test_mode' => $test_mode,
//         'merchant_ok_url' => $merchant_ok_url,
//         'merchant_fail_url' => $merchant_fail_url,
//         'user_basket' => $user_basket,
//         'paytr_token' => $token,
//         'installment_count' => $installment_count,
//         'card_number' => '9792030394440796', // Static card number for testing
//         'expiry_month' => '12',
//         'expiry_year' => '99',
//         'cvv' => '000'
//     ];

//     // Send the payment request to PayTR
//     try {
//         $response = Http::withHeaders([
//             'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3'
//         ])->asForm()->post($post_url, $payment_data);
        
//         dd($response->json(),$response->body(),$payment_data,$response);

//         // Check response from PayTR
//         if ($response->successful()) {
//             // Payment initiation was successful, handle response if needed
//             return redirect()->to($response->json()['redirect_url']);
//         } else {
//             // Log and return error if something went wrong
//             Log::error('PayTR payment initiation failed', ['response' => $response->body()]);
//             return response()->json(['error' => 'Payment initiation failed.'], 500);
//         }
//     } catch (\Exception $e) {
//         Log::error('Error in sending PayTR payment', ['exception' => $e->getMessage()]);
//         return response()->json(['error' => 'Error in sending payment request.'], 500);
//     }
// }

// public function handleNotification(Request $request)
// {
//     $post = $request->all();

//     // Merchant credentials
//     $merchant_key = env('PAYTR_API_KEY');
//     $merchant_salt = env('PAYTR_API_SECRET');

//     // Generate the hash to validate the response
//     $hash = base64_encode(hash_hmac('sha256', $post['merchant_oid'] . $merchant_salt . $post['status'] . $post['total_amount'], $merchant_key, true));

//     // Validate the hash
//     if ($hash != $post['hash']) {
//         Log::error('PAYTR notification failed: bad hash', ['data' => $post]);
//         return response('PAYTR notification failed: bad hash', 400);
//     }

//     // Check payment status
//     if ($post['status'] == 'success') {
//         // Payment successful, process the order
//         Log::info('Payment successful', ['order_id' => $post['merchant_oid'], 'amount' => $post['total_amount']]);
//     } else {
//         // Payment failed, handle failure
//         Log::warning('Payment failed', ['order_id' => $post['merchant_oid'], 'amount' => $post['total_amount']]);
//     }

//     // Return OK to PayTR
//     return response('OK', 200);
// }

public function createPayment()
{
    // Static configuration settings
    $configData = [
        'merchant_id' => env('PAYTR_MERCHANT_ID'),
        'merchant_key' => env('PAYTR_MERCHANT_KEY'),
        'merchant_salt' => env('PAYTR_MERCHANT_SALT'),
        'api_url' => 'https://www.paytr.com',
        'base_uri' => env('PAYTR_BASE_URI', 'https://www.paytr.com'),
    ];

    // Payment options
    $optionsData = [
        'transaction_type' => TransactionType::IFRAME,
        'currency' => Currency::TL,
        'is_test_mode' => true,
        'success_url' => 'https://ayva.stage04.obdemo.com/paytr/callback-ptr',
        'fail_url' => route('payment.fail'),
        'callback_url' => 'https://ayva.stage04.obdemo.com/paytr/callback-ptr', 
    ];

    $basket = new Basket();
    
    // Add products to the basket
    $product1 = new Product('Test Product', 100.00); // Ensure Product class exists
    $basket->addProduct($product1, 1); 

    // Order details
    $order = new Order();
    $order->setMerchantOrderId('ORDER' . time());
    $order->setUserIp(request()->ip());
    $order->setEmail('test@example.com');
    $order->setUserName('Test User');
    $order->setUserAddress('Test Address, City, Country');
    $order->setUserPhone('5551234567');
    $order->setPaymentAmount(10000); // 100.00 TRY (amount in Kuruş)
    $order->setBasket($basket); // Set basket correctly

    // Create Payment Instance
    $payment = new Payment($configData, $optionsData);
    $payment->setOrder($order);

    try {
        $response = $payment->call();

        if ($response->getResponse()->isSuccess()) {
            $token = $response->getResponse()->getContent()['token'];
            return view('paytr.iframe', compact('token'))->with('callbackUrl', 'https://ayva.stage04.obdemo.com/paytr/callback-ptr');
        }
         else {
            return response()->json(['error' => $response->getResponse()->getContent()], 400);
        }
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }

}

public function success()
{
    return "Payment Successful!";
}

public function fail()
{
    return "Payment Failed!";
}


// public function paymentForm(Request $request)
// {
   
//     return view('paytr.payment_form');
// }



// public function paymentRequestapi(Request $request)
//     {
//         return 11111;
//         $user_basket = json_encode([
//             ["Altis Renkli Deniz Yatağı - Mavi", "18.00", 1],
//             ["Pharmasol Güneş Kremi 50+ Yetişkin & Bepanthol Cilt Bakım Kremi", "33.25", 2],
//             ["Bestway Çocuklar İçin Plaj Seti Beach Set ÇANTADA DENİZ TOPU-BOT-KOLLUK", "45.42", 1]
//         ]);

//         $merchant_oid = rand();
//         $payment_amount = "100.99";
//         $currency = "TL";
//         $payment_type = "card";
//         $test_mode = "0";
//         $non_3d = "0";
//         $user_ip = $request->ip();
//         $email = "testnon3d@paytr.com";
//         $installment_count = 1;  // Adjust this if necessary
//         $client_lang = "tr";

//         // Generate the hash
//         $hash_str = $this->merchant_id . $user_ip . $merchant_oid . $email . $payment_amount . $payment_type . $installment_count . $currency . $test_mode . $non_3d;
//         $token = base64_encode(hash_hmac('sha256', $hash_str . $this->merchant_salt, $this->merchant_key, true));

//         // Return the response with payment details
//         return response()->json([
//             'form_action' => $this->post_url,
//             'merchant_id' => $this->merchant_id,
//             'user_ip' => $user_ip,
//             'merchant_oid' => $merchant_oid,
//             'email' => $email,
//             'payment_type' => $payment_type,
//             'payment_amount' => $payment_amount,
//             'currency' => $currency,
//             'test_mode' => $test_mode,
//             'non_3d' => $non_3d,
//             'merchant_ok_url' => $this->merchant_ok_url,
//             'merchant_fail_url' => $this->merchant_fail_url,
//             'user_basket' => $user_basket,
//             'paytr_token' => $token,
//             'client_lang' => $client_lang,
//             'installment_count' => $installment_count,
//         ]);
//     }

//     public function paymentNotificationapi(Request $request)
//     {
//         $post = $request->all();

//         // Validate hash to prevent tampering
//         $hash = base64_encode(hash_hmac('sha256', $post['merchant_oid'] . $this->merchant_salt . $post['status'] . $post['total_amount'], $this->merchant_key, true));

//         if ($hash != $post['hash']) {
//             return response('PAYTR notification failed: bad hash', 400);
//         }

//         // Handle the payment result
//         if ($post['status'] == 'success') {
//             // Process successful payment, e.g., update the order status in the database
//         } else {
//             // Handle failed payment, e.g., notify the user
//         }

//         return response('OK');
//     }


// public function paymentRequest(Request $request)
//     {
//         $user_basket = json_encode([
//             ["Altis Renkli Deniz Yatağı - Mavi", "18.00", 1],
//             ["Pharmasol Güneş Kremi 50+ Yetişkin & Bepanthol Cilt Bakım Kremi", "33.25", 2],
//             ["Bestway Çocuklar İçin Plaj Seti Beach Set ÇANTADA DENİZ TOPU-BOT-KOLLUK", "45.42", 1]
//         ]);

//         $merchant_oid = rand();
//         $payment_amount = "100.99";
//         $currency = "TL";
//         $payment_type = "card";
//         $test_mode = "0";
//         $non_3d = "0";
//         $user_ip = $request->ip();
//         $email = "testnon3d@paytr.com";
//         $installment_count = 1;  // Adjust this if necessary
//         $client_lang = "tr";

//         // Generate the hash
//         $hash_str = $this->merchant_id . $user_ip . $merchant_oid . $email . $payment_amount . $payment_type . $installment_count . $currency . $test_mode . $non_3d;
//         $token = base64_encode(hash_hmac('sha256', $hash_str . $this->merchant_salt, $this->merchant_key, true));

//         // Return the response with payment details
//         return response()->json([
//             'form_action' => $this->post_url,
//             'merchant_id' => $this->merchant_id,
//             'user_ip' => $user_ip,
//             'merchant_oid' => $merchant_oid,
//             'email' => $email,
//             'payment_type' => $payment_type,
//             'payment_amount' => $payment_amount,
//             'currency' => $currency,
//             'test_mode' => $test_mode,
//             'non_3d' => $non_3d,
//             'merchant_ok_url' => $this->merchant_ok_url,
//             'merchant_fail_url' => $this->merchant_fail_url,
//             'user_basket' => $user_basket,
//             'paytr_token' => $token,
//             'client_lang' => $client_lang,
//             'installment_count' => $installment_count,
//         ]);
//     }

//     public function paymentNotification(Request $request)
//     {
//         $post = $request->all();

//         // Validate hash to prevent tampering
//         $hash = base64_encode(hash_hmac('sha256', $post['merchant_oid'] . $this->merchant_salt . $post['status'] . $post['total_amount'], $this->merchant_key, true));

//         if ($hash != $post['hash']) {
//             return response('PAYTR notification failed: bad hash', 400);
//         }

//         // Handle the payment result
//         if ($post['status'] == 'success') {
//             // Process successful payment, e.g., update the order status in the database
//         } else {
//             // Handle failed payment, e.g., notify the user
//         }

//         return response('OK');
//     }


// public function showPaymentForm()
//     {
//         return view('paytr.form');
//     }

//     public function processPayment(Request $request)
//     {
//         // PayTR credentials
//         $merchant_id    = '523380'; // Your PayTR Merchant ID
//         $merchant_key   = 'EkrPt6wsUqxrxddM'; // Your PayTR Merchant Key
//         $merchant_salt  = 'dq9PYiZWe95QPqP2'; // Your PayTR Merchant Salt

//         // Order details
//         $email = "customer@example.com"; // Customer's email
//         $payment_amount = 100; // Example: 100.00 TL in kuruş (100 * 100)
//         $merchant_oid = uniqid(); // Unique order ID
//         $user_name = "John Doe"; // Customer's name
//         $user_address = "123 Sample St"; // Customer's address
//         $user_phone = "5551234567"; // Customer's phone number
//         $merchant_ok_url = route('payment.success'); // Success callback URL
//         $merchant_fail_url = route('payment.fail'); // Fail callback URL

//         // Basket items (encoded in base64)
//         $user_basket = base64_encode(json_encode([
//             ["Sample Product 1", "18.00", 1], // Product name, price, quantity
//             ["Sample Product 2", "33.25", 2],
//             ["Sample Product 3", "45.42", 1]
//         ]));

//         // Get the user's IP address
//         $user_ip = $request->ip();

//         // Other required parameters
//         $timeout_limit = 30; // In minutes
//         $debug_on = 1; // Display errors during integration
//         $test_mode = 1; // Set to 1 for test mode
//         $no_installment = 0; // Allow installments
//         $max_installment = 0; // Maximum number of installments (0 = no limit)
//         $currency = "TL"; // Currency (TL, EUR, USD, etc.)
//         $lang = "en"; // Language (tr or en)
//         $payment_type = 'card'; 

//         // Generate the hash string
//         // $hash_str = $merchant_id . $user_ip . $merchant_oid . $email . $payment_amount . $user_basket . $no_installment . $max_installment . $currency . $test_mode;
//         // $paytr_token = base64_encode(hash_hmac('sha256', $hash_str . $merchant_salt, $merchant_key, true));
//         $hash_str = $merchant_id .$user_ip .$merchant_oid .$email .$payment_amount .$user_basket.$no_installment.$max_installment.$currency.$test_mode;
//         $paytr_token=base64_encode(hash_hmac('sha256',$hash_str.$merchant_salt,$merchant_key,true));
//         // dd($paytr_token);
//         // Prepare the POST data
//         $post_vals = [
//             'merchant_id' => $merchant_id,
//             'user_ip' => $user_ip,
//             'merchant_oid' => $merchant_oid,
//             'email' => $email,
//             'payment_amount' => $payment_amount, // Ensure this is an integer
//             'paytr_token' => $paytr_token,
//             'user_basket' => $user_basket,
//             'debug_on' => $debug_on,
//             'no_installment' => $no_installment,
//             'max_installment' => $max_installment,
//             'user_name' => $user_name,
//             'user_address' => $user_address,
//             'user_phone' => $user_phone,
//             'merchant_ok_url' => $merchant_ok_url,
//             'merchant_fail_url' => $merchant_fail_url,
//             'timeout_limit' => $timeout_limit,
//             'currency' => $currency,
//             'test_mode' => $test_mode,
//             'lang' => $lang,
//             'payment_type' => $payment_type,
//         ];

//         // Send the request to PayTR API
//         $ch = curl_init();
//         curl_setopt($ch, CURLOPT_URL, "https://www.paytr.com/odeme/api/get-token");
//         curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
//         curl_setopt($ch, CURLOPT_POST, 1);
//         curl_setopt($ch, CURLOPT_POSTFIELDS, $post_vals);
//         curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
//         curl_setopt($ch, CURLOPT_TIMEOUT, 20);

//         $result = curl_exec($ch);
       

//         if (curl_errno($ch)) {
//             Log::error("PAYTR IFRAME connection error: " . curl_error($ch));
//             return redirect()->back()->with('error', 'Payment connection error.');
//         }

//         curl_close($ch);

//         // Decode the response
//         $result = json_decode($result, true);

//         if ($result['status'] == 'success') {
//             $token = $result['token'];
//             $paymentType = "card";
//             return view('paytr.iframe', compact('token','paymentType'));
//         } else {
//             Log::error("PAYTR IFRAME failed: " . $result['reason']);
//             return redirect()->back()->with('error', 'Payment failed: ' . $result['reason']);
//         }
//     }


//     public function paymentSuccess(Request $request)
//     {
        
//         $post = $request->all();
    
//         $merchant_key   = 'EkrPt6wsUqxrxddM';
//         $merchant_salt  = 'dq9PYiZWe95QPqP2';
//         $payment_type = 'card'; 
    
//         // Generate the hash to validate
//         $hash_str = $post['merchant_oid'] . $merchant_salt . $post['status'] . $post['total_amount'] . $payment_type;
//         $calculated_hash = base64_encode(hash_hmac('sha256', $hash_str, $merchant_key, true));
    
//         // Check if the calculated hash matches the received hash
//         if ($calculated_hash != $post['hash']) {
//             Log::error('PAYTR notification failed: bad hash');
//             return response('PAYTR notification failed: bad hash', 400);
//         }
    
//         // Process payment based on the status
//         if ($post['status'] == 'success') {
//             // Handle successful payment
//             // Update order status, inventory, etc.
//             Log::info('Payment successful for order ID: ' . $post['merchant_oid']);
//             // Respond with 'OK' to acknowledge receipt of the notification
//             return response('OK');
//         } else {
//             // Handle failed payment
//             // Update order status, notify user, etc.
//             Log::error('Payment failed for order ID: ' . $post['merchant_oid']);
//             // Respond with 'OK' to acknowledge receipt of the notification
//             return response('OK');
//         }
//     }
    

//     public function paymentFail(Request $request)
//     {
//         return view('paytr.fail');
//     }
}
