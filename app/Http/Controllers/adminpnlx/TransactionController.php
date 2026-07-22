<?php

namespace App\Http\Controllers\adminpnlx;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use App\Exports\TransactionsExport;
use Maatwebsite\Excel\Facades\Excel;


class TransactionController extends Controller
{
    public $model = 'Transactions';
    public function __construct(Request $request)
    {
        parent::__construct();
        View()->share('model', $this->model);
        $this->request = $request;
    }

    public function index(Request $request)
{
    $DB = Transaction::with(['user', 'order']); 
    $searchVariable = [];
    $inputGet = $request->all();

    if ($request->all()) {
        $searchData = $request->all();
        unset($searchData['display'], $searchData['_token'], $searchData['order'], $searchData['sortBy'], $searchData['page']);

        foreach ($searchData as $fieldName => $fieldValue) {
            if ($fieldValue != '') {
                if ($fieldName === 'user_name') {
                    $DB->whereHas('user', function($query) use ($fieldValue) {
                        $query->where('name', 'LIKE', '%' . $fieldValue . '%');
                    });
                }
                if ($fieldName == "order_number") { 
                    $DB->whereHas('order', function($query) use ($fieldValue) {
                        $query->where('order_number', 'like', '%' . $fieldValue . '%');
                    });
                }
                if ($fieldName == "payment_mode") { 
                    $DB->where('payment_mode', 'like', '%' . $fieldValue . '%');
                }
                if ($fieldName == "payment_status") { 
                    $DB->where('payment_status', 'like', '%' . $fieldValue . '%');
                }
                if ($fieldName == 'start_date') {
                    $DB->whereDate('transactions.created_at', '>=', $fieldValue);
                }
                if ($fieldName == 'end_date') {
                    $DB->whereDate('transactions.created_at', '<=', $fieldValue);
                }

                $searchVariable[$fieldName] = $fieldValue;
            }
        }
    }

    $sortBy = $request->input('sortBy', 'created_at');
    $order = $request->input('order', 'DESC');
    $records_per_page = $request->input('per_page', config('Reading.records_per_page'));

    if ($sortBy === 'user_name') {
        $DB->join('users', 'transactions.user_id', '=', 'users.id')
           ->orderBy('users.name', $order);
    } else {
        $DB->orderBy($sortBy, $order);
    }

    $results = $DB->paginate($records_per_page);
    $complete_string = $request->query();
    unset($complete_string['sortBy'], $complete_string['order']);
    $query_string = http_build_query($complete_string);
    $results->appends($inputGet);


    return view("admin.$this->model.index", compact('request','results', 'searchVariable', 'sortBy', 'order', 'query_string'));
}


    public function show($entrid)
    {
        $transaction_id = !empty($entrid) ? base64_decode($entrid) : null;
        if (is_null($transaction_id)) {
            return Redirect()->route($this->model . '.index');
        }
        $transactionDetails = DB::table('transactions')->join('users', 'transactions.user_id', '=', 'users.id')->join('orders', 'transactions.order_id', '=', 'orders.id')->select('transactions.*', 'users.name AS user_name', 'orders.order_number AS order_number')->where('transactions.id', $transaction_id)->first();

        if (!$transactionDetails) {
            return redirect()
                ->route('transactions.index')
                ->with('error', 'Enquiry not found');
        }
        return view("admin.$this->model.view", compact('transactionDetails'));
    }

   

    public function export(Request $request)
    {
        $searchData = $request->all();
        unset($searchData['display'], $searchData['_token'], $searchData['sortBy'], $searchData['order'], $searchData['page']);
        return Excel::download(new TransactionsExport($searchData), 'Transactions.xlsx');
    }

}
