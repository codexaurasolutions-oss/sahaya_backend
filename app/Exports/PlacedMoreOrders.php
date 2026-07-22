<?php

namespace App\Exports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PlacedMoreOrders implements FromQuery, WithHeadings
{
    protected $searchData;

    public function __construct(array $searchData)
    {
        $this->searchData = $searchData;
    }

    /**
     * Prepare the query for exporting orders with user details.
     */
    public function query()
    {
        $DB = Order::query()
            ->join('users', 'orders.user_id', '=', 'users.id')
            ->select('users.id as user_id', 'users.email', 'users.phone_number')
            ->groupBy('users.id', 'users.email', 'users.phone_number');

        // Apply search filters
        if (!empty($this->searchData['email'])) {
            $DB->where('users.email', 'LIKE', '%' . $this->searchData['email'] . '%');
        }

        if (!empty($this->searchData['phone_number'])) {
            $DB->where('users.phone_number', 'LIKE', '%' . $this->searchData['phone_number'] . '%');
        }

        if (!empty($this->searchData['start_date'])) {
            $DB->whereDate('orders.created_at', '>=', $this->searchData['start_date']);
        }

        if (!empty($this->searchData['end_date'])) {
            $DB->whereDate('orders.created_at', '<=', $this->searchData['end_date']);
        }

        return $DB;
    }

    /**
     * Define the column headings for the export file.
     */
    public function headings(): array
    {
        return [
            'User ID',
            'Email',
            'Phone Number',
        ];
    }
}
