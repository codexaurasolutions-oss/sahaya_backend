<?php

namespace App\Http\Controllers\adminpnlx;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\Language;
use App\Models\Faq;
use App\Models\Faq_description;

class FaqController extends Controller
{
    public $model = 'faqs';
    public function __construct(Request $request){
        parent::__construct();
        View()->share('model', $this->model);
        $this->request = $request;
    }

    public function index(Request $request){
        $DB = Faq::query();
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
                    if ($fieldName == "question") {
                        $DB->where("faqs.question", 'like', '%' . $fieldValue . '%');
                    }
                    if ($fieldName == "answer") {
                        $DB->where("faqs.answer", 'like', '%' . $fieldValue . '%');
                    }
                }
                $searchVariable = array_merge($searchVariable, array($fieldName => $fieldValue));
            }
        }
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

    public function store(Request $request){
        $thisData                    =    $request->all();
        $default_language            =    Config('constants.DEFAULT_LANGUAGE.FOLDER_CODE');
        $language_code                 =   Config('constants.DEFAULT_LANGUAGE.LANGUAGE_CODE');
        $dafaultLanguageArray        =    $thisData['data'][$language_code];
        $validator = Validator::make(
            array(
                'faq_order'         => $request->input('faq_order'),
                'question'             => $dafaultLanguageArray['question'],
                'answer'                 => $dafaultLanguageArray['answer'],
            ),
            array(
                'faq_order'         => 'required|numeric|unique:faqs',
                'question'             => 'required',
                'answer'                 => 'required',
            ),
        );
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        } else {
            $obj = new Faq;
            $obj->faq_order = $request->input('faq_order');
            $obj->question  = $dafaultLanguageArray['question'];
            $obj->answer    = $dafaultLanguageArray['answer'];
            $obj->is_active = 1;
            $obj->save();
            $lastId = $obj->id;
            if (!empty($thisData)) {
                foreach ($thisData['data'] as $language_id => $value) {
                    $subObj = new Faq_description();
                    $subObj->language_id = $language_id;
                    $subObj->parent_id = $lastId;
                    $subObj->question = $value['question'];
                    $subObj->answer = $value['answer'];
                    $subObj->save();
                }
            }
            Session()->flash('success', Config('constants.FAQ.FAQ_TITLE') . " has been added successfully");
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
        $FaqDetails   =  Faq::find($cms_id);
        $data = compact('FaqDetails');
        return view("admin.$this->model.view", $data);
    }

    public function edit($enfaqid){
        $faq_id = '';
        $multiLanguage =    array();
        if (!empty($enfaqid)) {
            $faq_id = base64_decode($enfaqid);
            $faqDetails   =   Faq::find($faq_id);
            $Faq_descriptiondetl = Faq_description::where('parent_id', $faq_id)->get();

            if (!empty($Faq_descriptiondetl)) {
                foreach ($Faq_descriptiondetl as $description) {
                    $multiLanguage[$description->language_id]['question']    =   $description->question;
                    $multiLanguage[$description->language_id]['answer']    =   $description->answer;
                }
            }
            $languages = Language::where('is_active', 1)->get();
            $language_code = Config('constants.DEFAULT_LANGUAGE.LANGUAGE_CODE');
            return View("admin.$this->model.edit", compact('multiLanguage', 'Faq_descriptiondetl', 'faqDetails', 'languages', 'language_code'));
        } else {
            return Redirect()->route($this->model . ".index");
        }
    }

    public function update(Request $request, $enfaqid){
        $faq_id = '';
        $multiLanguage =    array();
        if (!empty($enfaqid)) {
            $faq_id = base64_decode($enfaqid);
        } else {
            return Redirect()->route($this->model . ".index");
        }
        $thisData                    =    $request->all();
        $default_language            =    Config('constants.DEFAULT_LANGUAGE.FOLDER_CODE');
        $language_code                 =   Config('constants.DEFAULT_LANGUAGE.LANGUAGE_CODE');
        $dafaultLanguageArray        =    $thisData['data'][$language_code];
        $validator = Validator::make(
            array(
                'faq_order'         => $request->input('faq_order'),
                'question'             => $dafaultLanguageArray['question'],
                'answer'                 => $dafaultLanguageArray['answer'],
            ),
            array(
                'faq_order'         => 'required|numeric',
                'question'             => 'required',
                'answer'                 => 'required',
            ),
        );
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        } else {
            $obj   =   Faq::find($faq_id);
            $obj->faq_order = $request->input('faq_order');
            $obj->question = $dafaultLanguageArray['question'];
            $obj->answer = $dafaultLanguageArray['answer'];
            $obj->is_active = 1;
            $obj->save();
            $lastId  =  $obj->id;
            Faq_description::where("parent_id", $lastId)->delete();
            if (!empty($thisData)) {
                foreach ($thisData['data'] as $language_id => $value) {
                    $subObj                =  new Faq_description();
                    $subObj->language_id = $language_id;
                    $subObj->parent_id = $lastId;
                    $subObj->question = $value['question'];
                    $subObj->answer = $value['answer'];
                    $subObj->save();
                }
            }
            Session()->flash('success', Config('constants.FAQ.FAQ_TITLE') .  " has been updated successfully");
            return Redirect()->route($this->model . ".index");
        }
    }

    public function destroy($enfaqid){
        $faq_id = '';
        if (!empty($enfaqid)) {
            $faq_id = base64_decode($enfaqid);
        } else {
            return Redirect()->route($this->model . ".index");
        }
        $FaqDetails   =  Faq::find($faq_id)->delete();
        Faq_description::where("parent_id", $faq_id)->delete();
        Session()->flash('flash_notice', trans(Config('constants.FAQ.FAQ_TITLE') . " has been removed successfully"));
        return back();
    }
}
