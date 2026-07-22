<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayTR Payment</title>
</head>
<body>
    <h2>Secure Payment</h2>
    <iframe src="https://www.paytr.com/odeme/guvenli/{{ $token }}" frameborder="0" width="100%" height="600"></iframe>
</body>
</html>
{{-- <script>
    window.onload = function() {
        setTimeout(function() {
            window.location.href = "{{ $callbackUrl }}";
        }, 5000); // Redirect after 5 seconds
    };
</script> --}}
