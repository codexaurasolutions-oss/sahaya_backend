<?php
namespace App\Http\Controllers\adminpnlx;

use App\Http\Controllers\Controller;
use App\Models\SeoPage;
use App\Models\SeoDescription;
use mjanssen\BreadcrumbsBundle\Breadcrumbs as Breadcrumb;
use Illuminate\Http\Request;
use Auth,Blade,Config,Cache,Cookie,DB,File,Hash,Input,Redirect,Response,Session,URL,View,Validator;
/**
* SeoPageController Controller
*
* Add your methods in the class below
*
* This file will render views from views/adminpnlxSeoPage
*/
class SeoPageController extends Controller {

	public $model      =   'SeoPage';
    public function __construct(Request $request)
    {   
        parent::__construct();
        View()->share('model', $this->model);
        $this->request = $request;
    }
/**
* Function for display all Document 
*
* @param null
*
* @return view page. 
*/
public function index(Request $request){	
	$DB							=	SeoPage::query();
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
                    if ($fieldName == "title") {
                        $DB->where("seo_pages.title", 'like', '%' . $fieldValue . '%');
                    }
                    
                }
                $searchVariable = array_merge($searchVariable, array($fieldName => $fieldValue));
            }
        }
        $DB->where('is_deleted',0);
        $sortBy = ($request->input('sortBy')) ? $request->input('sortBy') : 'created_at';
        $order = ($request->input('order')) ? $request->input('order') : 'DESC';
        $records_per_page = ($request->input('per_page')) ? $request->input('per_page') : Config::get("Reading.records_per_page");
        $results = $DB->orderBy($sortBy, $order)->paginate($records_per_page);       
        $complete_string = $request->query();
        unset($complete_string["sortBy"]);
        unset($complete_string["order"]);
        $query_string = http_build_query($complete_string);
        $results->appends($inputGet)->render();
	return  View::make('admin.SeoPage.index',compact('results','searchVariable','sortBy','order','query_string'));
	}// end listDoc()
/**
* Function for display page  for add new seo
*
* @param null
*
* @return view page. 
*/
public function addDoc(){
    	
	
	return  View::make('admin.SeoPage.add');
	} //end addDoc()
/**
* Function for save document
*
* @param null
*
* @return redirect page. 
*/
function saveDoc(Request $request){
	$request->replace($this->arrayStripTags($request->all()));
	$thisData					=	$request->all();


   if (!empty($thisData)) {
	$validator = Validator::make(
         $request->all(),
		array(
			'page_id' 			=> 'required',
			'title' 			=> 'required',
			'page_name' 		=> 'required',
			
		),
		array(
			"page_id.required"				=>	trans("The page id field is required."),
			"title.required"				=>	trans("The title field is required."),				
			"page_name.required"			=>	trans("The page name field is required."),
		)
	);
    if($validator->fails()){
  
        return Redirect::back()->withErrors($validator)->withInput();
      }else{
		DB::beginTransaction();
		$seo 					=  new SeoPage;
		$seo->page_id    		= $request->input('page_id');
		$seo->page_name    		= $request->input('page_name');
		$seo->title   			= $request->input('title');
		$seo->meta_description  = $request->input('meta_description');
		$seo->meta_keywords   	= $request->input('meta_keywords');

		$seo->twitter_card   	= $request->input('twitter_card');
		$seo->twitter_site   	= $request->input('twitter_site');
		$seo->og_url   			= $request->input('og_url');
		$seo->og_type   		= $request->input('og_type');
		$seo->og_title   		= $request->input('og_title');
		$seo->og_description   	= $request->input('og_description');
        
		

		if($request->hasFile('og_image')){
			$extension 				=	$request->file('og_image')->getClientOriginalExtension();
			$fileName				=	time().'-og_image.'.$extension;
			
			$folderName     		= 	strtoupper(date('M'). date('Y'))."/";
			$folderPath				=	Config('constants.SEO_PAGE_IMAGE_ROOT_PATH').$folderName;
			if(!File::exists($folderPath)) {
				File::makeDirectory($folderPath, $mode = 0777,true);
			}
			if($request->file('og_image')->move($folderPath, $fileName)){
				$seo->og_image					=	$folderName.$fileName;
			   
			}
		}
        	$seopags				= $seo->save();
		
			if(!$seopags) {
			DB::rollback();
			Session::flash('error', trans("Something went wrong.")); 
			return Redirect::back()->withInput();
		}
			DB::commit();
			Session::flash('flash_notice', trans("Seo page added successfully")); 
			return Redirect::to('adminpnlx/seo-page-manager');
		}
      }
	}//end saveBlock()
