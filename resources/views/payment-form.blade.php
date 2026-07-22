<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayTR Payment</title>
</head>
<body>
    <form method="post" action="{{ env('PAYTR_BASE_URL') }}">
        <input type="hidden" name="merchant_id" value="{{ $merchant_id }}">
        <input type="hidden" name="user_name" value="{{ $user_name }}">
        <input type="hidden" name="user_address" value="{{ $user_address }}">
        <input type="hidden" name="user_phone" value="{{ $user_phone }}">
        <input type="hidden" name="merchant_oid" value="{{ $merchant_oid }}">
        <input type="hidden" name="email" value="{{ $email }}">
        <input type="hidden" name="payment_amount" value="{{ $payment_amount }}">
        <input type="hidden" name="user_basket" value="{{ $user_basket }}">
        <input type="hidden" name="no_installment" value="{{ $no_installment }}">
        <input type="hidden" name="max_installment" value="{{ $max_installment }}">
        <input type="hidden" name="currency" value="{{ $currency }}">
        <input type="hidden" name="test_mode" value="1">
        <input type="hidden" name="user_ip" value="{{ $user_ip }}">
        <input type="hidden" name="payment_type" value="{{ $payment_type }}">
        <input type="hidden" name="paytr_token" value="{{ $paytr_token }}">
        <input type="text" name="card_number" value="9792030394440796" placeholder="Card Number" />
        <input type="text" name="cvv" value="000" placeholder="CVV" />
        <input type="text" name="expiry_month" value="12" placeholder="Expiry Month" />
        <input type="text" name="expiry_year" value="25" placeholder="Expiry Year" />
        <input type="hidden" name="installment_count" value="{{ $installment_count }}">
        <input type="hidden" name="merchant_ok_url" value="{{ $merchant_ok_url }}">
        <input type="hidden" name="merchant_fail_url" value="{{ $merchant_fail_url }}">
        <input type="hidden" name="cc_owner" value="{{ $cc_owner }}">

        
        <button type="submit">Pay with PayTR</button>
    </form>
</body>
</html>