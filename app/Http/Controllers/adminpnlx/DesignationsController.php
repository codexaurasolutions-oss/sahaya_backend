<?php

namespace App\Http\Controllers\adminpnlx;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

use App\Models\Acl;
use App\Models\Department;
use App\Models\Designation;
use App\Models\AclAdminAction;
use App\Models\DesignationPermission;
use App\Models\DesignationPermissionAction;

class DesignationsController extends Controller
{
    public $model =    'designations';
    public function __construct(Request $request)
    {   
        parent::__construct();
        View()->share('model', $this->model);
        $this->request = $request;
    }

    public function index(Request $request)
    {
        
        $DB                    =    Designation::query();
        $searchVariable        =    array();
        $inputGet            =    $request->all();
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
            if ((!empty($searchData['date_from'])) && (!empty($searchData['date_to']))) {
                $dateS = $searchData['date_from'];
                $dateE = $searchData['date_to'];
                $DB->whereBetween('designations.created_at', [$dateS . " 00:00:00", $dateE . " 23:59:59"]);
            } elseif (!empty($searchData['date_from'])) {
                $dateS = $searchData['date_from'];
                $DB->where('designations.created_at', '>=', [$dateS . " 00:00:00"]);
            } elseif (!empty($searchData['date_to'])) {
                $dateE = $searchData['date_to'];
                $DB->where('designations.created_at', '<=', [$dateE . " 00:00:00"]);
            }
            foreach ($searchData as $fieldName => $fieldValue) {
                if ($fieldValue != "") {
                    if ($fieldName == "name") {
                        $DB->where("designations.name", 'like', '%' . $fieldValue . '%');
                    }
                    if ($fieldName == "is_active") {
                        $DB->where("designations.is_active", $fieldValue);
                    }
                }
                $searchVariable    =    array_merge($searchVariable, array($fieldName => $fieldValue));
            }
        }
        $DB->where("is_deleted", 0);
        $DB->select("designations.*");
        $sortBy = ($request->input('sortBy')) ? $request->input('sortBy') : 'created_at';
        $order  = ($request->input('order')) ? $request->input('order')   : 'DESC';
        $records_per_page    =    ($request->input('per_page')) ? $request->input('per_page') : Config("Reading.records_per_page");
        $results = $DB->orderBy($sortBy, $order)->paginate($records_per_page);
        $complete_string        =    $request->query();
        unset($complete_string["sortBy"]);
        unset($complete_string["order"]);
        $query_string            =    http_build_query($complete_string);
        $results->appends($inputGet)->render();
        return  View("admin.$this->model.index", compact('results', 'searchVariable', 'sortBy', 'order', 'query_string'));
    }

    public function add(Request $request)
    {   

        $formData =    $request->all();
        if ($request->isMethod('POST')) {
            $validated = $request->validate([
                'name' => 'required'
            ]);
            $obj                        =  new Designation;
            $obj->name                  =  $request->name;
            $SavedResponse = $obj->save();
            $userId                    =    $obj->id;
            if (!empty($formData['data'])) {
                $id    =    $userId;
                foreach ($formData['data'] as $data) {
                    if(!empty($data['designation_id']) && $data['designation_id'] > 0){
                        $obj                        =  new DesignationPermission;
                        $obj['designation_id']      =  !empty($id) ? $id : 0;
                        $obj['admin_module_id']     =  !empty($data['designation_id']) ? $data['designation_id'] : 0;
                        $obj['is_active']           =  !empty($data['value']) ? $data['value'] : 0;
                        $obj->save();
                        $userpermissionID        =    $obj->id;
                        if (isset($data['module']) && !empty($data['module'])) {
                            $objData        =   [];
                            $counter= 0;
                            foreach ($data['module'] as $subModule) {
                                $objData[$counter]['designation_id']              =  !empty($id) ? $id : 0;
                                $objData[$counter]['designation_permission_id']   =  $userpermissionID;
                                $objData[$counter]['admin_module_id']             =  !empty($data['designation_id']) ? $data['designation_id'] : 0;
                                $objData[$counter]['admin_sub_module_id']         =  !empty($subModule['designation_module_id']) ? $subModule['designation_module_id'] : 0;
                                $objData[$counter]['admin_module_action_id']      =  !empty($subModule['id']) ? $subModule['id'] : 0;
                                $objData[$counter]['is_active']                   =  isset($subModule['value']) ? $subModule['value'] : 0;
                                $counter++;
                            }
                            DesignationPermissionAction::insert($objData);
                        }
                    }
                }
            }
            if (!$SavedResponse) {
                Session()->flash('flash_error', trans("Something went wrong."));
                return Redirect()->back()->withInput();
            } else {
                Session()->flash('flash_notice', Config('constants.DESIGNATION.DESIGNATION_TITLE') . " has been added successfully");
                return Redirect()->route($this->model . ".index");
            }
        }
        $aclModules        =    Acl::where('is_active', 1)->where('parent_id', 0)->get();
        if (!empty($aclModules)) {
            foreach ($aclModules as $aclModule) {
                $aclModule['sub_module']    =    Acl::where('is_active', 1)->where('parent_id', $aclModule->id)->select('title', 'id')->get();
                $module_ids            =    array();
                if (!empty($aclModule['sub_module'])) {
                    foreach ($aclModule['sub_module'] as $module) {
                        $module_id        =        $module->id;
                        $module_ids[$module->id]        =        $module->id;
                        $module['module']    =    AclAdminAction::where('admin_module_id', $module->id)->where('is_show', 1)->select('name', 'function_name', 'id')->orderBy('name', 'ASC')->get();
                    }
                }
                $newArray    =    array();
                $aclModule['extModule']    =    Acl::where('is_active', 1)->whereIn('parent_id', $module_ids)->select('title', 'id')->get();
                if (!empty($aclModule['extModule'])) {
                    foreach ($aclModule['extModule'] as &$record) {
                        $action_id            =    $record->id;
                        $record['module']    =    AclAdminAction::where('admin_module_id', $record->id)->where('is_show', 1)->select('name', 'function_name', 'id')->orderBy('name', 'ASC')->get();
                    }
                }
                if (($aclModule['sub_module']->isEmpty()) && ($aclModule['extModule']->isEmpty())) {
                    $action_id            =    $aclModule->id;
                    $aclModule['parent_module_action']    =    AclAdminAction::where('admin_module_id', $aclModule->id)->where('is_show', 1)->select('name', 'function_name', 'id')->orderBy('name', 'ASC')->get();
                }
            }
        }
        return  View("admin.$this->model.add", compact( 'aclModules'));
    }

    public function update(Request $request, $endesid = null)
    {
        $des_id = '';
        if (!empty($endesid)) {
            $des_id = base64_decode($endesid);
        }
        $modell =    Designation::where('designations.id', $des_id)->first();
        if (empty($modell)) {
            return Redirect()->back();
        }
        if ($request->isMethod('POST')) {
            $formData =    $request->all();
            $validated = $request->validate([
                'name' => 'required'
            ]);
            $obj             =  Designation::find($des_id);
            $obj->name       =  $request->input('name');
            $obj->save();
            $userId          =    $obj->id;
            if (!$userId) {
                Session()->flash('flash_error', trans("Something went wrong."));
                return Redirect()->back()->withInput();
            }
            if (!empty($formData['data'])) {
                $id    =    $userId;
                foreach ($formData['data'] as $data) {
                    $adminid = !empty($data['designation_id']) ? $data['designation_id'] : 0;
                    $desi_id = DesignationPermission::where('designation_id', $id)->where('admin_module_id', $adminid)->get('id');
                   
                    if (count($desi_id) > 0) {
                        foreach ($desi_id as $des_id) {
                            $desi = DesignationPermission::find($des_id->id);
                            $value1 =   !empty($data['value']) ? $data['value'] : 0;
                            DesignationPermission::where('id', $desi->id)->update(array('is_active' => $value1));
                            $userpermissionID =     $des_id->id;
                        }
                    } else {
                        $obj   = new  DesignationPermission;
                        $obj['designation_id']    =  !empty($id) ? $id : 0;
                        $obj['admin_module_id']    =  !empty($data['designation_id']) ? $data['designation_id'] : 0;
                        $obj['is_active']        =  !empty($data['value']) ? $data['value'] : 0;
                        $obj->save();
                        $userpermissionID =     $obj->id;
                    }
                    if (isset($data['module']) && !empty($data['module'])) {
                        foreach ($data['module'] as $subModule) {
                            $adminaction =  !empty($subModule['id']) ? $subModule['id'] : 0;
                            $adminacl =  !empty($subModule['designation_module_id']) ? $subModule['designation_module_id'] : 0;
                            $desiper =  DesignationPermissionAction::where('designation_id', $id)->where('admin_sub_module_id', $adminacl)->where('admin_module_action_id', $adminaction)->get('id');
                    
                            if (count($desiper) > 0) {
                                foreach ($desiper as $des_id) {
                                    $desi = DesignationPermissionAction::find($des_id->id);
                                    $value1 =   !empty($subModule['value']) ? $subModule['value'] : 0;
                                    DesignationPermissionAction::where('id', $desi->id)->update(array('is_active' => $value1));
                                    $userpermissionID =     $des_id->id;
                                }
                            } else {
                                $objData                             = new  DesignationPermissionAction;
                                $objData['designation_id']            =  !empty($id) ? $id : 0;
                                $objData['designation_permission_id'] =  $userpermissionID;
                                $objData['admin_module_id']            =  !empty($data['designation_id']) ? $data['designation_id'] : 0;
                                $objData['admin_sub_module_id']     =  !empty($subModule['designation_module_id']) ? $subModule['designation_module_id'] : 0;
                                $objData['admin_module_action_id']    =  !empty($subModule['id']) ? $subModule['id'] : 0;
                                $objData['is_active']                =  !empty($subModule['value']) ? $subModule['value'] : 0;
                                $objData->save();
                            }
                        }
                    }
                }
            }
            Session()->flash('flash_notice', trans(Config('constants.DESIGNATION.DESIGNATION_TITLE') . " has been updated successfully"));
            return Redirect()->route($this->model . ".index");
        }
        $aclModules    = Acl::select('title', 'id', DB::Raw("(select is_active from designation_permissions where designation_id = $modell->id AND admin_module_id = acls.id LIMIT 1) as active"))->where('is_active', 1)->where('parent_id', 0)->get();
        // dd(  $aclModules);
        if (!empty($aclModules)) {
            foreach ($aclModules as &$aclModule) {
                $aclModule['sub_module']    =    Acl::where('is_active', 1)->where('parent_id', $aclModule->id)->select('title', 'id')->get();
                $module_ids            =    array();
                if (!empty($aclModule['sub_module'])) {
                    foreach ($aclModule['sub_module'] as &$module) {
                        $module_id        =        $module->id;
                        $module_ids[$module->id]        =        $module->id;
                        $module['module']    =    AclAdminAction::where('admin_module_id', $module->id)->where('is_show', 1)->select('name', 'function_name', 'id', DB::Raw("(select is_active from designation_permission_actions where designation_id = $modell->id AND admin_sub_module_id = $module_id AND admin_module_action_id = acl_admin_actions.id LIMIT 1) as active"))->orderBy('name', 'ASC')->get();
                    }
                }
                $newArray    =    array();
                $aclModule['extModule']    =    Acl::where('is_active', 1)->whereIn('parent_id', $module_ids)->select('title', 'id')->get();
                if (!empty($aclModule['extModule'])) {
                    foreach ($aclModule['extModule'] as &$record) {
                        $action_id            =    $record->id;
                        $record['module']    =    AclAdminAction::where('admin_module_id', $record->id)->where('is_show', 1)->select('name', 'function_name', 'id', DB::Raw("(select is_active from designation_permission_action where designation_id = $modell->id AND admin_sub_module_id = $action_id AND admin_module_action_id = acl_admin_actions.id LIMIT 1) as active"))->orderBy('name', 'ASC')->get();
                    }
                }
                if (($aclModule['sub_module']->isEmpty()) && ($aclModule['extModule']->isEmpty())) {
                    $action_id            =    $aclModule->id;
                    $aclModule['parent_module_action']    =    AclAdminAction::where('admin_module_id', $aclModule->id)->where('is_show', 1)->select('name', 'function_name', 'id', DB::Raw("(select is_active from designation_permission_actions where designation_id = $modell->id AND admin_sub_module_id = $action_id AND admin_module_action_id = acl_admin_actions.id LIMIT 1) as active"))->orderBy('name', 'ASC')->get();
                }
            }
        }
        return  View("admin.$this->model.edit", compact('modell', 'aclModules'));
    }


    public function changeStatus($modelId = 0, $status = 0){
        if ($status == 1) {
            $statusMessage   =   trans(Config('constants.DESIGNATION.DESIGNATION_TITLE') . " has been deactivated successfully");
        } else {
            $statusMessage   =   trans(Config('constants.DESIGNATION.DESIGNATION_TITLE') . " has been activated successfully");
        }
        $user = Designation::find($modelId);
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

    public  function delete($endesid = null)
    {
        $dep_id = '';
        if (!empty($endesid)) {
            $dep_id = base64_decode($endesid);
        }
        $depDetails   =   Designation::find($dep_id);
        if (empty($depDetails)) {
            return Redirect()->route($this->model . '.index');
        }
        if ($dep_id) {
            Designation::where('id', $dep_id)->update(array('is_deleted' => 1));
            Session()->flash('flash_notice', trans(Config('constants.DESIGNATION.DESIGNATION_TITLE') . " has been removed successfully"));
        }
        return back();
    }
}
