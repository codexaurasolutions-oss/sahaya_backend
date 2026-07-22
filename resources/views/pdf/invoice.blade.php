<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice - {{ $transaction->order_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
        .company-info { margin-bottom: 20px; }
        .invoice-details { margin-bottom: 30px; }
        .table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .table th { background-color: #f5f5f5; }
        .total-section { text-align: right; margin-top: 20px; }
        .footer { margin-top: 50px; text-align: center; font-size: 10px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>INVOICE</h1>
        <div class="company-info">
            <strong>{{ $company['name'] }}</strong><br>
            {{ $company['address'] }}<br>
            Phone: {{ $company['phone'] }} | Email: {{ $company['email'] }}
        </div>
    </div>

    <div class="invoice-details">
        <table width="100%">
            <tr>
                <td width="50%">
                    <strong>Bill To:</strong><br>
                    {{ $transaction->user->name ?? 'N/A' }}<br>
                    {{ $transaction->user->email ?? 'N/A' }}
                </td>
                <td width="50%" align="right">
                    <strong>Invoice Number:</strong> {{ $transaction->order_number }}<br>
                    <strong>Date:</strong> {{ $transaction->created_at->format('M d, Y') }}<br>
                    <strong>Transaction ID:</strong> {{ $transaction->transaction_id ?? 'N/A' }}
                </td>
            </tr>
        </table>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Description</th>
                <th>Order Number</th>
                <th>Payment Mode</th>
                <th>Currency</th>
                <th>Amount</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ ucfirst($transaction->type) }} Transaction</td>
                <td>{{ $transaction->order_number }}</td>
                <td>{{ $transaction->payment_mode ?? 'N/A' }}</td>
                <td>{{ $transaction->currency }}</td>
                <td>{{ number_format($transaction->amount, 2) }}</td>
                <td>{{ ucfirst($transaction->payment_status) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="total-section">
        <strong>Total Amount: {{ $transaction->currency }} {{ number_format($transaction->amount, 2) }}</strong>
    </div>

    <div class="footer">
        <p>This is a computer-generated invoice. No signature required.</p>
        <p>Generated on: {{ now()->format('M d, Y H:i:s') }}</p>
    </div>
</body>
</html>