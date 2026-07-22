<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PayTR Payment</title>
</head>
<body>
    <h1>Payment Form</h1>
    <form action="/paytr/payment/submit" method="POST">
        <!-- Hidden input data for PayTR -->
        @csrf

        <!-- Submit Button -->
        <input type="submit" value="Proceed to Payment">
    </form>
</body>
</html>
