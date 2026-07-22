<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;

class UsersExport implements FromQuery, WithHeadings
{
    protected $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function query()
    {
        $DB = User::query();

        if (!empty($this->request->input('date_from')) && !empty($this->request->input('date_to'))) {
            $dateS = date("Y-m-d", strtotime($this->request->input('date_from')));
            $dateE = date("Y-m-d", strtotime($this->request->input('date_to')));
            $DB->whereBetween('users.created_at', [$dateS . " 00:00:00", $dateE . " 23:59:59"]);
        } elseif (!empty($this->request->input('date_from'))) {
            $dateS = $this->request->input('date_from');
            $DB->where('users.created_at', '>=', [$dateS . " 00:00:00"]);
        } elseif (!empty($this->request->input('date_to'))) {
            $dateE = $this->request->input('date_to');
            $DB->where('users.created_at', '<=', [$dateE . " 23:59:59"]);
        }

        foreach ($this->request->except(['display', '_token', 'sortBy', 'order', 'page']) as $fieldName => $fieldValue) {
            if ($fieldValue != "") {
                switch ($fieldName) {
                    case "first_name":
                        $DB->where("users.first_name", 'like', '%' . $fieldValue . '%');
                        break;
                    case "address":
                        $DB->where("users.address", 'like', '%' . $fieldValue . '%');
                        break;
                    case "last_name":
                        $DB->where("users.last_name", 'like', '%' . $fieldValue . '%');
                        break;
                    case "name":
                        $DB->where("users.name", 'like', '%' . $fieldValue . '%');
                        break;
                    case "email":
                        $DB->where("users.email", 'like', '%' . $fieldValue . '%');
                        break;
                    case "is_active":
                        if ($fieldValue == 'verified') {
                            $DB->where("users.is_verified", 1);
                        } elseif ($fieldValue == 'unverified') {
                            $DB->where("users.is_verified", 0);
                        } else {
                            $DB->where("users.is_active", 'like', '%' . $fieldValue . '%');
                        }
                        break;
                }
            }
        }

        $DB->where("users.is_deleted", 0);
        $DB->where("users.user_role_id", 2);

        return $DB;
    }

    public function headings(): array
    {
        return [
            'ID',
            'First Name',
            'Last Name',
            'Name',
            'Email',
            'Address',
            'Created At',
            'Is Verified',
            'Is Active',
        ];
    }
}
