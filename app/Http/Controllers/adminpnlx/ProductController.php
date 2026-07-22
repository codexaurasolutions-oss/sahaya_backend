<?php

namespace App\Http\Controllers\adminpnlx;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\Language;
use App\Models\Product;
use App\Models\Category;
use App\Models\User;
use App\Models\EmailAction;
use App\Models\EmailTemplate;
use DB;

class ProductController extends Controller
{
    public $model = 'products';
    public function __construct(Request $request){
        parent::__construct();
        View()->share('model', $this->model);
        $this->request = $request;
    }

    public function index(Request $request){
        $DB = Product::query();
        $DB->with(['parentCategoryDetails', 'subCategoryDetails']);
        $sub_categories = [];
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
                    if($fieldName == 'category' && $fieldValue != ''){
                        $DB->where("parent_category", 'like', '%' . $fieldValue . '%');
                        $sub_categories = Category::where('parent_id', $fieldValue)->where(['is_active' => 1, 'is_deleted' => 0])->get();
                    }
                    if($fieldName == 'sub_category' && $fieldValue != ''){
                        $DB->where("category_level_2", 'like', '%' . $fieldValue . '%');
                    }
                    if ($fieldName == "status" && $fieldValue != '') {
                        $DB->where("is_approved", 'like', '%' . $fieldValue . '%');
                    }
                }
                $searchVariable = array_merge($searchVariable, array($fieldName => $fieldValue));
            }
        }
        $DB->where('is_deleted', 0);
        $categories = Category::where(['parent_id' => NULL, 'is_active' => 1, 'is_deleted' => 0])->get();
        $sortBy = ($request->input('sortBy')) ? $request->input('sortBy') : 'created_at';
        $order = ($request->input('order')) ? $request->input('order') : 'DESC';
        $records_per_page = ($request->input('per_page')) ? $request->input('per_page') : Config("Reading.records_per_page");
        $results = $DB->orderBy($sortBy, $order)->paginate($records_per_page);
        $complete_string = $request->query();
        unset($complete_string["sortBy"]);
        unset($complete_string["order"]);
        $query_string = http_build_query($complete_string);
        $results->appends($inputGet)->render();
        return View("admin.$this->model.index", compact('results', 'searchVariable', 'sortBy', 'order', 'query_string', 'categories' ,'sub_categories'));
    }

    // public function create()
    // {
    //     $languages = Language::where('is_active', 1)->get();
    //     $language_code = Config('constants.DEFAULT_LANGUAGE.LANGUAGE_CODE');
    //     return View("admin.$this->model.add", compact('languages', 'language_code'));
    // }

    // public function save(Request $request){
    //     $thisData                    =    $request->all();
    //     $default_language            =    Config('constants.DEFAULT_LANGUAGE.FOLDER_CODE');
    //     $language_code                 =   Config('constants.DEFAULT_LANGUAGE.LANGUAGE_CODE');
    //     $dafaultLanguageArray        =    $thisData['data'][$language_code];
    //     $validator = Validator::make(
    //         array(
    //             'name'             => $dafaultLanguageArray['name'],
    //         ),
    //         array(
    //             'name'             => 'required',
    //         ),
    //     );
    //     if ($validator->fails()) {
    //         return redirect()->back()->withErrors($validator)->withInput();
    //     } else {
    //         $obj             = new Size;
    //         $obj->name       = $dafaultLanguageArray['name'];
    //         $obj->is_active  = 1;
    //         $obj->save();
    //         $lastId = $obj->id;
    //         if (!empty($thisData)) {
    //             foreach ($thisData['data'] as $language_id => $value) {
    //                 $subObj               = new SizeDescription();
    //                 $subObj->language_id  = $language_id;
    //                 $subObj->parent_id    = $lastId;
    //                 $subObj->name         = $value['name'];
    //                 $subObj->save(); 
    //             }
    //         }
    //         Session()->flash('success', Config('constants.SIZE.SIZE_TITLE') . " has been added successfully");
    //         return Redirect()->route($this->model . ".index");
    //     }
    // }

    public function show($encmsid){
        $cms_id = '';
        if (!empty($encmsid)) {
            $cms_id = base64_decode($encmsid);
        } else {
            return Redirect()->route($this->model . ".index");
        }
        $ProductDetails   =  Product::with(['userDetails','parentCategoryDetails','subCategoryDetails','prodcutColorDetails','prodcutColorDetails.prodcutVariantDetails'])->find($cms_id);
        $data = compact('ProductDetails');
        return view("admin.$this->model.view", $data);
    }

    // public function edit($enfaqid){
    //     $product_id = '';
    //     $multiLanguage =    array();
    //     if (!empty($enfaqid)) {
    //         $product_id = base64_decode($enfaqid);
    //         $ColorDetails   =   Product::find($product_id);
            
    //         $languages = Language::where('is_active', 1)->get();
    //         $language_code = Config('constants.DEFAULT_LANGUAGE.LANGUAGE_CODE');
    //         return View("admin.$this->model.edit", compact('multiLanguage', 'ColorDetails', 'languages', 'language_code'));
    //     } else {
    //         return Redirect()->route($this->model . ".index");
    //     }
    // }

    // public function update(Request $request, $enfaqid){
    //     $product_id = '';
    //     $multiLanguage =    array();
    //     if (!empty($enfaqid)) {
    //         $product_id = base64_decode($enfaqid);
    //     } else {
    //         return Redirect()->route($this->model . ".index");
    //     }
    //     $thisData                    =    $request->all();
    //     $default_language            =    Config('constants.DEFAULT_LANGUAGE.FOLDER_CODE');
    //     $language_code                 =   Config('constants.DEFAULT_LANGUAGE.LANGUAGE_CODE');
    //     $dafaultLanguageArray        =    $thisData['data'][$language_code];
    //     $validator = Validator::make(
    //         array(
    //             'name'                 => $dafaultLanguageArray['name'],
    //         ),
    //         array(
    //             'name'                 => 'required',
    //         ),
    //     );
    //     if ($validator->fails()) {
    //         return redirect()->back()->withErrors($validator)->withInput();
    //     } else {
    //         $obj              =   Size::find($product_id);
    //         $obj->name        = $dafaultLanguageArray['name'];
    //         $obj->save();
    //         $lastId  =  $obj->id;
    //         if (!empty($thisData)) {
    //             foreach ($thisData['data'] as $language_id => $value) {
    //                 $subObj                = SizeDescription::where('parent_id', $lastId)->where('language_id', $language_id)->first();
    //                 $subObj->language_id   = $language_id;
    //                 $subObj->parent_id     = $lastId;
    //                 $subObj->name          = $value['name'];
    //                 $subObj->save();
    //             }
    //         }
    //         Session()->flash('success', Config('constants.SIZE.SIZE_TITLE') .  " has been updated successfully");
    //         return Redirect()->route($this->model . ".index");
    //     }
    // }

    public function destroy($enfaqid){
        $product_id = '';
        if (!empty($enfaqid)) {
            $product_id = base64_decode($enfaqid);
        } else {
            return Redirect()->route($this->model . ".index");
        }
        $ProductDetails               =  Product::where('id', $product_id)->first();
        $ProductDetails->is_deleted   = 1;
        $ProductDetails->save();

        Session()->flash('flash_notice', trans(Config('constants.PRODUCT.PRODUCT_TITLE') . " has been removed successfully"));
        return back();
    }

    public function approveStatus($modelId = 0, $status = 0)
    {
        
        $product = Product::with(['parentCategoryDetails', 'subCategoryDetails'])->find($modelId);
        $user = User::where('id', $product->user_id)->first();
        if ($product) {
            
            if ($status == 1) {
                $mailStatus = 'approved';
                $statusMessage = trans(Config('constants.PRODUCT.PRODUCT_TITLE') . ' has been approved successfully');
            }else if($status == 2) {
                $mailStatus = 'rejected';
                $statusMessage = trans(Config('constants.PRODUCT.PRODUCT_TITLE') . ' has been rejected successfully');
            }

            $product->is_approved = $status;
            $ResponseStatus = $product->save();

            $settingsEmail = Config('Site.from_email');
            $language_id        =  get_admin_current_language();

            $emailActions		=  EmailAction::where('action', '=', $mailStatus)->get()->toArray();

            $emailTemplates     =  EmailTemplate::where('action', '=', $mailStatus)->select("name", "action", DB::raw("(select subject from email_template_descriptions where parent_id=email_templates.id AND language_id=$language_id) as subject"), DB::raw("(select body from email_template_descriptions where parent_id=email_templates.id AND language_id=$language_id) as body"))->get()->toArray();
            $cons 			= 	explode(',',$emailActions[0]['options']);
            $constants 		= 	array();
            foreach($cons as $key => $val){
                $constants[] = '{'.$val.'}';
            }
            $subject 		= 	$emailTemplates[0]['subject'];
            $rep_Array 		= 	array(
                $user->name ?? '',
                $user->email ?? '',
                $user->phone_number ?? '',
                $product->name ?? '',
                $product?->parentCategoryDetails?->name,
                $product?->subCategoryDetails?->name,
            );
            $messageBody 	= 	str_replace($constants, $rep_Array, $emailTemplates[0]['body']);
            $this->sendMail($user->email,$user->nmae,$subject,$messageBody,$settingsEmail);
        }
        Session()->flash('flash_notice', $statusMessage);
        return back();
    }

    // public function changeStatus($modelId = 0, $status = 0)
    // {
    //     $product = Product::find($modelId);
    //     if ($product) {
    //         $currentStatus = $product->is_active;
    //         if (isset($currentStatus) && $currentStatus == 0) {
    //             $NewStatus = 1;
    //         } else {
    //             $NewStatus = 0;
    //         }

    //         if ($NewStatus == 1) {
    //             $statusMessage = trans(Config('constants.PRODUCT.PRODUCT_TITLE') . ' has been activated successfully');
    //         } else {
    //             $statusMessage = trans(Config('constants.PRODUCT.PRODUCT_TITLE') . ' has been deactivated successfully');
    //         }

    //         $product->is_active = $NewStatus;
    //         $ResponseStatus = $product->save();
    //     }
    //     Session()->flash('flash_notice', $statusMessage);
    //     return back();
    // }

    public function getSubCategory(Request $request){
        if($request->catId != null){
            $subcategory  =  Category::where('parent_id', $request->catId)->where(['is_active' => 1, 'is_deleted' => 0])->get();
            if($subcategory){
                return response()->json([
                    'status'   => 'success',
                    'subcategory' => $subcategory
                ]);
            }else{
                return response()->json([
                    'status'   => 'error',
                    'msg'      => 'Something Went Wrong.'
                ]);
            }
        }

    }
}
