<?php

namespace App\Http\Controllers\adminpnlx;

use App\Models\Coupon;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Validation\Rule;



class CouponController extends Controller
{

    public $model = 'coupon';
    public function __construct(Request $request){
        parent::__construct();
        View()->share('model', $this->model);
        $this->request = $request;
    }
    
    public function index(Request $request) {
        $DB = Coupon::query(); 
        $searchVariable = array();
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
                if ($fieldValue != "") {
                    
                    if ($fieldName == "code" && $fieldValue != '') { 
                        $DB->where("code", 'like', '%' . $fieldValue . '%');
                    }
                    if ($fieldName == "title" && $fieldValue != '') {
                        $DB->where("title", 'like', '%' . $fieldValue . '%');
                    }
                    if ($fieldName == "short_title" && $fieldValue != '') {
                        $DB->where("short_title", 'like', '%' . $fieldValue . '%');
                    }
                    if ($fieldName == "status" && $fieldValue != '') {
                        $DB->where("status", 'like', '%' . $fieldValue . '%');
                    }
                    if ($fieldName == "start_date") {
                        $DB->whereDate('start_date', '>=', $fieldValue);
                    }
                    if ($fieldName == "end_date") {
                        $DB->whereDate('end_date', '<=', $fieldValue);
                    }
                    if ($request->has('quantity') && $request->quantity !== '') {
                        if ($request->quantity == 'limited') {
                            $DB->where('quantity', '=', 'limited'); 
                        } elseif ($request->quantity == 'unlimited') {
                            $DB->where('quantity', '=', 'unlimited'); 
                        }
                    }
                    
                }
                $searchVariable = array_merge($searchVariable, array($fieldName => $fieldValue));
            }
        }
    
        $DB->where('is_deleted', 0);
        $sortBy = ($request->input('sortBy')) ? $request->input('sortBy') : 'created_at';
        $order = ($request->input('order')) ? $request->input('order') : 'DESC';
        $records_per_page = ($request->input('per_page')) ? $request->input('per_page') : Config("Reading.records_per_page");
        $results = $DB->orderBy($sortBy, $order)->paginate($records_per_page);
        $complete_string = $request->query();
        unset($complete_string["sortBy"]);
        unset($complete_string["order"]);
        $query_string = http_build_query($complete_string);
        $results->appends($inputGet)->render();
        return view("admin.coupon.index", compact('results', 'searchVariable', 'sortBy', 'order', 'query_string'));
    }

    public function create()
    {
        return view("admin.$this->model.add");
    }

    public function store(Request $request)
    {
       $validator = Validator::make($request->all(), [
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('coupons')->where(function ($query) {
                    return $query->where('is_deleted', 0);
                }),
            ],
          //  'short_title'     => 'required|string|max:255',
            'title'           => 'required|string|max:255',
            'type'            => 'required|string',
            'per_person_use'  => 'nullable|numeric',
            'max_uses'        => 'required|numeric|gt:per_person_use',
            'min_amount'      => 'required|numeric',
            'maximum_amount'    => 'required|numeric|gt:min_amount',

            'start_date'      => 'required|string|date_format:m/d/Y h:i A',
            'end_date'        => 'required|string|date_format:m/d/Y h:i A|after:start_date',
        ]);
        $validator->sometimes('min_amount', 'gt:is_amount', function ($input) {
            return isset($input->is_amount); 
        });        
        if ($request->type === 'discount_by_per') {
            $validator->sometimes('is_per', 'required|numeric|between:0,100', function ($input) {
                return $input->type === 'discount_by_per';
            });
        } elseif ($request->type === 'discount_by_amount') {
            $validator->sometimes('is_amount', 'required|numeric', function ($input) {
                return $input->type === 'discount_by_amount'; 
            });
        }        
        $validator->setCustomMessages([
            'min_amount.gt' => 'The minimum amount must be greater than the amount.',
            'is_per.required' => 'The percentage field is required.',
            'is_per.numeric'  => 'The percentage must be a number.',
            'is_per.between'  => 'The percentage must be between 0 and 100.',
            'is_amount.required' => 'The amount field is required.',
            'is_amount.numeric'  => 'The amount must be a number.',
            'is_amount.gt' => 'The amount must be greater than the minimum amount.',
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }
        
        $coupon = new Coupon();
    
        $startDatetime         = \Carbon\Carbon::createFromFormat('m/d/Y h:i A', $request->start_date);
        $coupon->start_date    = $startDatetime->toDateString(); 
        $coupon->start_time    = $startDatetime->toTimeString();
    
        $endDatetime           = \Carbon\Carbon::createFromFormat('m/d/Y h:i A', $request->end_date);
        $coupon->end_date      = $endDatetime->toDateString(); 
        $coupon->end_time      = $endDatetime->toTimeString();
    
        $coupon->code          = $request->code;
        $coupon->short_title   = $request->short_title;
        $coupon->title         = $request->title;
        $coupon->type          = $request->type;
        $coupon->maximum_amount = $request->maximum_amount;
        $coupon->min_amount    = $request->min_amount;
        
        if ($request->type === 'discount_by_per') {
            $coupon->is_per = $request->is_per;
            $coupon->is_amount = null;  
        } elseif ($request->type === 'discount_by_amount') {
            $coupon->is_amount = $request->is_amount;
            $coupon->is_per = null; 
        }
        $coupon->quantity = $request->quantity;
        $coupon->per_person_use = $request->per_person_use;
        $coupon->max_uses       = $request->max_uses;
        $coupon->save();
    
        return redirect()->route('coupons.index')->with('success', 'Coupon added successfully.');
    }
    

    public function edit($enfaqid){
        $coupon_id = '';
        
        if (!empty($enfaqid)) {
            $coupon_id          = base64_decode($enfaqid);
            $coupon             =   Coupon::find($coupon_id);
            $Startdatetime      = $coupon->start_date . ' ' .$coupon->start_time;
            $Enddatetime        = $coupon->end_date . ' ' .$coupon->end_time;

            return View("admin.$this->model.edit", compact('coupon','Startdatetime','Enddatetime'));
        } else {
            return Redirect()->route($this->model . ".index");
        }
    }

    public function update(Request $request, $enfaqid)
    {
        $coupon_id = base64_decode($enfaqid);
        $coupon = Coupon::find($coupon_id);
        if (!$coupon) {
            return redirect()->route('coupons.index')->with('error', 'Coupon not found.');
        }
        $validator = Validator::make($request->all(), [
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('coupons')->where(function ($query) {
                    return $query->where('is_deleted', 0);
                })->ignore($coupon_id),
            ],
        //    'short_title'     => 'required|string|max:255',
            'title'           => 'required|string|max:255',
            'type'            => 'required|string',
            'per_person_use'  => 'nullable|numeric',
            'max_uses'        => 'required|numeric|gt:per_person_use',
            'min_amount'      => 'required|numeric',
            'maximum_amount'    => 'required|numeric|gt:min_amount',

            'start_date'      => 'required|string|date_format:m/d/Y h:i A',
            'end_date'        => 'required|string|date_format:m/d/Y h:i A|after:start_date',
        ]);
        $validator->sometimes('min_amount', 'gt:is_amount', function ($input) {
            return isset($input->is_amount); 
        });        
        if ($request->type === 'discount_by_per') {
            $validator->sometimes('is_per', 'required|numeric|between:0,100', function ($input) {
                return $input->type === 'discount_by_per';
            });
        } elseif ($request->type === 'discount_by_amount') {
            $validator->sometimes('is_amount', 'required|numeric', function ($input) {
                return $input->type === 'discount_by_amount'; 
            });
        }        
        $validator->setCustomMessages([
            'min_amount.gt' => 'The minimum amount must be greater than the amount.',
            'is_per.required' => 'The percentage field is required.',
            'is_per.numeric'  => 'The percentage must be a number.',
            'is_per.between'  => 'The percentage must be between 0 and 100.',
            'is_amount.required' => 'The amount field is required.',
            'is_amount.numeric'  => 'The amount must be a number.',
            'is_amount.gt' => 'The amount must be greater than the minimum amount.',
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }
        
        $startDatetime        = \Carbon\Carbon::createFromFormat('m/d/Y h:i A', $request->start_date);
        $coupon->start_date   = $startDatetime->toDateString(); 
        $coupon->start_time   = $startDatetime->toTimeString();
      
        $endDatetime          = \Carbon\Carbon::createFromFormat('m/d/Y h:i A', $request->end_date);
        $coupon->end_date     = $endDatetime->toDateString(); 
        $coupon->end_time     = $endDatetime->toTimeString();
      
        $coupon->code         = $request->code;
        $coupon->short_title  = $request->short_title;
        $coupon->title        = $request->title;
        $coupon->type         = $request->type;
        $coupon->min_amount   = $request->min_amount;
        $coupon->maximum_amount = $request->maximum_amount;


        
        if ($request->type === 'discount_by_per') {
            $coupon->is_per = $request->is_per;
            $coupon->is_amount = null;  
        } elseif ($request->type === 'discount_by_amount') {
            $coupon->is_amount = $request->is_amount;
            $coupon->is_per = null; 
        }
        $coupon->quantity = $request->quantity;
        $coupon->per_person_use = $request->per_person_use;
        $coupon->max_uses       = $request->max_uses;
        $coupon->save();
    
        return redirect()->route('coupons.index')->with('success', 'Coupon updated successfully.');
    }

    public function show($endesid) {
        $coupon_id = '';
        if (!empty($endesid)) {
            $coupon_id = base64_decode($endesid);
        } else {
            return Redirect()->route($this->model . ".index");
        }
        $CouponDetails = Coupon::find($coupon_id);
    
        if ($CouponDetails) {
            $CouponDetails->start_date    = Carbon::parse($CouponDetails->start_date)->format('m/d/Y');
            $CouponDetails->end_date      = Carbon::parse($CouponDetails->end_date)->format('m/d/Y');
        }
        $data = compact('CouponDetails');
        return view("admin.$this->model.view", $data);
    }
    
    public function changeStatus($modelId = 0, $status = 0)
    {
        $coupon = Coupon::find($modelId);
        if ($coupon) {
            $currentStatus = $coupon->status;
            if (isset($currentStatus) && $currentStatus == 0) {
                $NewStatus = 1;
            } else {
                $NewStatus = 0;
            }
            if ($NewStatus == 1) {
                $statusMessage = trans(Config('constants.COUPON.COUPON_TITLE') . ' has been activated successfully');
            } else {
                $statusMessage = trans(Config('constants.COUPON.COUPON_TITLE') . ' has been deactivated successfully');
            }
            $coupon->status = $NewStatus;
            $ResponseStatus = $coupon->save();
        }
        Session()->flash('flash_notice', $statusMessage);
        return back();
    }

    public function destroy($enfaqid){
        $coupon_id = '';
        if (!empty($enfaqid)) {
            $coupon_id = base64_decode($enfaqid);
        } else {
            return Redirect()->route($this->model . ".index");
        }
        $couponDetails               =  Coupon::where('id', $coupon_id)->first();
        $couponDetails->is_deleted   = 1;
        $couponDetails->save();
        Session()->flash('flash_notice', trans(Config('constants.COUPON.COUPON_TITLE') . " has been removed successfully"));
        return back();
    }
}
