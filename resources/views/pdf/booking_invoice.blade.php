<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Booking Invoice - {{ $booking->order_id ?? $booking->id }}</title>
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
        .customer-info, .booking-info { margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>BOOKING INVOICE</h1>
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
                    <strong>Customer Information:</strong><br>
                    {{ $booking->customer->name ?? 'N/A' }}<br>
                    Email: {{ $booking->customer->email ?? 'N/A' }}<br>
                    Customer ID: {{ $booking->customer_id }}
                </td>
                <td width="50%" align="right">
                    <strong>Vendor Information:</strong><br>
                    {{ $booking->vendor->name ?? 'N/A' }}<br>
                    Vendor ID: {{ $booking->vendor_id }}<br><br>

                    <strong>Invoice Details:</strong><br>
                    @if($booking->order_id)
                        <strong>Order ID:</strong> {{ $booking->order_id }}<br>
                    @endif
                    <strong>Booking ID:</strong> {{ $booking->id }}<br>
                    <strong>Date:</strong> {{ $booking->created_at->format('M d, Y') }}<br>
                    <strong>Status:</strong> {{ ucfirst($booking->status) }}
                </td>
            </tr>
        </table>
    </div>

    <div class="booking-info">
        <strong>Service Details:</strong><br>
        Service: {{ $booking->service->name ?? 'N/A' }}<br>
        @if($booking->schedule_time)
            <strong>Scheduled Time:</strong> 
            @if(is_array($booking->schedule_time))
                {{ implode(', ', $booking->schedule_time) }}
            @else
                {{ $booking->schedule_time }}
            @endif
            <br>
        @endif
        @if($booking->reschedule_time)
            <strong>Rescheduled Time:</strong> {{ $booking->reschedule_time->format('M d, Y H:i') }}<br>
        @endif
        @if($booking->note)
            <strong>Note:</strong> {{ $booking->note }}<br>
        @endif
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Description</th>
                <th>Amount</th>
                <th>Tax</th>
                <th>Platform Fee</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Booking Service - {{ $booking->service->name ?? 'N/A' }}</td>
                <td>${{ number_format($booking->amount, 2) }}</td>
                <td>${{ number_format($booking->tax, 2) }}</td>
                <td>${{ number_format($booking->platform_fee, 2) }}</td>
                <td><strong>${{ number_format($booking->amount + $booking->tax + $booking->platform_fee, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <div class="total-section">
        <strong>Subtotal: ${{ number_format($booking->amount, 2) }}</strong><br>
        <strong>Tax: ${{ number_format($booking->tax, 2) }}</strong><br>
        <strong>Platform Fee: ${{ number_format($booking->platform_fee, 2) }}</strong><br>
        <strong style="font-size: 14px;">Grand Total: ${{ number_format($booking->amount + $booking->tax + $booking->platform_fee, 2) }}</strong>
    </div>

    @if($booking->promo_code_id)
    <div class="promo-info">
        <strong>Promo Code Applied:</strong> {{ $booking->promo_code_id }}
    </div>
    @endif

    <div class="footer">
        <p>This is a computer-generated booking invoice. No signature required.</p>
        <p>Generated on: {{ now()->format('M d, Y H:i:s') }}</p>
        @if($booking->is_paid_key)
        <p>Payment Status: Paid</p>
        @else
        <p>Payment Status: Pending</p>
        @endif
    </div>
</body>
</html>