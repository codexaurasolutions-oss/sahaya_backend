<?php

namespace App\Http\Controllers\adminpnlx;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Traits\ImageUpload;


use App\Models\Language;
use App\Models\Category;
use App\Models\CategoryDescription;

use Str;

class CategoryController extends Controller
{
    use ImageUpload;
    
    public $model = 'categories';
    public function __construct(Request $request){
        parent::__construct();
        View()->share('model', $this->model);
        $this->request = $request;
    }

    public function index(Request $request){
        $DB = Category::query();
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
                    if ($fieldName == "name" && $fieldValue != '') {
                        $DB->where("name", 'like', '%' . $fieldValue . '%');
                    }
                    if ($fieldName == "color_code" && $fieldValue != '') {
                        $DB->where("color_code", 'like', '%' . $fieldValue . '%');
                    }
                    if ($fieldName == "status" && $fieldValue != '') {
                        $DB->where("is_active", 'like', '%' . $fieldValue . '%');
                    }
                }
                $searchVariable = array_merge($searchVariable, array($fieldName => $fieldValue));
            }
        }
        $DB->where('is_deleted', 0);
        $DB->where('parent_id', NULL);
        $sortBy = ($request->input('sortBy')) ? $request->input('sortBy') : 'created_at';
        $order = ($request->input('order')) ? $request->input('order') : 'DESC';
        $records_per_page = ($request->input('per_page')) ? $request->input('per_page') : Config("Reading.records_per_page");
        $results = $DB->orderBy($sortBy, $order)->paginate($records_per_page);
        $complete_string = $request->query();
        unset($complete_string["sortBy"]);
        unset($complete_string["order"]);
        $query_string = http_build_query($complete_string);
        $results->appends($inputGet)->render();
        return View("admin.$this->model.index", compact('results', 'searchVariable', 'sortBy', 'order', 'query_string'));
    }

    public function create()
    {
        $languages = Language::where('is_active', 1)->get();
        $language_code = Config('constants.DEFAULT_LANGUAGE.LANGUAGE_CODE');
        return View("admin.$this->model.add", compact('languages', 'language_code'));
    }

    public function save(Request $request){
        $thisData                    =    $request->all();
        $default_language            =    Config('constants.DEFAULT_LANGUAGE.FOLDER_CODE');
        $language_code                 =   Config('constants.DEFAULT_LANGUAGE.LANGUAGE_CODE');
        $dafaultLanguageArray        =    $thisData['data'][$language_code];
        $validator = Validator::make(
            array(
                'name'             => $dafaultLanguageArray['name'],
                'image'            => $request->file('image'),
            ),
            array(
                'name'             => 'required',
                'image'            => 'required|image|mimes:png,jpeg,jpg,webp',
            ),
        );
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        } else {
            $catCount = Category::count();

            $obj                = new Category;
            $obj->name          = $dafaultLanguageArray['name'];
            $obj->slug          = Str::slug($dafaultLanguageArray['name']);
            $obj->image         = $this->upload($request, 'image', config('constants.CATEGORY_IMAGE_ROOT_PATH'));
            $obj->order_number  = $catCount + 1;
            $obj->save();

            $lastId = $obj->id;
            if (!empty($thisData)) {
                foreach ($thisData['data'] as $language_id => $value) {
                    $subObj               = new CategoryDescription();
                    $subObj->language_id  = $language_id;
                    $subObj->parent_id    = $lastId;
                    $subObj->name         = $value['name'];
                    $subObj->save(); 
                }
            }
            Session()->flash('success', Config('constants.CATEGORY.CATEGORY_TITLE') . " has been added successfully");
            return Redirect()->route($this->model . ".index");
        }
    }

    public function show($encmsid){
        $cms_id = '';
        if (!empty($encmsid)) {
            $cms_id = base64_decode($encmsid);
        } else {
            return Redirect()->route($this->model . ".index");
        }
        $CategoryDetails   =  Category::find($cms_id);
        $data = compact('CategoryDetails');
        return view("admin.$this->model.view", $data);
    }

    public function edit($enfaqid){
        $cat_id = '';
        $multiLanguage =    array();
        if (!empty($enfaqid)) {
            $cat_id = base64_decode($enfaqid);
            $CategoryDetails   =   Category::find($cat_id);
            $Category_Description = CategoryDescription::where('parent_id', $cat_id)->get();

            if (!empty($Category_Description)) {
                foreach ($Category_Description as $description) {
                    $multiLanguage[$description->language_id]['name']    =   $description->name;
                }
            }
            $languages = Language::where('is_active', 1)->get();
            $language_code = Config('constants.DEFAULT_LANGUAGE.LANGUAGE_CODE');
            return View("admin.$this->model.edit", compact('multiLanguage', 'Category_Description', 'CategoryDetails', 'languages', 'language_code'));
        } else {
            return Redirect()->route($this->model . ".index");
        }
    }

    public function update(Request $request, $enfaqid){
        $cat_id = '';
        $multiLanguage =    array();
        if (!empty($enfaqid)) {
            $cat_id = base64_decode($enfaqid);
        } else {
            return Redirect()->route($this->model . ".index");
        }
        $thisData                    =    $request->all();
        $default_language            =    Config('constants.DEFAULT_LANGUAGE.FOLDER_CODE');
        $language_code                 =   Config('constants.DEFAULT_LANGUAGE.LANGUAGE_CODE');
        $dafaultLanguageArray        =    $thisData['data'][$language_code];
        $validator = Validator::make(
            array(
                'name'                 => $dafaultLanguageArray['name'],
            ),
            array(
                'name'                 => 'required',
            ),
        );
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        } else {
            $obj              =   Category::find($cat_id);
            $obj->name        = $dafaultLanguageArray['name'];
            if($request->hasFile('image')){
                $obj->image       = $this->upload($request, 'image', config('constants.CATEGORY_IMAGE_ROOT_PATH'));
            }
            $obj->save();
            $lastId  =  $obj->id;
            if (!empty($thisData)) {
                foreach ($thisData['data'] as $language_id => $value) {
                    $subObj                = CategoryDescription::where('parent_id', $lastId)->where('language_id', $language_id)->first();
                    $subObj->language_id   = $language_id;
                    $subObj->parent_id     = $lastId;
                    $subObj->name          = $value['name'];
                    $subObj->save();
                }
            }
            Session()->flash('success', Config('constants.CATEGORY.CATEGORY_TITLE') .  " has been updated successfully");
            return Redirect()->route($this->model . ".index");
        }
    }

    public function destroy($enfaqid){
        $cat_id = '';
        if (!empty($enfaqid)) {
            $cat_id = base64_decode($enfaqid);
        } else {
            return Redirect()->route($this->model . ".index");
        }
        $ColorDetails               =  Category::where('id', $cat_id)->first();
        $ColorDetails->is_deleted   = 1;
        $ColorDetails->save();

        Session()->flash('flash_notice', trans(Config('constants.CATEGORY.CATEGORY_TITLE') . " has been removed successfully"));
        return back();
    }

    public function changeStatus($modelId = 0, $status = 0)
    {
        $color = Category::find($modelId);
        if ($color) {
            $currentStatus = $color->is_active;
            if (isset($currentStatus) && $currentStatus == 0) {
                $NewStatus = 1;
            } else {
                $NewStatus = 0;
            }

            if ($NewStatus == 1) {
                $statusMessage = trans(Config('constants.CATEGORY.CATEGORY_TITLE') . ' has been activated successfully');
            } else {
                $statusMessage = trans(Config('constants.CATEGORY.CATEGORY_TITLE') . ' has been deactivated successfully');
            }

            $color->is_active = $NewStatus;
            $ResponseStatus = $color->save();
        }
        Session()->flash('flash_notice', $statusMessage);
        return back();
    }
}
