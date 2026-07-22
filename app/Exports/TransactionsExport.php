<?php

namespace App\Exports;

use App\Models\Transaction;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TransactionsExport implements FromCollection, WithHeadings
{
    protected $searchData;

    public function __construct(array $searchData)
    {
        $this->searchData = $searchData;
    }

    public function collection()
    {
        $query = Transaction::with(['user', 'order'])
            ->join('users', 'transactions.user_id', '=', 'users.id')
            ->join('orders', 'transactions.order_id', '=', 'orders.id')
            ->select('transactions.*', 'users.name AS user_name', 'orders.order_number');

        if (!empty($this->searchData['user_name'])) {
            $query->where('users.name', 'LIKE', '%' . $this->searchData['user_name'] . '%');
        }

        if (!empty($this->searchData['order_number'])) {
            $query->where('orders.order_number', 'LIKE', '%' . $this->searchData['order_number'] . '%');
        }

        if (!empty($this->searchData['payment_mode'])) {
            $query->where('transactions.payment_mode', 'LIKE', '%' . $this->searchData['payment_mode'] . '%');
        }

        if (!empty($this->searchData['payment_status'])) {
            $query->where('transactions.payment_status', 'LIKE', '%' . $this->searchData['payment_status'] . '%');
        }

        if (!empty($this->searchData['start_date'])) {
            $query->whereDate('transactions.created_at', '>=', $this->searchData['start_date']);
        }

        if (!empty($this->searchData['end_date'])) {
            $query->whereDate('transactions.created_at', '<=', $this->searchData['end_date']);
        }

        return $query->get()->map(function ($transaction) {
            return [
                'Transaction ID' => $transaction->id,
                'User Name' => $transaction->user_name,
                'Order Number' => $transaction->order_number,
                'Payment Mode' => $transaction->payment_mode,
                'Payment Status' => $transaction->payment_status,
                'Created At' => $transaction->created_at,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Transaction ID',
            'User Name',
            'Order Number',
            'Payment Mode',
            'Payment Status',
            'Created At',
        ];
    }
}
