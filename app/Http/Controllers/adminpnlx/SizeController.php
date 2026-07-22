<?php

namespace App\Http\Controllers\adminpnlx;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\Language;
use App\Models\Size;
use App\Models\SizeDescription;

class SizeController extends Controller
{
    public $model = 'sizes';
    public function __construct(Request $request){
        parent::__construct();
        View()->share('model', $this->model);
        $this->request = $request;
    }

    public function index(Request $request){
        $DB = Size::query();
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
                    if ($fieldName == "status" && $fieldValue != '') {
                        $DB->where("is_active", 'like', '%' . $fieldValue . '%');
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
            ),
            array(
                'name'             => 'required',
            ),
        );
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        } else {
            $obj             = new Size;
            $obj->name       = $dafaultLanguageArray['name'];
            $obj->is_active  = 1;
            $obj->save();
            $lastId = $obj->id;
            if (!empty($thisData)) {
                foreach ($thisData['data'] as $language_id => $value) {
                    $subObj               = new SizeDescription();
                    $subObj->language_id  = $language_id;
                    $subObj->parent_id    = $lastId;
                    $subObj->name         = $value['name'];
                    $subObj->save(); 
                }
            }
            Session()->flash('success', Config('constants.SIZE.SIZE_TITLE') . " has been added successfully");
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
        $ColorDetails   =  Color::find($cms_id);
        $data = compact('ColorDetails');
        return view("admin.$this->model.view", $data);
    }

    public function edit($enfaqid){
        $color_id = '';
        $multiLanguage =    array();
        if (!empty($enfaqid)) {
            $color_id = base64_decode($enfaqid);
            $ColorDetails   =   Size::find($color_id);
            $Color_Description = SizeDescription::where('parent_id', $color_id)->get();

            if (!empty($Color_Description)) {
                foreach ($Color_Description as $description) {
                    $multiLanguage[$description->language_id]['name']    =   $description->name;
                }
            }
            $languages = Language::where('is_active', 1)->get();
            $language_code = Config('constants.DEFAULT_LANGUAGE.LANGUAGE_CODE');
            return View("admin.$this->model.edit", compact('multiLanguage', 'Color_Description', 'ColorDetails', 'languages', 'language_code'));
        } else {
            return Redirect()->route($this->model . ".index");
        }
    }

    public function update(Request $request, $enfaqid){
        $color_id = '';
        $multiLanguage =    array();
        if (!empty($enfaqid)) {
            $color_id = base64_decode($enfaqid);
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
            $obj              =   Size::find($color_id);
            $obj->name        = $dafaultLanguageArray['name'];
            $obj->save();
            $lastId  =  $obj->id;
            if (!empty($thisData)) {
                foreach ($thisData['data'] as $language_id => $value) {
                    $subObj                = SizeDescription::where('parent_id', $lastId)->where('language_id', $language_id)->first();
                    $subObj->language_id   = $language_id;
                    $subObj->parent_id     = $lastId;
                    $subObj->name          = $value['name'];
                    $subObj->save();
                }
            }
            Session()->flash('success', Config('constants.SIZE.SIZE_TITLE') .  " has been updated successfully");
            return Redirect()->route($this->model . ".index");
        }
    }

    public function destroy($enfaqid){
        $color_id = '';
        if (!empty($enfaqid)) {
            $color_id = base64_decode($enfaqid);
        } else {
            return Redirect()->route($this->model . ".index");
        }
        $ColorDetails               =  Size::where('id', $color_id)->first();
        $ColorDetails->is_deleted   = 1;
        $ColorDetails->save();

        Session()->flash('flash_notice', trans(Config('constants.SIZE.SIZE_TITLE') . " has been removed successfully"));
        return back();
    }

    public function changeStatus($modelId = 0, $status = 0)
    {
        $size = Size::find($modelId);
        if ($size) {
            $currentStatus = $size->is_active;
            if (isset($currentStatus) && $currentStatus == 0) {
                $NewStatus = 1;
            } else {
                $NewStatus = 0;
            }

            if ($NewStatus == 1) {
                $statusMessage = trans(Config('constants.SIZE.SIZE_TITLE') . ' has been activated successfully');
            } else {
                $statusMessage = trans(Config('constants.SIZE.SIZE_TITLE') . ' has been deactivated successfully');
            }

            $size->is_active = $NewStatus;
            $ResponseStatus = $size->save();
        }
        Session()->flash('flash_notice', $statusMessage);
        return back();
    }
}
