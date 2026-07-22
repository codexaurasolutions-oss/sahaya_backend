<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayTR Payment</title>
</head>
<body>
    <form action="https://www.paytr.com/odeme" method="post">
        <!-- Card details -->
        <label for="cc_owner">Card Owner Name:</label>
        <input type="text" name="cc_owner" value="TEST KARTI"><br>

        <label for="card_number">Card Number:</label>
        <input type="text" name="card_number" value="9792030394440796"><br>

        <label for="expiry_month">Expiration Month:</label>
        <input type="text" name="expiry_month" value="12"><br>

        <label for="expiry_year">Expiration Year:</label>
        <input type="text" name="expiry_year" value="99"><br>

        <label for="cvv">CVV:</label>
        <input type="text" name="cvv" value="000"><br>

        <!-- Hidden Fields with Data from Controller -->
        <input type="hidden" name="merchant_id" value="{{ $data['merchant_id'] }}">
        <input type="hidden" name="user_ip" value="{{ $data['user_ip'] }}">
        <input type="hidden" name="merchant_oid" value="{{ $data['merchant_oid'] }}">
        <input type="hidden" name="email" value="{{ $data['email'] }}">
        <input type="hidden" name="payment_type" value="{{ $data['payment_type'] }}">
        <input type="hidden" name="payment_amount" value="{{ $data['payment_amount'] }}">
        <input type="hidden" name="currency" value="{{ $data['currency'] }}">
        <input type="hidden" name="installment_count" value="{{ $data['installment_count'] }}">
        <input type="hidden" name="test_mode" value="{{ $data['test_mode'] }}">
        <input type="hidden" name="non_3d" value="{{ $data['non_3d'] }}">
        <input type="hidden" name="merchant_ok_url" value="{{ $data['merchant_ok_url'] }}">
        <input type="hidden" name="merchant_fail_url" value="{{ $data['merchant_fail_url'] }}">
        <input type="hidden" name="callback_url" value="{{ $data['callback_url'] }}">
        <input type="hidden" name="paytr_token" value="{{ $data['paytr_token'] }}">
        <input type="hidden" name="user_name" value="{{ $data['user_name'] }}">
        <input type="hidden" name="user_address" value="{{ $data['user_address'] }}">
        <input type="hidden" name="user_basket" value="{{ $data['user_basket'] }}">

        <input type="submit" value="Submit Payment">
    </form>
</body>
</html>
