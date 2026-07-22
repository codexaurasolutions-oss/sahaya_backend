<?php

namespace App\Http\Controllers\adminpnlx;

use App\Http\Controllers\Controller;
use App\Models\ProhibitedWord;
use App\Models\Venue;
use App\Model\CategoryVenue;
use App\Models\Lookup;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Services\Admin\ProhibitedWordsService;
use Auth, Blade, Config, Cache, Cookie, DB, File, Hash, Input, Mail, Redirect, Response, Session, URL, View, Validator;

/**
 * CountriesController Controller
 *
 * Add your methods in the class below
 *
 */
class ProhibitedWordsController extends Controller
{

	public $model		=	'ProhibitedWord';
	public $sectionName	=	'Prohibited Words';
	public $sectionNameSingular	= 'Prohibited Word';

	public function __construct(Request $request,ProhibitedWordsService $ProhibitedWordsService)
	{
		parent::__construct();
		View::share('modelName', $this->model);
		View::share('sectionName', $this->sectionName);
		View::share('sectionNameSingular', $this->sectionNameSingular);
		$this->request                = $request;
		$this->ProhibitedWordsService = $ProhibitedWordsService;
	}

	/**
	 * Function for display all Customers 
	 *
	 * @param null
	 *
	 * @return view page. 
	 */
	public function index(Request $request)
	{
		$result              = $this->ProhibitedWordsService->index($request);
		if($result['status'] == true){
			$results              = $result['results'];
			$searchVariable      = $result['searchVariable'];
			$sortBy              = $result['sortBy'];
			$order               = $result['order'];
			$query_string        = $result['query_string'];
			return  View::make("admin.$this->model.index", compact('results', 'searchVariable', 'sortBy', 'order', 'query_string'));
		}else{
             return back();
		}
	} // end index()
	public function add()
	{
		return  View::make("admin.$this->model.add");
	} // end add()

	public function save(Request $request)
	{
		$this->request->replace($this->arrayStripTags($request->all()));
		$formData						=	$request->all();
		if (!empty($formData)) {

			$validator 					=	Validator::make(
				$request->all(),
				array(
					'word'		    => 'required',
					//			'type'		    => 'required'
				),
				array(
					"word.required"			=>	trans("The word field is required."),
			

				)
			);
			if ($validator->fails()) {
				return Redirect::back()->withErrors($validator)->withInput();
			} else {
				$result         = $this->ProhibitedWordsService->save($request);
				if($result['status']== true){
					return Redirect::route($this->model . ".index")->with(['success' => $result['message']]);
				}else{
					return Redirect::back()->with(['error' => $result['message']]);
				}
				
			}
		}
	} 
	public function changeStatus($id)
	{
		$result    = $this->ProhibitedWordsService->changeStatus($id);
		if($result['status'] == true){
			return Redirect::route($this->model . ".index")->with(['success' => $result['message']]);
		}else{
			return Redirect::back()->with(['error' => $result['message']]);
		}
	} // end changeStatus()

	public function edit(Request $request, $modelId = 0)
	{
		$model		=	ProhibitedWord::where('id', $modelId)->first();
		if (empty($model)) {
			return Redirect::back();
		}
		return View::make("admin.$this->model.edit", compact('model'));
	} // end edit()

	function update($modelId, Request $request)
	{
		$model					=	ProhibitedWord::findorFail($modelId);
		if (empty($model)) {
			return Redirect::back();
		}
		$this->request->replace($this->arrayStripTags($request->all()));
		$formData						=	$request->all();
		if (!empty($formData)) {
			$validator 					=	Validator::make(
				$request->all(),
				array(
					'word'		    => 'required',

				),
				array(
					"word.required"			=>	trans("The word field is required."),
				
				)
			);
			if ($validator->fails()) {
				return Redirect::back()->withErrors($validator)->withInput();
			} else {
                   $result  = $this->ProhibitedWordsService->update($request,$modelId);
				   if($result['status'] == true){
				    return Redirect::route($this->model . ".index")->with(['success' => $result['message']]);
				   }else{
					return Redirect::back()->with(['error' => $result['message']]);
				   }
			}
		}
	} // end update()


	public function delete($userId = 0)
	{
		$userDetails = ProhibitedWord::find($userId);
		if (empty($userDetails)) {
			return Redirect::route($this->model . ".index");
		}
		if ($userId) {
			ProhibitedWord::where('id', $userId)->delete();
			Session::flash('flash_notice', trans($this->sectionNameSingular . " has been removed successfully"));
		}
		return Redirect::back();
	} // end delete()



}// end ProhibitedWordsController