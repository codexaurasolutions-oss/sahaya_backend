<?php
namespace App\Exports;

use App\Models\Enquiry;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EnquiryExport implements FromCollection, WithHeadings
{
    protected $searchData;

    public function __construct(array $searchData)
    {
        $this->searchData = $searchData;
    }

    public function collection()
    {
        $query = Enquiry::with(['product', 'user'])
            ->join('products', 'enquiries.product_id', '=', 'products.id')
            ->join('users', 'enquiries.user_id', '=', 'users.id')
            ->select('enquiries.*', 'products.name AS product_name', 'users.name AS user_name');

        if (!empty($this->searchData['user_name'])) {
            $query->where('users.name', 'LIKE', '%' . $this->searchData['user_name'] . '%');
        }

        if (!empty($this->searchData['product_name'])) {
            $query->where('products.name', 'LIKE', '%' . $this->searchData['product_name'] . '%');
        }

        if (!empty($this->searchData['description'])) {
            $query->where('enquiries.description', 'LIKE', '%' . $this->searchData['description'] . '%');
        }

        if (!empty($this->searchData['start_date'])) {
            $query->whereDate('enquiries.created_at', '>=', $this->searchData['start_date']);
        }

        if (!empty($this->searchData['end_date'])) {
            $query->whereDate('enquiries.created_at', '<=', $this->searchData['end_date']);
        }

        return $query->get()->map(function ($enquiry) {
            return [
                'ID' => $enquiry->id,
                'Product Name' => $enquiry->product_name,
                'User Name' => $enquiry->user_name,
                'Description' => $enquiry->description,
                'Created At' => $enquiry->created_at,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'ID',
            'Product Name',
            'User Name',
            'Description',
            'Created At',
        ];
    }
}
