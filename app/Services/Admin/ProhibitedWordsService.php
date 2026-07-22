<?php

namespace App\Services\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProhibitedWord;
use App\Models\Venue;
use App\Model\CategoryVenue;
use App\Models\Lookup;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Auth, Blade, Config, Cache, Cookie, DB, File, Hash, Input, Mail, Redirect, Response, Session, URL, View, Validator;

class ProhibitedWordsService
{
    
    protected $controller;
    public $model		=	'ProhibitedWord';
	public $sectionName	=	'Prohibited Words';
    public $sectionNameSingular	= 'Prohibited Word';

    public function __construct(Controller $controller)
    {
        $this->controller = $controller;
        View::share('modelName', $this->model);
		View::share('sectionName', $this->sectionName);
		View::share('sectionNameSingular', $this->sectionNameSingular);
    }

    public function index(Request $request){
        $result                 = [];
        $DB					    =	ProhibitedWord::query();
		$searchVariable		    =	array();
		$inputGet			    =	$request->all();
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
                $dateS = date("Y-m-d", strtotime($searchData['date_from']));
                $dateE = date("Y-m-d", strtotime($searchData['date_to']));
                $DB->whereBetween('prohibited_words.created_at', [$dateS . " 00:00:00", $dateE . " 23:59:59"]);
            } elseif (!empty($searchData['date_from'])) {
                $dateS = $searchData['date_from'];
                $DB->where('prohibited_words.created_at', '>=', [$dateS . " 00:00:00"]);
            } elseif (!empty($searchData['date_to'])) {
                $dateE = $searchData['date_to'];
                $DB->where('prohibited_words.created_at', '<=', [$dateE . " 00:00:00"]);
            }
			foreach ($searchData as $fieldName => $fieldValue) {
				if ($fieldValue    != "") {
					if ($fieldName == "word") {
						$DB->where("word", 'like', '%' . $fieldValue . '%');
					}
					if ($fieldName == "is_active") {
                        $DB->where("prohibited_words.status", 'like', '%' . $fieldValue . '%');
                    }
				}
				$searchVariable	   =	array_merge($searchVariable, array($fieldName => $fieldValue));
			}
		}
		$sortBy                 = ($request->input('sortBy')) ? $request->input('sortBy') : 'created_at';
		$order                  = ($request->input('order')) ? $request->input('order')   : 'DESC';
		$records_per_page	    =	($request->input('per_page')) ? $request->input('per_page') : Config::get("Reading.records_per_page");
		$results                = $DB->orderBy($sortBy, $order)->paginate($records_per_page);
		$complete_string		=	$request->query();
		unset($complete_string["sortBy"]);
		unset($complete_string["order"]);
		$query_string			=	http_build_query($complete_string);
		$results->appends($inputGet)->render();
        $result                 = ['status' => true ,'results' => $results, 'searchVariable' => $searchVariable, 'sortBy' => $sortBy, 'order' => $order, 'query_string' => $query_string];
        return $result;

    }
    public function save(Request $request){
        $resutl             = [];
        $obj 			    =  new ProhibitedWord;
	    $obj->word 			=    $request->input('word');
        $obj->status 	    =   1;
	    $obj->save();
	    $userId			    =  $obj->id;
	    if (!$userId) {
            $result   = ['status' => false, 'message' => trans("Something went wrong.")];
            return  $result;
	    }
        $result   = ['status' => true, 'message' => trans($this->sectionNameSingular . " has been added successfully.")];
        return    $result;
    }

    public function changeStatus($id){
        $result           = [];
        $status = ProhibitedWord::where('id', $id)->value('status');
		if ($status == '1') {
			ProhibitedWord::where('id', $id)->update(['status' => '0']);
            $result   = ['status' => true, 'message' => trans($this->sectionNameSingular . " has been deactivated successfully")];
            return $result;			
		} else {
			ProhibitedWord::where('id', $id)->update(['status' => '1']);
            $result     = ['status' => true, 'message' => trans($this->sectionNameSingular . " has been activated successfully")];
            return $result;
		}
    }

    public function update(Request $request,$modelId){
        $result                     = [];
        $obj 						=  ProhibitedWord::find($modelId);
	    $obj->word 			        =    $request->input('word');
        $obj->status 	            =   1;
	    $obj->save();
	    $userId						=	$obj->id;
	    if (!$userId) {
            $result                 = ['status' => false,'message' => trans("Something went wrong.")];
            return $result;
	    }
        $result   = ['status' => true, 'message' => trans($this->sectionNameSingular . " has been updated successfully")];
        return $result;
    }




}