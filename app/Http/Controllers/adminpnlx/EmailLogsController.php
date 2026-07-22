<?php
namespace App\Http\Controllers\adminpnlx;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\EmailLog;

class EmailLogsController extends Controller
{
    public $model      =   'emaillogs';
    public function __construct(Request $request){
		parent::__construct();
      	View()->share('model', $this->model);
      	$this->request = $request;
   }

    public function index(Request $request){
        $DB				=	EmailLog::query();
		$searchVariable	=	array(); 
		$inputGet		=	$request->input();
		if ($request->all()){
			$searchData	=	$request->input();
			unset($searchData['display']);
			unset($searchData['_token']);

			if(isset($searchData['order'])){
				unset($searchData['order']);
			}
			if(isset($searchData['sortBy'])){
				unset($searchData['sortBy']);
			}
			
			if(isset($searchData['page'])){
				unset($searchData['page']);
			}
			if((!empty($searchData['date_from'])) && (!empty($searchData['date_to']))){
				$dateS = $searchData['date_from'];
				$dateE = $searchData['date_to'];
				$DB->whereBetween('email_logs.created_at', [$dateS." 00:00:00", $dateE." 23:59:59"]); 											
			}elseif(!empty($searchData['date_from'])){
				$dateS = $searchData['date_from'];
				$DB->where('email_logs.created_at','>=' ,[$dateS." 00:00:00"]); 
			}elseif(!empty($searchData['date_to'])){
				$dateE = $searchData['date_to'];
				$DB->where('email_logs.created_at','<=' ,[$dateE." 00:00:00"]); 						
			}
			foreach($searchData as $fieldName => $fieldValue){
				if($fieldName == "email_to" || $fieldName == "email_from" || $fieldName == "subject" || $fieldName == "message"){
					if(!empty($fieldValue)){
						$DB->where("$fieldName",'like','%'.$fieldValue.'%');
					}
				}
					$searchVariable	=	array_merge($searchVariable,array($fieldName => $fieldValue));	
			}
		}
		$sortBy = ($request->input('sortBy')) ? $request->input('sortBy') : 'created_at';
	    $order  = ($request->input('order')) ? $request->input('order')   : 'DESC';
        $records_per_page   =   ($request->input('per_page')) ? $request->input('per_page') : Config("Reading.records_per_page");   
		$results = $DB->orderBy($sortBy, $order)->paginate($records_per_page);
		$complete_string		=	$request->query();
		unset($complete_string["sortBy"]);
		unset($complete_string["order"]);
		$query_string			=	http_build_query($complete_string);
		$results->appends($request->all())->render();
       return View("admin.$this->model.index",compact('results','searchVariable','sortBy','order','query_string'));
    }

    public function emailDetail(Request $request, $enmailid=null){
         $result= EmailLog::find($enmailid);
        return View("admin.$this->model.popup",compact('result'));
    }
}
