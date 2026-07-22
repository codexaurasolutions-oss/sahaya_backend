<?php

namespace App\Http\Controllers\adminpnlx;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\Language;
use App\Models\EmailTemplate;
use App\Models\EmailAction;
use App\Models\EmailTemplateDescription;

class EmailtemplateController extends Controller
{
    public $model                =    'email-templates';
    public function __construct(Request $request){
        parent::__construct();
        $this->request = $request;
        View()->share('model', $this->model);
    }
    
    public function index(Request $request)
    {
        $DB                =    EmailTemplate::query();
        $searchVariable    =    array();
        $inputGet        =    $request->all();
        if ($request->all()) {
            $searchData    =    $request->all();
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
                if (!empty($fieldValue)) {
                    if ($fieldName == "name" || $fieldName == "subject") {
                        $DB->where("$fieldName", 'like', '%' . $fieldValue . '%');
                    }
                    $searchVariable    =    array_merge($searchVariable, array($fieldName => $fieldValue));
                }
            }
        }
        $sortBy = ($request->input('sortBy')) ? $request->input('sortBy') : 'updated_at';
        $order  = ($request->input('order')) ? $request->input('order')   : 'DESC';
        $records_per_page   =   ($request->input('per_page')) ? $request->input('per_page') : Config("Reading.records_per_page");
        $results = $DB->orderBy($sortBy, $order)->paginate($records_per_page);
        $complete_string        =    $request->query();
        unset($complete_string["sortBy"]);
        unset($complete_string["order"]);
        $query_string            =    http_build_query($complete_string);
        $results->appends($request->all())->render();
        return  View("admin.$this->model.index", compact('results', 'searchVariable', 'sortBy', 'order', 'query_string'));
    }

    public function create()
    {
        $Action_options    =    EmailAction::get();
        $languages = Language::where('is_active', 1)->get();
        $language_code = Config('constants.DEFAULT_LANGUAGE.LANGUAGE_CODE');
        return  View("admin.$this->model.add", compact('Action_options', 'languages', 'language_code'));
    }

    public function store(Request $request)
    {
        $thisData                    =    $request->all();
        $default_language            =    Config('constants.DEFAULT_LANGUAGE.FOLDER_CODE');
        $language_code                 =   Config('constants.DEFAULT_LANGUAGE.LANGUAGE_CODE');
        $dafaultLanguageArray        =    $thisData['data'][$language_code];
        $validator = Validator::make(
            array(
                'name'         =>  $dafaultLanguageArray['name'],
                'subject'       =>  $dafaultLanguageArray['subject'],
                'action'       =>  $dafaultLanguageArray['action'],
                'body'       =>  $dafaultLanguageArray['body'],
            ),
            array(
                'name'         => 'required',
                'subject'         => 'required',
                'action'         => 'required',
                'body'         => 'required',
            )
        );
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        } else {
            $obj                                 = new EmailTemplate;
            $obj->name                             = $dafaultLanguageArray['name'];
            $obj->subject                          = $dafaultLanguageArray['subject'];
            $obj->body                               = $dafaultLanguageArray['body'];
            $obj->action                           = $dafaultLanguageArray['action'];
            $objSave                            = $obj->save();
            $last_id            =    $obj->id;
            foreach ($thisData['data'] as $language_id => $value) {
                $EmailTemplateDescription_obj                    =  new EmailTemplateDescription();
                $EmailTemplateDescription_obj->language_id        =    $language_id;
                $EmailTemplateDescription_obj->parent_id        =    $last_id;
                $EmailTemplateDescription_obj->name                =    $value['name'];
                $EmailTemplateDescription_obj->subject            =    $value['subject'];
                $EmailTemplateDescription_obj->body                =    $value['body'];
                $EmailTemplateDescription_obj->save();
            }
            Session()->flash('flash_notice', trans("Email template added successfully"));
            return redirect()->route($this->model.'.index');
        }
    }

    public function edit($enmaiman)
    {
        $Id = '';
        if (!empty($enmaiman)) {
            $Id = base64_decode($enmaiman);
            $emailTemplate              =    EmailTemplate::find($Id);
            $EmailTemplateDescription   =    EmailTemplateDescription::where('parent_id', '=',  $Id)->get();
            $multiLanguage              =    array();
            if (!empty($EmailTemplateDescription)) {
                foreach ($EmailTemplateDescription as $description) {
                    $multiLanguage[$description->language_id]['name']            =    $description->name;
                    $multiLanguage[$description->language_id]['subject']        =    $description->subject;
                    $multiLanguage[$description->language_id]['body']            =    $description->body;
                }
            }
            $options =  EmailAction::where('action', $emailTemplate->action)->value('options');
            $optionsvalue = explode(',', $options);
            $language_code = Config('constants.DEFAULT_LANGUAGE.LANGUAGE_CODE');
            $languages = Language::where('is_active', 1)->get();
            return  View("admin.$this->model.edit", compact('languages', 'language_code', 'multiLanguage', 'emailTemplate', 'optionsvalue'));
        }else{
            return Redirect()->route($this->model . ".index");
        }
    }

    public function update(Request $request, $enmaiman)
    {
        $Id = '';
        if (!empty($enmaiman)) {
            $Id = base64_decode($enmaiman);
        }else{
            return Redirect()->route($this->model . ".index");
        }
        $thisData                    =    $request->all();     
        $default_language            =    Config('constants.DEFAULT_LANGUAGE.FOLDER_CODE');
        $language_code                 =   Config('constants.DEFAULT_LANGUAGE.LANGUAGE_CODE');
        $dafaultLanguageArray        =    $thisData['data'][$language_code];
        $validator = Validator::make(
            array(
                'name'         =>  $dafaultLanguageArray['name'],
                'subject'       =>  $dafaultLanguageArray['subject'],
                'body'       =>  $dafaultLanguageArray['body'],
            ),
            array(
                'name'         => 'required',
                'subject'         => 'required',
                'body'         => 'required',
            )
        );
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        } else {
            $model                    = EmailTemplate::find($Id);
            $obj                      = $model;
            $obj->name                = $dafaultLanguageArray['name'];
            $obj->subject             = $dafaultLanguageArray['subject'];
            $obj->body                = $dafaultLanguageArray['body'];
            $obj->save();
            $last_id            =    $obj->id;
            EmailTemplateDescription::where('parent_id', '=', $last_id)->delete();
            if (!empty($thisData)) {
                foreach ($thisData['data'] as $language_id => $value) {
                    $EmailTemplateDescription_obj                       =  new EmailTemplateDescription();
                    $EmailTemplateDescription_obj->language_id          =    $language_id;
                    $EmailTemplateDescription_obj->parent_id            =    $last_id;
                    $EmailTemplateDescription_obj->name                 =    $value['name'];
                    $EmailTemplateDescription_obj->subject              =    $value['subject'];
                    $EmailTemplateDescription_obj->body                 =    $value['body'];
                    $EmailTemplateDescription_obj->save();
                }
                Session()->flash('flash_notice', trans("Email template updated successfully"));
                return Redirect()->route("$this->model.index");
            }
        }
    }

 
   

    public function getConstant(Request $request){
        if ($request->all()) {
            $constantName     =     $request->input('constant');
            $options        =     EmailAction::where('action', '=', $constantName)->pluck('options', 'action');
            $a                 =     explode(',', $options[$constantName]);
            return json_encode($a);
        }
        exit;
    }
}
