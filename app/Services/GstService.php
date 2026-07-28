<?php

namespace App\Services;

class GstService
{
    const GST_RATE = 18.0;

    public static function calculate(float $baseAmount): array
    {
        $gstAmount = round($baseAmount * self::GST_RATE / 100, 2);
        $totalAmount = $baseAmount + $gstAmount;

        return [
            'base_amount' => $baseAmount,
            'gst_rate' => self::GST_RATE,
            'gst_amount' => $gstAmount,
            'total_amount' => $totalAmount,
        ];
    }

    public static function extractGst(float $totalAmount): array
    {
        $baseAmount = round($totalAmount / (1 + self::GST_RATE / 100), 2);
        $gstAmount = $totalAmount - $baseAmount;

        return [
            'base_amount' => $baseAmount,
            'gst_rate' => self::GST_RATE,
            'gst_amount' => $gstAmount,
            'total_amount' => $totalAmount,
        ];
    }

    public static function formatInvoiceText(string $itemName, float $baseAmount, float $gstAmount, float $totalAmount, string $orderId, string $date): string
    {
        $companyName = config('app.name', 'Sahayya');
        $gstNumber = config('app.gst_number', 'N/A');

        return "*{$companyName} - Payment Invoice*\n\n" .
            "Invoice #: {$orderId}\n" .
            "Date: {$date}\n" .
            "-----------------------------------\n" .
            "Item: {$itemName}\n" .
            "Base Amount: ₹" . number_format($baseAmount, 2) . "\n" .
            "GST (18%): ₹" . number_format($gstAmount, 2) . "\n" .
            "-----------------------------------\n" .
            "*Total Paid: ₹" . number_format($totalAmount, 2) . "*\n\n" .
            "GSTIN: {$gstNumber}\n" .
            "Thank you for your payment! 🙏";
    }
}
