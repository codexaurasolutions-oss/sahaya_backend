<?php

namespace App\Http\Controllers\adminpnlx;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Config;
use App\Models\Plan;
use App\Models\Acl;
use App\Models\AclAdminAction;
use App\Models\AclDescription;
use App\Models\Language;

class PlanController extends Controller
{
	public $model =	'subscription-plans';
	public function __construct(Request $request)
	{	
		parent::__construct();
		View()->share('model', $this->model);
	}

	public function index(Request $request)
	{
		$DB 					= 	Plan::query();
		$searchVariable			=	array();
		$inputGet				=	$request->all();
		if ($request->all()) {
			$searchData			=	$request->all();
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
            if ((!empty($searchData['date_from'])) && (!empty($searchData['date_to']))) {
                $dateS = date("Y-m-d",strtotime($searchData['date_from']));
                $dateE =  date("Y-m-d",strtotime($searchData['date_to']));
                $DB->whereBetween('plan.created_at', [$dateS . " 00:00:00", $dateE . " 23:59:59"]);
            } elseif (!empty($searchData['date_from'])) {
                $dateS = $searchData['date_from'];
                $DB->where('plan.created_at', '>=', [$dateS . " 00:00:00"]);
            } elseif (!empty($searchData['date_to'])) {
                $dateE = $searchData['date_to'];
                $DB->where('plan.created_at', '<=', [$dateE . " 00:00:00"]);
            }
			foreach ($searchData as $fieldName => $fieldValue) {
				if ($fieldName == "title") {
					$DB->where("plan.title", 'LIKE', '%' . $fieldValue . '%');
				}
                if ($fieldName == "price") {
					$DB->where("plan.price", 'LIKE', '%' . $fieldValue . '%');
				}
				$searchVariable = array_merge($searchVariable, array($fieldName => $fieldValue));
			}
		}
        $DB->where('is_deleted',0);
		$sortBy = ($request->input('sortBy')) ? $request->input('sortBy') : 'plan.created_at';
		$order  = ($request->input('order')) ? $request->input('order')   : 'DESC';
		$records_per_page  =   ($request->input('per_page')) ? $request->input('per_page') : Config("Reading.records_per_page");
		$results = $DB->orderBy($sortBy, $order)->paginate($records_per_page);
		$complete_string =  $request->query();
		unset($complete_string["sortBy"]);
		unset($complete_string["order"]);
		$query_string  =   http_build_query($complete_string);
		$results->appends($inputGet)->render();
		$resultcount = $results->count();
		$parent_list 	= 	Plan::get();
		return View("admin.$this->model.index", compact('results', 'searchVariable', 'sortBy', 'order', 'query_string', 'parent_list'));
	}	

	public function create()
	{
		$parent_list =  Acl::get();
		return View("admin.$this->model.add", compact('parent_list'));
	}

	public function store(Request $request)
	{
		$validated = $request->validate([
			'title' => 'required',
			'descripition' => 'required',
			'price'  => 'required|numeric',
            'duration' => 'required'
		],
		[
			'title.required' => trans("messages.The_title_field_is_required"),
			'descripition.required' => trans("messages.The_description_field_is_required"),
			'price.required' => trans("messages.The_price_field_is_required"),
            'duration.required' => trans("messages.The_duration_field_is_required"),
			'price.numeric' => trans("messages.The_price_must_be_a_number"),
		]
	);
		$obj                  =  new Plan;
		$obj->title           =  $request->title;
		$obj->descripition    =  $request->descripition;
		$obj->price           =  $request->price;
		$obj->duration        =  $request->duration;
		$SavedResponse = $obj->save();
		if (!$SavedResponse) {
			Session()->flash('error', trans("Something went wrong."));
			return Redirect()->back()->withInput();
		} else {
			Session()->flash('success', "Subscription Plan added successfully");
			return Redirect()->route($this->model . ".index");
		}
	}

	public function edit($enaclid)
	{
		$acl_id = '';
		if (!empty($enaclid)) {
			$acl_id = base64_decode($enaclid);
			$planDetails   =  Plan::find($acl_id);
			return  View("admin.$this->model.edit", compact( 'acl_id', 'planDetails'));
		} else {
			return redirect()->route($this->model . ".index");
		}
	}

	public function update(Request $request, $enaclid)
	{
		$acl_id = '';
		if (!empty($enaclid)) {
			$acl_id = base64_decode($enaclid);
		} else {
			return redirect()->route($this->model . ".index");
		}
		$thisData = $request->all();
		$validated = $request->validate([
			'title' => 'required',
			'descripition' => 'required',
			'price'  => 'required|numeric',
            'duration' => 'required'
		],
		[
			'title.required' => trans("messages.The_title_field_is_required"),
			'descripition.required' => trans("messages.The_description_field_is_required"),
			'price.required' => trans("messages.The_price_field_is_required"),
            'duration.required' => trans("messages.The_duration_field_is_required"),
			'price.numeric' => trans("messages.The_price_must_be_a_number"),
		]);
		$obj                        =  Plan::find($acl_id);
		$obj->title                 =  $request->title;
		$obj->descripition          =  $request->descripition;
		$obj->price                 =  $request->price;
		$obj->duration              =  $request->duration;
		$SavedResponse = $obj->save();
		if (!$SavedResponse) {
			Session()->flash('error', trans("Something went wrong."));
			return Redirect()->back()->withInput();
		} else {
			Session()->flash('success', "Subscription Plan updated successfully");
			return Redirect()->route($this->model . ".index");
		}
	}

	public function destroy($enaclid)
	{
		$acl_id = '';
		if (!empty($enaclid)) {
			$acl_id = base64_decode($enaclid);
		}
		$planDetails   =  Plan::find($acl_id);
		if ($planDetails) {
            $planDetails->is_deleted  = 1;
            $planDetails->save();
			Session()->flash('flash_notice', "Subscription Plan removed successfully");
		}
		return back();
	}

	public function changeStatus($modelId = 0, $status = 0)
	{
		if ($status == 0) {
			$statusMessage   =   "Subscription Plan deactivated successfully";
		} else {
			$statusMessage   =   "Subscription Plan activated successfully";
		}
		$user = Plan::find($modelId);
		if ($user) {
			$currentStatus = $user->is_active;
			if (isset($currentStatus) && $currentStatus == 0) {
				$NewStatus = 1;
			} else {
				$NewStatus = 0;
			}
			$user->is_active = $NewStatus;
			$ResponseStatus = $user->save();
		}
		Session()->flash('flash_notice', $statusMessage);
		return back();
	}

	
	public function delete_function($id,Request $request){
		AclAdminAction::where('function_name', $id)->delete();
       return back();
    }





}