/**
* Function for display page  for edit seo
*
* @param $Id ad id 
*
* @return view page. 
*/	
public function editDoc($Id){
	$ids	= base64_decode($Id); 
	$docs				=	SeoPage::where('id',$ids)->first();
	if(empty($docs)) {
		return Redirect::to('adminpnlx/seo-page-manager');
	}
	 return  View::make('admin.SeoPage.edit',array('doc'=>$docs));
	}// end editBlock()
/**
* Function for update seo 
*
* @param $Id ad id of seo 
*
* @return redirect page. 
*/
function updateDoc($Id,Request $request){
$docs				=	SeoPage::find($Id);
	if(empty($docs)) {
		return Redirect::to('adminpnlxno-cms-manager');
	}
	$request->replace($this->arrayStripTags($request->all()));
	$this_data				=	$request->all();
	$doc 					= 	SeoPage:: find($Id);
	$validator = Validator::make(
        $request->all(),
		array(
			'page_id' 			=> 'required',
			'page_name' 		=> 'required',
			'title' 			=> 'required',
			),
		array(
			"page_id.required"				=>	trans("The page id field is required."),
			"title.required"				=>	trans("The title field is required."),				
			"page_name.required"			=>	trans("The page name field is required."),
		)
	);
	if ($validator->fails()){	
		return Redirect::back()
		->withErrors($validator)->withInput();
	}else{
		DB::beginTransaction();
		$og_image	=	$docs->og_image;
		if($request->hasFile('og_image')){
			$extension 			=	$request->file('og_image')->getClientOriginalExtension();
			$fileName			=	time().'-og-image.'.$extension;
			if($request->file('og_image')->move(Config('constants.SEO_PAGE_IMAGE_ROOT_PATH'), $fileName)){
				$og_image   =  	$fileName;
			} 
		}

		$Seo_response		=	SeoPage::where('id', $Id)->update(array(
			'page_id'   	 	=>  $request->input('page_id'),
			'page_name'   	 	=>  $request->input('page_name'),
			'title' 			=>  $request->input('title'),
			'meta_description' 	=>  $request->input('meta_description'),
			'meta_keywords' 	=>  $request->input('meta_keywords'),
			'twitter_card' 		=>  $request->input('twitter_card'),
			'twitter_site' 		=>  $request->input('twitter_site'),
			'og_url' 			=>  $request->input('og_url'),
			'og_type' 			=>  $request->input('og_type'),
			'og_title' 			=>  $request->input('og_title'),
			'og_description' 	=>  $request->input('og_description'),
        	'og_image' 			=>  $og_image,
		));

		if(!$Seo_response) {
			DB::rollback();
			Session::flash('error', trans("Something went wrong.")); 
			return Redirect::back()->withInput();
		}		
			DB::commit();
			Session::flash('flash_notice',  trans("Seo page updated successfully")); 
			return Redirect::intended('adminpnlx/seo-page-manager');
      }
	}// end updateSeoPage()
/**
* Function for update seo  status
*
* @param $Id as id of seo 
* @param $Status as status of seo 
*
* @return redirect page. 
*/	
public function updateDocStatus($Id = 0, $Status = 0){
		/* $model					=	SeoPage::find($Id);
		$model->is_active		=	$Status;
		$model->save(); */
		if($Status == 0	){
			$statusMessage	=	trans("Seo page deactivated successfully");
		}else{
			$statusMessage	=	trans("Seo page activated successfully");
		}
		$this->_update_all_status('seos',$Id,$Status);
		Session::flash('flash_notice', $statusMessage); 
		return Redirect::to('adminpnlx/no-cms-manager');
	}// end updateSeoPageStatus()
/**
* Function for delete seo 
*
* @param $Id as id of seo 
*
* @return redirect page. 
*/	
public function deletePage($modelId, Request $request){
	
	$ids	= base64_decode($modelId);
		$delete_item = SeoPage::where('id',$ids)->update(array('is_deleted' => 1,));
      Session::flash('flash_notice', trans("Seo page has been removed successfully"));
	  return Redirect::to('adminpnlx/seo-page-manager');
	}// end deleteSeoPage()
/**
* Function for delete multiple seo
*
* @param null
*
* @return view page. 
*/



	
}// end BlockController	