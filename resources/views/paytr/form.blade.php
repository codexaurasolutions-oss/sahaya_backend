<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Form</title>
</head>
<body>
    <div>
        <h1>Payment Form</h1>
        <form action="{{ route('payment.process') }}" method="POST">
            @csrf
            <button type="submit">Proceed to Payment</button>
        </form>
    </div>
</body>
</html>