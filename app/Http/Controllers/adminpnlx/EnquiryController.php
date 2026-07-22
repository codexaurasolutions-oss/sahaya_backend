<?php

namespace App\Http\Controllers\adminpnlx;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use Illuminate\Support\Facades\DB;
use App\Exports\EnquiryExport; 
use Maatwebsite\Excel\Facades\Excel;


class EnquiryController extends Controller
{
    public $model = 'Enquiries';
    public function __construct(Request $request)
    {
        parent::__construct();
        View()->share('model', $this->model);
        $this->request = $request;
    }

    public function index(Request $request)
    {
        $DB = Enquiry::query();

        $searchVariable = [];
        $inputGet = $request->all();

        if ($request->all()) {
            $searchData = $request->all();
            unset($searchData['display']);
            unset($searchData['_token']);
            if (isset($searchData['order'])) {
                unset($searchData['order']);
            }
            if (isset($searchData['sortBy'])) {
                unset($searchData['sortBy']);
            }
            if (isset($searchData['page'])) {
                unset($searchData['page']);
            }

            foreach ($searchData as $fieldName => $fieldValue) {
                if ($fieldValue != '') {
                    if ($fieldName === 'user_name') {
                        $DB->where('users.name', 'LIKE', '%' . $fieldValue . '%');
                    }

                    if ($fieldName === 'product_name') {
                        $DB->where('products.name', 'LIKE', '%' . $fieldValue . '%');
                    }

                    if ($fieldName === 'description') {
                        $DB->where('enquiries.description', 'LIKE', '%' . $fieldValue . '%');
                    }

                    if ($fieldName == 'start_date' && $fieldValue != '') {
                        $DB->whereDate('enquiries.created_at', '>=', $fieldValue);
                    }
                    
                    if ($fieldName == 'end_date' && $fieldValue != '') {
                        $DB->whereDate('enquiries.created_at', '<=', $fieldValue);
                    }
                    

                    $searchVariable[$fieldName] = $fieldValue;
                }
            }
        }

        $results = $DB->join('products', 'enquiries.product_id', '=', 'products.id')->join('users', 'enquiries.user_id', '=', 'users.id')->select('enquiries.*', 'products.name AS product_name', 'users.name AS user_name');
        $sortBy = $request->input('sortBy', 'created_at');
        $order = $request->input('order', 'DESC');
        $records_per_page = $request->input('per_page', Config('Reading.records_per_page'));
        $results = $results->orderBy($sortBy, $order)->paginate($records_per_page);
        $complete_string = $request->query();
        unset($complete_string['sortBy'], $complete_string['order']);
        $query_string = http_build_query($complete_string);
        $results->appends($inputGet);

        return view("admin.$this->model.index", compact('results', 'searchVariable', 'sortBy', 'order', 'query_string','request'));
    }

    public function show($enenqid)
    {
        $enquiry_id = !empty($enenqid) ? base64_decode($enenqid) : null;
        if (is_null($enquiry_id)) {
            return Redirect()->route($this->model . '.index');
        }
        $enquiryDetails = DB::table('enquiries')->join('products', 'enquiries.product_id', '=', 'products.id')->join('users', 'enquiries.user_id', '=', 'users.id')->select('enquiries.*', 'products.name AS product_name', 'users.name AS user_name')->where('enquiries.id', $enquiry_id)->first();
        if (!$enquiryDetails) {
            return redirect()
                ->route($this->model . '.index')
                ->with('error', 'Enquiry not found');
        }
        return view("admin.$this->model.view", compact('enquiryDetails'));
    }

    public function destroy($enenqid)
    {
        $enquiry_id = '';
        if (!empty($enenqid)) {
            $enquiry_id = base64_decode($enenqid);
        } else {
            return Redirect()->route($this->model . '.index');
        }
        $enquiryDetails = Enquiry::where('id', $enquiry_id)->first();
        $enquiryDetails->delete();
        Session()->flash('flash_notice', 'Enquiry has been removed successfully');
        return back();
    }

    public function export(Request $request)
    {
        return Excel::download(new EnquiryExport($request->all()), 'Enquiries.xlsx');
    }
    


    
}
