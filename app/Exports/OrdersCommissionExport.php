<?php

namespace App\Exports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class OrdersCommissionExport implements FromCollection, WithHeadings
{
    protected $searchData;

    public function __construct(array $searchData)
    {
        $this->searchData = $searchData;
    }

    /**
     * Prepare the collection for exporting orders with commission details.
     */
    public function collection()
    {
        $query = Order::query();

        if (!empty($this->searchData['order_number'])) {
            $query->where('orders.order_number', 'LIKE', '%' . $this->searchData['order_number'] . '%');
        }

        if (!empty($this->searchData['start_date'])) {
            $query->whereDate('orders.created_at', '>=', $this->searchData['start_date']);
        }

        if (!empty($this->searchData['end_date'])) {
            $query->whereDate('orders.created_at', '<=', $this->searchData['end_date']);
        }

        return $query->get()->map(function ($commission) {
            return [
                'Order Number' => $commission->order_number,
                'Admin Commission Amount' => $commission->admin_commission_amount,
                'Order Date' => $commission->created_at,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Order Number',
            'Admin Commission Amount',
            'Order Date',
        ];
    }
}
