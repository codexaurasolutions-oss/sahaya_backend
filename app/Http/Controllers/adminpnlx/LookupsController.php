<?php

namespace App\Http\Controllers\adminpnlx;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Lookup;
use App\Models\LookupDiscription;
use App\Models\Language;
use DB;
use Illuminate\Support\Facades\Validator;

class LookupsController extends Controller
{
    public $model = 'lookups-manager';
    public function __construct(Request $request)
    {   
        parent::__construct();
        View()->share('model', $this->model);
        $this->request = $request;
    }
    public function index(Request $request, $type = null)
    {
        if (empty($type)) {
            return Redirect()->route('dashboard');
        }

        $DB                =    Lookup::query()->where('lookup_type', $type);
        $searchVariable    =    array();
        $inputGet        =    $request->all();
        if ($request->all()) {
            $searchData            =    $request->all();
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
                if ($fieldName == "code") {
                    $DB->where("lookups.code", 'like', '%' . $fieldValue . '%');
                }
                $searchVariable    =    array_merge($searchVariable, array($fieldName => $fieldValue));
            }
        }

        $sortBy = ($request->get('sortBy')) ? $request->get('sortBy') : 'updated_at';
        $order  = ($request->get('order')) ? $request->get('order')   : 'DESC';
        $records_per_page    =    ($request->input('per_page')) ? $request->input('per_page') : Config("Reading.records_per_page");
        $results = $DB->orderBy($sortBy, $order)->paginate($records_per_page);

        $complete_string        =    $request->query();
        unset($complete_string["sortBy"]);
        unset($complete_string["order"]);
        $query_string            =    http_build_query($complete_string);
        $results->appends($request->all())->render();

