<?php

namespace App\Http\Controllers\adminpnlx;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Config;
use App\Models\Lookup;  
use App\Traits\ImageUpload;
use App\Models\Language;
use App\Models\User;
use App\Models\MobileIntroScreen;
use Carbon\Carbon;
use App\Models\EmailAction;
use App\Models\EmailTemplate;
use App\Models\MobileIntroScreenDescription;
use Redirect,Session;
use Str;

class IntroScreenController extends Controller
{
    public $model               = 'mobile-intro-screen';
    public $sectionNameSingular = 'intro-screen';
    use ImageUpload;
    public function __construct(Request $request)
        {   
            
            parent::__construct();
            View()->share('model', $this->model);
            View()->share('sectionNameSingular', $this->sectionNameSingular);
            $this->request = $request;
        }

    public function index(Request $request)
        {
            $DB					=	MobileIntroScreen::query();
            $searchVariable		=	array();
            $inputGet			=	$request->all();
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
                    $DB->whereBetween('mobile_intro_screen.created_at', [$dateS . " 00:00:00", $dateE . " 23:59:59"]);
                } elseif (!empty($searchData['date_from'])) {
                    $dateS = $searchData['date_from'];
                    $DB->where('mobile_intro_screen.created_at', '>=', [$dateS . " 00:00:00"]);
                } elseif (!empty($searchData['date_to'])) {
                    $dateE = $searchData['date_to'];
                    $DB->where('mobile_intro_screen.created_at', '<=', [$dateE . " 00:00:00"]);
                }
                foreach ($searchData as $fieldName => $fieldValue) {
                    if ($fieldValue != "") {
                        if ($fieldName == "title") {
                            $DB->where("mobile_intro_screen.title", 'like', '%' . $fieldValue . '%');
                        }
                        if ($fieldName == "description") {
                            $DB->where("mobile_intro_screen.description", 'like', '%' . $fieldValue . '%');
                        }
                
                        if ($fieldName == "is_active") {
                            $DB->where("mobile_intro_screen.is_active", 'like', '%' . $fieldValue . '%');
                        }
                    }
                    $searchVariable	=	array_merge($searchVariable, array($fieldName => $fieldValue));
                }
            }

            $sortBy = ($request->input('sortBy')) ? $request->input('sortBy') : 'mobile_intro_screen.row_order';
            $order  = ($request->input('order')) ? $request->input('order')   : 'ASC';
            $records_per_page	=	($request->input('per_page')) ? $request->input('per_page') : Config::get("Reading.records_per_page");
            $results = $DB->orderBy($sortBy, $order)->paginate($records_per_page);
            $complete_string		=	$request->query();
            unset($complete_string["sortBy"]);
            unset($complete_string["order"]);
            $query_string			=	http_build_query($complete_string);
            $results->appends($inputGet)->render();
            $resultcount = $results->count();
            return  View("admin.$this->model.index", compact('resultcount', 'results', 'searchVariable', 'sortBy', 'order', 'query_string'));
        }

    public function add(Request $request)
        {       
            $languages = Language::where('is_active', 1)->get();
            $language_code = Config('constants.DEFAULT_LANGUAGE.LANGUAGE_CODE');
            return View("admin.$this->model.add", compact('languages', 'language_code'));
        
        }

    public function Save(Request $request)
        {

            $thisData             = $request->all();
            $default_language     = Config('constants.DEFAULT_LANGUAGE.FOLDER_CODE');
            $language_code        = Config('constants.DEFAULT_LANGUAGE.LANGUAGE_CODE');
            $dafaultLanguageArray = $thisData['data'][$language_code];

            $validator = Validator::make(
                array(
                    'title'         => $dafaultLanguageArray['title'],
                    'description'   => $dafaultLanguageArray['description'],
                    'image'         => $request->file('image'),
                ),
                array(
                    'title'         => 'required',
                    'description'   => 'required',
                    'image'         => 'required|mimes:jpg,jpeg,png',
                ),
            );

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            } else {
                $obj = new MobileIntroScreen;
                $obj->title = $dafaultLanguageArray['title'];
                $obj->description  = $dafaultLanguageArray['description'];
                $obj->image = $this->upload($request, 'image', config('constants.MOBILE_INTRO_IMAGE_ROOT_PATH'));
                $obj->save();
                $lastId = $obj->id;
                if (!empty($thisData)) {
                    foreach ($thisData['data'] as $language_id => $value) {
                        $subObj = new MobileIntroScreenDescription();
                        $subObj->language_id = $language_id;
                        $subObj->parent_id = $lastId;
                        $subObj->title = $value['title'];
                        $subObj->description = $value['description'];
                        $subObj->save();
                    }
                }
                Session()->flash('success', ucfirst(Config('constants.MOBILE_INTRO_SCREEN.MOBILE_INTRO_SCREEN_TITLE')." has been added successfully"));
                return Redirect()->route($this->model . ".index");
            }
        }

   
    public function edit(Request $request,  $enuserid = null)
        {
            $user_id = '';
            $multiLanguage =    array();
            if (!empty($enuserid)) {
                $user_id        = base64_decode($enuserid);
                $userDetails    = MobileIntroScreen::find($user_id);
                $intro_descriptiondetl = MobileIntroScreenDescription::where('parent_id', $user_id)->get();

                if (!empty($intro_descriptiondetl)) {
                    foreach ($intro_descriptiondetl as $d) {
                        $multiLanguage[$d->language_id]['title']        =  $d->title;
                        $multiLanguage[$d->language_id]['description']  = $d->description;
                    }
                }
                $languages = Language::where('is_active', 1)->get();
                $language_code = Config('constants.DEFAULT_LANGUAGE.LANGUAGE_CODE');
                return View("admin.$this->model.edit", compact('multiLanguage', 'intro_descriptiondetl', 'userDetails', 'languages', 'language_code'));
            
            } else {
                return redirect()->route($this->model . ".index");
            }


        }

 
 
    public function update(Request $request,  $enuserid = null)
        {
            
            $user_id = '';
            $multiLanguage =    array();
            if (!empty($enuserid)) {
                $user_id = base64_decode($enuserid);
            } else {
            
                return Redirect()->route($this->model . ".index");
            }
            $thisData              =    $request->all();
            $default_language      =    Config('constants.DEFAULT_LANGUAGE.FOLDER_CODE');
            $language_code         =    Config('constants.DEFAULT_LANGUAGE.LANGUAGE_CODE');
            $dafaultLanguageArray  =    $thisData['data'][$language_code];

            $validator = Validator::make(
                array(
                    'title'         => $dafaultLanguageArray['title'],
                    'description'   => $dafaultLanguageArray['description'],
                    'image'         => $request->file('image'),
                ),
                array(
                    'title'         => 'required',
                    'description'   => 'required',
                    'image'         => 'nullable|mimes:jpg,jpeg,png',
                ),
            );
            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            } else {
                $obj              = MobileIntroScreen::find($user_id);
                $obj->title       = $dafaultLanguageArray['title'];
                $obj->description = $dafaultLanguageArray['description'];

                if($request->image){
                    $path         = parse_url($obj->image, PHP_URL_PATH);
                    $oldPath      = Str::after($path, 'intro-image');
                    $obj->image   = $this->upload($request,'image',Config('constants.MOBILE_INTRO_IMAGE_ROOT_PATH'),$oldPath);
            }
                $obj->save();
                $lastId  =  $obj->id;
                MobileIntroScreenDescription::where("parent_id", $lastId)->delete();
                if (!empty($thisData)) {
                    foreach ($thisData['data'] as $language_id => $value) {
                        $subObj   =  new MobileIntroScreenDescription();
                        $subObj->language_id = $language_id;
                        $subObj->parent_id = $lastId;
                        $subObj->title = $value['title'];
                        $subObj->description = $value['description'];
                        $subObj->save();
                    }
                }
                Session()->flash('success', ucfirst(Config('constants.MOBILE_INTRO_SCREEN.MOBILE_INTRO_SCREEN_TITLE')." has been updated successfully"));
                    return Redirect()->route($this->model . ".index");
            }
        }

    public function delete($enuserid)
        {
                if (empty($enuserid)) {
                    return Redirect()->route($this->model . '.index');
                }
            
                $user_id = base64_decode($enuserid);
            
                if (is_null($user_id)) {
                    return Redirect()->route($this->model . '.index');
                }
                $MobileIntroScreenObj   =  DB::table("mobile_intro_screen")->where('id',$user_id)->first();
                $deletePaths            = base_path().'/public/storage'.Config('constants.MOBILE_INTRO_IMAGE_ROOT_PATH') . $MobileIntroScreenObj->image ?? null;
                if (file_exists($deletePaths)) {
                    unlink($deletePaths);
                }
            
                $MobileIntroScreenObj   =  MobileIntroScreen::find($user_id)->delete();
                MobileIntroScreenDescription::where("parent_id", $user_id)->delete();
            
                Session()->flash('flash_notice', trans('Mobile Intro Screen has been removed successfully'));
            
                return back();
        }

    public function changeStatus($modelId = 0, $status = 0)
        {
            if ($status == 1) {
                $statusMessage   =   trans(Config('constants.MOBILE_INTRO_SCREEN.MOBILE_INTRO_SCREEN_TITLE'). " has been activated successfully");
            } else {
                $statusMessage   =   trans(Config('constants.MOBILE_INTRO_SCREEN.MOBILE_INTRO_SCREEN_TITLE'). " has been deactivated successfully");
            }
            $user = MobileIntroScreen::find($modelId);
            if ($user) {
                $currentStatus = $user->is_active;
                if (isset($currentStatus) && $currentStatus == 0) {
                    $NewStatus = 1;
                } else {
                    $NewStatus = 0;
                }
                $user->is_active = $NewStatus;
                $ResponseStatus  = $user->save();
            }
            Session()->flash('flash_notice', $statusMessage);
            return back();
        }

    public function updateIntroScreenOrder(Request $request)
        {
            $requestOrder = $request->input("requestData");
            if(!empty($requestOrder)){
            foreach($requestOrder as $mood_order){
            MobileIntroScreen::where("id",$mood_order["id"])->update(array("row_order"=>$mood_order["order"]));
            }
            }
            die;
        }

}
