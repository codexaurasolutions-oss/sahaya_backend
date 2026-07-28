<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zoho {{ ucfirst($service) }} - {{ $success ? 'Connected' : 'Error' }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .card { background: white; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); padding: 48px; text-align: center; max-width: 440px; width: 90%; }
        .icon { width: 72px; height: 72px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 36px; margin-bottom: 24px; }
        .icon.success { background: #d4edda; color: #28a745; }
        .icon.error { background: #f8d7da; color: #dc3545; }
        h2 { margin-bottom: 12px; color: #333; }
        p { color: #666; font-size: 15px; line-height: 1.5; margin-bottom: 24px; }
        .btn { display: inline-block; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; border: none; cursor: pointer; }
        .btn-primary { background: #D98C7A; color: white; }
        .btn-primary:hover { background: #c47b6a; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon {{ $success ? 'success' : 'error' }}">
            {{ $success ? '&#10003;' : '&#10007;' }}
        </div>
        <h2>{{ $success ? ucfirst($service) . ' Connected!' : 'Connection Failed' }}</h2>
        <p>{{ $message }}</p>
        @if($success)
            <a href="{{ config('zoho.admin_panel_url', 'https://sahayya-admin.vercel.app') }}/admin/zoho-{{ $service }}" class="btn btn-primary">Go to Admin Panel</a>
        @else
            <a href="{{ config('zoho.admin_panel_url', 'https://sahayya-admin.vercel.app') }}/admin/zoho-{{ $service }}" class="btn btn-primary">Try Again</a>
        @endif
    </div>
    <script>
        // Auto-close after 3 seconds if success
        @if($success)
        setTimeout(function() {
            window.close();
        }, 3000);
        @endif
    </script>
</body>
</html>
