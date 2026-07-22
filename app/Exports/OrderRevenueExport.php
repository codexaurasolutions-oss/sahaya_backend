<?php

namespace App\Exports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class OrderRevenueExport implements FromCollection, WithHeadings
{
    protected $searchData;

    public function __construct(array $searchData)
    {
        $this->searchData = $searchData;
    }

    public function collection()
    {
        $query = Order::query();

        // Filter the orders based on search criteria
        if (!empty($this->searchData['order_status'])) {
            $query->where('orders.status', 'LIKE', '%' . $this->searchData['order_status'] . '%');
        }

        if (!empty($this->searchData['start_date'])) {
            $query->whereDate('orders.created_at', '>=', $this->searchData['start_date']);
        }

        if (!empty($this->searchData['end_date'])) {
            $query->whereDate('orders.created_at', '<=', $this->searchData['end_date']);
        }

        return $query->get()->map(function ($order) {
            return [
                'Customer Name' => $order->user->name ?? 'N/A', 
                'Customer Email' => $order->user->email ?? 'N/A', 
                'Customer Phone Number' => $order->user->phone_number ?? 'N/A',
                'Order Number' => $order->order_number,
                'Order Status' => $order->status,
                'Payment method' => $order->method,
                'Order Receive Date' => $order->created_at->format('Y-m-d H:i:s')
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Customer Name',
            'Customer Email',
            'Customer Phone Number',
            'Order Number',
            'Order Status',
            'Payment method',
            'Order Receive Date'
           
        ];
    }
}