        return  View("admin.$this->model.index", compact('results', 'searchVariable', 'sortBy', 'order', 'type', 'query_string'));
    }

    public function add(Request $request, $type = null)
    {
        if ($request->isMethod('POST')) {
            $thisData                    =    $request->all();
            $default_language            =    Config('constants.DEFAULT_LANGUAGE.FOLDER_CODE');
            $language_code               =    Config('constants.DEFAULT_LANGUAGE.LANGUAGE_CODE');
            $dafaultLanguageArray        =    $thisData['data'][$language_code];
            if(!empty($type == 'skills')){
                Validator::extend('unique_skills', function($attribute,$value,$parameters){
                    $getinfo  =   DB::table('lookups')->where("lookup_type",'skills')->where("code",$value)->where("is_active",1)->first();
                    if(!empty($getinfo)){
                        return false;
                    }else {
                        return true;
                    }
                });
                $validator = Validator::make(
                    array(
                        'code'              => $dafaultLanguageArray['code'],
                        'lookup_type'       =>  $type,
                    ),
                    array(
                        'code'              => 'required|unique_skills',
                    ),
                    array(
                        "code.required"        => trans("The skills field is required."),
                        "code.unique_skills"   => trans("The skills must be unique."),
                    )
                );    
            }else{
                $validator = Validator::make(
                    array(
                        'code'              => $dafaultLanguageArray['code'],
                        'lookup_type'	    =>	$type,
                    ),
                    array(
                        'code'              => 'required',
                    ),
                );
            }
            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }else{
                $lookups = new Lookup;
                $lookups->code    						= $dafaultLanguageArray['code'];
                $lookups->lookup_type    				= $type;
                $lookups->save(); 
                $lookupsId								= $lookups->id;
                if(!$lookupsId){
                    Session()->flash('error', trans("Something went wrong.")); 
                    return Redirect()->back()->withInput();
                }
                foreach ($thisData['data'] as $language_id => $value) {
                    $lookupDescription_obj					=  new LookupDiscription();
                    $lookupDescription_obj->language_id		=	$language_id;
                    $lookupDescription_obj->parent_id		=	$lookupsId;
                    $lookupDescription_obj->code	 	    =	$value['code'];
                    $lookupDescription_obj->save();
                }
    
            Session()->flash('flash_notice', trans(ucfirst($type).' added successfully')); 
                return Redirect()->route($this->model.".index",$type);
            }
        }
        $languages = Language::where('is_active', 1)->get();
        $language_code = Config('constants.DEFAULT_LANGUAGE.LANGUAGE_CODE');
        return  View("admin.$this->model.add", compact('type', 'languages', 'language_code'));
    }

    public function update(Request $request, $type, $enlokid)
    {
       
        $look_id = '';       
        if (!empty($enlokid)) {
            $look_id = base64_decode($enlokid);
        } else {
            return Redirect()->back();
        }    
        // $type =  Lookup::find($look_id)->value('lookup_type'); 
        if ($request->isMethod('POST')) {
            $thisData                    =    $request->all();
            $default_language            =    Config('constants.DEFAULT_LANGUAGE.FOLDER_CODE');
            $language_code                 =   Config('constants.DEFAULT_LANGUAGE.LANGUAGE_CODE');
            $dafaultLanguageArray        =    $thisData['data'][$language_code];
            if(!empty($type == 'skills')){
                Validator::extend('unique_skills', function($attribute,$value,$parameters)use($look_id){
                    $getinfo  =   DB::table('lookups')->where("lookup_type",'skills')->where("code",$value)->where('id','!=',$look_id)->where("is_active",1)->first();
                    if(!empty($getinfo)){
                        return false;
                    }else {
                        return true;
                    }
                });
                $validator = Validator::make(
                    array(
                        'code'              => $dafaultLanguageArray['code'],
                        'lookup_type'       =>  $type,
                    ),
                    array(
                        'code'              => 'required|unique_skills',
                    ),
                    array(
                        "code.required"        => trans("The skills field is required."),
                        "code.unique_skills"   => trans("The skills must be unique."),
                    )
                );    
            }else{
                $validator = Validator::make(
                    array(
                        'code'              => $dafaultLanguageArray['code'],
                        'lookup_type'       =>  $type,
                    ),
                    array(
                        'code'              => 'required',
                    ),
                );
            }
            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }  else{
                $lookups =  Lookup::find($look_id);  
                $lookups->code    						= $dafaultLanguageArray['code'];
                $lookups->lookup_type    				= 	$type;
                $lookups->save(); 
                $lookupsId								=	$lookups->id;
                if(!$lookupsId){
                    Session()->flash('error', trans("Something went wrong.")); 
                    return Redirect()->back()->withInput();
                }
                LookupDiscription::where('parent_id', '=', $lookupsId)->delete();
                foreach ($thisData['data'] as $language_id => $value) {
                    $lookupDescription_obj					=  new LookupDiscription();
                    $lookupDescription_obj->language_id		=	$language_id;
                    $lookupDescription_obj->parent_id		=	$lookupsId;
                    $lookupDescription_obj->code	 	    =	$value['code'];	
                    $lookupDescription_obj->save();
                }
    
                Session()->flash('flash_notice',trans(ucfirst($type)." updated successfully")); 
                return Redirect()->route($this->model.'.index',$type);
            }
        }

        $lookups =  Lookup::find($look_id);  
        $LookupDescription	=	LookupDiscription::where('parent_id',$look_id)->get();
        $multiLanguage		 	=	array();
        if(!empty($LookupDescription)){
			foreach($LookupDescription as $description) {
				$multiLanguage[$description->language_id]['code']=	$description->code;				
			}
		}
        $languages = Language::where('is_active', 1)->get();
        $language_code = Config('constants.DEFAULT_LANGUAGE.LANGUAGE_CODE');
   
        return  View("admin.$this->model.edit",array('lookups' => $lookups,'type'=>$type,'languages' => $languages,'language_code' => $language_code,'multiLanguage' => $multiLanguage));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($enlokid)
    {
        $look_id = '';
        if (!empty($enlokid)) {
            $look_id = base64_decode($enlokid);
        } else {
            return Redirect()->back();
        }
        $type =  Lookup::find($look_id)->value('lookup_type');  
         Lookup::find($look_id)->delete();
        LookupDiscription::where("parent_id", $look_id)->delete();
        Session()->flash('flash_notice', trans(ucfirst($type). " has been removed successfully"));
        return back();
    }

    public function changeStatus($modelId = 0, $status = 0){
        $type =  Lookup::find($modelId)->value('lookup_type');  
        if ($status == 1) {
            $statusMessage   =   trans(ucfirst($type). " has been deactivated successfully");
        } else {
            $statusMessage   =  trans(ucfirst($type).  " has been activated successfully");
        }
        $user = Lookup::find($modelId);
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
}
