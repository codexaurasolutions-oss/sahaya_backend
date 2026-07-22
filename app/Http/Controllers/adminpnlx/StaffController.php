<?php

namespace App\Http\Controllers\adminpnlx;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\DB;
use App\Models\Acl;
use  App\Models\Department;
use  App\Models\Designation;
use App\Models\AclAdminAction;
use App\Models\DesignationPermission;
use App\Models\DesignationPermissionAction;
use  App\Models\UserPermission;
use App\Models\UserPermissionAction;

class StaffController extends Controller
{
    public $model =  'staff';
    public function __construct(Request $request){
        parent::__construct();
        View()->share('model', $this->model);
        $this->request  = $request;
    }

    public function index(Request $request)
    {
        $DB                    =    Admin::query();
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
                $DB->whereBetween('admins.created_at', [$dateS . " 00:00:00", $dateE . " 23:59:59"]);
            } elseif (!empty($searchData['date_from'])) {
                $dateS = $searchData['date_from'];
                $DB->where('admins.created_at', '>=', [$dateS . " 00:00:00"]);
            } elseif (!empty($searchData['date_to'])) {
                $dateE = $searchData['date_to'];
                $DB->where('admins.created_at', '<=', [$dateE . " 00:00:00"]);
            }
            foreach ($searchData as $fieldName => $fieldValue) {
                if ($fieldValue != "") {
                    if ($fieldName == "name") {
                        $DB->where("admins.name", 'like', '%' . $fieldValue . '%');
                    }
                    if ($fieldName == "phone_number") {
                        $DB->where("admins.phone_number", 'like', '%' . $fieldValue . '%');
                    }
                    if ($fieldName == "email") {
                        $DB->where("admins.email", 'like', '%' . $fieldValue . '%');
                    }
                    if ($fieldName == "is_active") {
                        $DB->where("admins.is_active", $fieldValue);
                    }
                }
                $searchVariable    =    array_merge($searchVariable, array($fieldName => $fieldValue));
            }
        }
        $DB->where("user_role_id", "!=",1);
        $DB->where("is_deleted", 0);
        $DB->select("admins.*");
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
    
    public function create()
    {
        $departments = Designation::where('is_deleted', 0)->where('is_active', 1)->get();
        $designations = array();
        return  View("admin.$this->model.add", compact('departments', 'designations'));
    }

    public function store(Request $request)
    {   
        $formData                        =    $request->all();
        if (!empty($formData)) {
            $validated = $request->validate([
                'name'          =>        'required|min:2|max:40',
                'email'         =>         'required|email|max:60|unique:admins',
                // 'phone_number'  =>         'required|numeric|unique:admins',
                'password'      =>         ['required', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
                'confirm_password' =>      'required|same:password',
                'designation_id' =>         'required',
            ]);
          
            $obj                                     =  new Admin;
            $obj->user_role_id                        =  Config('constants.ROLE_ID.STAFF_ROLE_ID');
            $obj->name                                 =  $request->input('name');
            $obj->email                             =  $request->input('email');
            
            $obj->designation_id                     =  $request->input('designation_id');
            $obj->password                             =  Hash::make($request->input('password'));
            $obj->save();
            $userId                                    =    $obj->id;
            $staffInfo  =  Admin::where('id', $userId)->first();
            if ($obj->save()) {
                if (!empty($formData['data'])) {
                    $id    =    $userId;
                    UserPermission::where('user_id', $id)->delete();
                    UserPermissionAction::where('user_id', $id)->delete();
                    foreach ($formData['data'] as $data) {
                        $obj                     =     new  UserPermission;
                        $obj['user_id']            =  !empty($id) ? $id : 0;
                        $obj['admin_module_id']    =  !empty($data['department_id']) ? $data['department_id'] : 0;
                        $obj['is_active']        =  !empty($data['value']) ? $data['value'] : 0;
                        $userpermissiondata     =     $obj->save();
                        $userpermissionID        =    $obj->id;

                        // if (isset($data['module']) && !empty($data['module'])) {
                        //     foreach ($data['module'] as $subModule) {
                        //         $objData                             =  new UserPermissionAction;
                        //         $objData['user_id']                  =  !empty($id) ? $id : 0;
                        //         $objData['user_permission_id']       =  $userpermissionID;
                        //         $objData['admin_module_id']          =  !empty($data['department_id']) ? $data['department_id'] : 0;
                        //         $objData['admin_sub_module_id']      =  !empty($subModule['department_module_id']) ? $subModule['department_module_id'] : 0;
                        //         $objData['admin_module_action_id']   =  !empty($subModule['id']) ? $subModule['id'] : 0;
                        //         $objData['is_active']                =  !empty($subModule['value']) ? $subModule['value'] : 0;
                        //         $objData->save();
                        //     }
                        // }

                        if (isset($data['module']) && !empty($data['module'])) {
                            foreach ($data['module'] as $subModule) {
                                $objData                             = new UserPermissionAction;
                                $objData['user_id']                    =  !empty($id) ? $id : 0;
                                $objData['user_permission_id']        =  $userpermissionID;
                                $objData['admin_module_id']            =  !empty($data['department_id']) ? $data['department_id'] : 0;
                                $objData['admin_sub_module_id']     =  !empty($subModule['department_module_id']) ? $subModule['department_module_id'] : 0;
                                $objData['admin_module_action_id']    =  !empty($subModule['id']) ? $subModule['id'] : 0;
                                $objData['is_active']                =  !empty($subModule['value']) ? $subModule['value'] : 0;
                                $objData->save();
                            }
                        }
                    }
                }
            }
            if (!$userId) {
                Session()->flash(trans("Something went wrong."));
                return Redirect()->back()->withInput();
            }
            Session()->flash('success', Config('constants.STAFF.STAFF_TITLE') . trans("has been added successfully"));
            return Redirect()->route($this->model . ".index");
        }
    }

    public function show($enstfid)
    {
        $stf_id = '';
        if (!empty($enstfid)) {
            $stf_id = base64_decode($enstfid);
        }
        $aclModules    = Acl::select('title', 'id', DB::Raw("(select is_active from user_permissions where user_id = $stf_id AND admin_module_id = acls.id LIMIT 1) as active"))->where('is_active', 1)->where('parent_id', 0)->get();
        if (!empty($aclModules)) {
            foreach ($aclModules as &$aclModule) {
                $aclModule['sub_module']    =    Acl::where('is_active', 1)->where('parent_id', $aclModule->id)->select('title', 'id')->get();
                $module_ids            =    array();
                if (!empty($aclModule['sub_module'])) {
                    foreach ($aclModule['sub_module'] as &$module) {
                        $module_id        =        $module->id;
                        $module_ids[$module->id]        =        $module->id;
                        $module['module']    =    AclAdminAction::where('admin_module_id', $module->id)->where('is_show', 1)->select('name', 'function_name', 'id', DB::Raw("(select is_active from user_permission_actions where user_id = $stf_id AND admin_sub_module_id = $module_id AND admin_module_action_id = acl_admin_actions.id LIMIT 1) as active"))->orderBy('name', 'ASC')->get();
                    }
                }
                $newArray    =    array();
                $aclModule['extModule']    =    Acl::where('is_active', 1)->whereIn('parent_id', $module_ids)->select('title', 'id')->get();
                if (!empty($aclModule['extModule'])) {
                    foreach ($aclModule['extModule'] as &$record) {
                        $action_id            =    $record->id;
                        $record['module']    =    AclAdminAction::where('admin_module_id', $record->id)->where('is_show', 1)->select('name', 'function_name', 'id', DB::Raw("(select is_active from user_permission_actions where user_id = $stf_id AND admin_sub_module_id = $action_id AND admin_module_action_id = acl_admin_actions.id LIMIT 1) as active"))->orderBy('name', 'ASC')->get();
                    }
                }
                if (($aclModule['sub_module']->isEmpty()) && ($aclModule['extModule']->isEmpty())) {
                    $action_id            =    $aclModule->id;
                    $aclModule['parent_module_action']    =    AclAdminAction::where('admin_module_id', $aclModule->id)->where('is_show', 1)->select('name', 'function_name', 'id', DB::Raw("(select is_active from user_permission_actions where user_id = $stf_id AND admin_sub_module_id = $action_id AND admin_module_action_id = acl_admin_actions.id LIMIT 1) as active"))->orderBy('name', 'ASC')->get();
                }
            }
        }
        $modell = Admin::find($stf_id);
        if (empty($modell)) {
            return Redirect()->back();
        }
        return  View("admin.$this->model.view", compact('modell', 'aclModules'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($enstfid)
    {
        $stf_id = '';
        if (!empty($enstfid)) {
            $stf_id = base64_decode($enstfid);
        }
        $modell     =    Admin::find($stf_id);
        if (empty($modell)) {
            return Redirect()->back();
        }
        $departments = Designation::where('is_deleted', 0)->where('is_active', 1)->get();
        
        
        $designations = array();
        $aclModules        =    Acl::select('title', 'id', DB::Raw("(select is_active from user_permissions where user_id = $stf_id AND admin_module_id = acls.id LIMIT 1) as active"))->where('is_active', 1)->where('parent_id', 0)->get();
        
        if (!empty($aclModules)) {
            foreach ($aclModules as &$aclModule) {
                $aclModule['sub_module']    =    Acl::where('is_active', 1)->where('parent_id', $aclModule->id)->select('title', 'id')->get();
                $module_ids            =    array();
                if (!empty($aclModule['sub_module'])) {
                    foreach ($aclModule['sub_module'] as &$module) {
                        $module_id        =        $module->id;
                        $module_ids[$module->id]        =        $module->id;
                        $module['module']    =    AclAdminAction::where('admin_module_id', $module->id)->where('is_show', 1)->select('name', 'function_name', 'id', DB::Raw("(select is_active from user_permission_actions where user_id = $stf_id AND admin_sub_module_id = $module_id AND admin_module_action_id = acl_admin_actions.id LIMIT 1) as active"))->orderBy('name', 'ASC')->get();
                    }
                    $newArray    =    array();
                    $aclModule['extModule']    =    Acl::where('is_active', 1)->whereIn('parent_id', $module_ids)->select('title', 'id')->get();
                    if (!empty($aclModule['extModule'])) {
                        foreach ($aclModule['extModule'] as &$record) {
                            $action_id            =    $record->id;
                            $record['module']    =    AclAdminAction::where('admin_module_id', $record->id)->where('is_show', 1)->select('name', 'function_name', 'id', DB::Raw("(select is_active from user_permission_actions where user_id = $stf_id AND admin_sub_module_id = $action_id AND admin_module_action_id = acl_admin_actions.id LIMIT 1) as active"))->orderBy('name', 'ASC')->get();
                        }
                    }
                    if (($aclModule['sub_module']->isEmpty()) && ($aclModule['extModule']->isEmpty())) {
                        $action_id            =    $aclModule->id;
                        $aclModule['parent_module_action']    =    AclAdminAction::where('admin_module_id', $aclModule->id)->where('is_show', 1)->select('name', 'function_name', 'id', DB::Raw("(select is_active from user_permission_actions where user_id = $stf_id AND admin_sub_module_id = $action_id AND admin_module_action_id = acl_admin_actions.id LIMIT 1) as active"))->orderBy('name', 'ASC')->get();
                    }
                    
                }
            }

            //echo "<pre>";print_r($aclModules);die;
            $designation = Designation::where('id',$modell->designation_id ?? '')->first();
                      
            return  View("admin.$this->model.edit", compact('modell', 'departments', 'designations', 'aclModules','designation'));
    }
}

    
    public function update(Request $request, $enstfid)
    {   
        $stf_id = '';
        if (!empty($enstfid)) {
            $stf_id = base64_decode($enstfid);
        }
        $formData                        =    $request->all();
        if (!empty($formData)) {
            $validated = $request->validate([
                'name'              =>        'required',
                'email'             =>        'required|email',
                'designation_id'    =>        'required',
            ]);
            $obj                        =  Admin::find($stf_id);
            $obj->user_role_id          =  Config('constants.ROLE_ID.STAFF_ROLE_ID');
            $obj->name                  =  $request->input('name');
            $obj->email                 =  $request->input('email');
            $obj->designation_id        =  $request->input('designation_id');
            $obj->save();
            $userId                     =  $obj->id;
            $staffInfo                  =  Admin::where('id', $userId)->first();
            if ($obj->save()) {
                if (!empty($formData['data'])) {

                    $id    =    $userId;
                    UserPermission::where('user_id', $id)->delete();
                    UserPermissionAction::where('user_id', $id)->delete();
              
                    foreach ($formData['data'] as $data) {
                        $obj                        =  new UserPermission;
                        $obj['user_id']             =  !empty($id) ? $id : 0;
                        $obj['admin_module_id']     =  !empty($data['department_id']) ? $data['department_id'] : 0;
                        $obj['is_active']           =  !empty($data['value']) ? $data['value'] : 0;
                        $userpermissiondata         =  $obj->save();
                        $userpermissionID           =  $obj->id;

                        if (isset($data['module']) && !empty($data['module'])) {
                            foreach ($data['module'] as $subModule) {
                                $objData                                =  new UserPermissionAction;
                                $objData['user_id']                     =  !empty($id) ? $id : 0;
                                $objData['user_permission_id']          =  $userpermissionID;
                                $objData['admin_module_id']             =  !empty($data['department_id']) ? $data['department_id'] : 0;
                                $objData['admin_sub_module_id']         =  !empty($subModule['department_module_id']) ? $subModule['department_module_id'] : 0;
                                $objData['admin_module_action_id']      =  !empty($subModule['id']) ? $subModule['id'] : 0;
                                $objData['is_active']                   =  !empty($subModule['value']) ? $subModule['value'] : 0;
                                $objData->save();
                            }
                        }
                    }
                }
            }
            if (!$userId) {
                Session()->flash(trans("Something went wrong."));
                return Redirect()->back()->withInput();
            }
            Session()->flash('success', Config('constants.STAFF.STAFF_TITLE') . trans("has been updated successfully"));
            return Redirect()->route($this->model . ".index");
        }
    }


    public function destroy($enstfid)
    {
        $stf_id = '';
        if (!empty($enstfid)) {
            $stf_id = base64_decode($enstfid);
        }
        $stfDetails   =   Admin::find($stf_id);
        if (empty($stfDetails)) {
            return Redirect()->route($this->model . '.index');
        }
        if ($stf_id) {
            Admin::where('id', $stf_id)->update(array('is_deleted' => 1));
            Session()->flash('flash_notice', trans(Config('constants.STAFF.STAFF_TITLE') . " ". trans("has been removed successfully")));
        }
        return back();
    }

    public function getDesignations(Request $request){
        $departmentid    =    $request->input("departmentid");
        $selctedid    =    $request->input("selctedid");
        $designations = Designation::where("department_id", $departmentid)->where("is_active", 1)->where("is_deleted", 0)->get();
        return  View("admin.$this->model.add_more_designations", compact('designations', 'selctedid'));
    }

    public function getStaffPermission(Request $request){
        if (!empty($request->designation_id)) {
            $userId    =    $request->designation_id;
            $aclModules    = Acl::select('title', 'id', DB::Raw("(select is_active from designation_permissions where designation_id = $userId AND admin_module_id = acls.id LIMIT 1) as active"))->where('is_active', 1)->where('parent_id', 0)->get();
            if (!empty($aclModules)) {
                foreach ($aclModules as &$aclModule) {
                    $aclModule['sub_module']    =    Acl::where('is_active', 1)->where('parent_id', $aclModule->id)->select('title', 'id')->get();
                    $module_ids            =    array();
                    if (!empty($aclModule['sub_module'])) {
                        foreach ($aclModule['sub_module'] as $module) {
                            $module_id        =        $module->id;
                            $module_ids[$module->id]        =        $module->id;
                            $module['module']    =    AclAdminAction::where('admin_module_id', $module->id)->where('is_show', 1)->select('name', 'function_name', 'id', DB::Raw("(select is_active from designation_permission_actions where designation_id = $userId AND admin_sub_module_id = $module_id AND admin_module_action_id = acl_admin_actions.id LIMIT 1) as active"))->orderBy('name', 'ASC')->get();
                        }
                    }
                    $newArray    =    array();
                    $aclModule['extModule']    =    Acl::where('is_active', 1)->whereIn('parent_id', $module_ids)->select('title', 'id')->get();
                    if (!empty($aclModule['extModule'])) {
                        foreach ($aclModule['extModule'] as &$record) {
                            $action_id            =    $record->id;
                            $record['module']    =    AclAdminAction::where('admin_module_id', $record->id)->where('is_show', 1)->select('name', 'function_name', 'id', DB::Raw("(select is_active from designation_permission_action where designation_id = $userId AND admin_sub_module_id = $action_id AND admin_module_action_id = acl_admin_actions.id LIMIT 1) as active"))->orderBy('name', 'ASC')->get();
                        }
                    }
                    if (($aclModule['sub_module']->isEmpty()) && ($aclModule['extModule']->isEmpty())) {
                        $action_id            =    $aclModule->id;
                        $aclModule['parent_module_action']    =    AclAdminAction::where('admin_module_id', $aclModule->id)->where('is_show', 1)->select('name', 'function_name', 'id', DB::Raw("(select is_active from designation_permission_actions where designation_id = $userId AND admin_sub_module_id = $action_id AND admin_module_action_id = acl_admin_actions.id LIMIT 1) as active"))->orderBy('name', 'ASC')->get();
                    }
                }
            }
            return  View("admin.$this->model.staff_permission_data", compact('aclModules'));
        } else {
            return response()->json(['success' => false, 'message' => 'Something went wrong']);
        }
    }

    public function changeStatus($modelId = 0, $status = 0){
        if ($status == 1) {
            $statusMessage   =   trans(Config('constants.STAFF.STAFF_TITLE') . trans("has been deactivated successfully"));
        } else {
            $statusMessage   =   trans(Config('constants.STAFF.STAFF_TITLE') . trans("has been activated successfully"));
        }
        $user = Admin::find($modelId);
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

    public function changedPassword(Request $request, $enstfid = null){
        $stf_id = '';
        if (!empty($enstfid)) {
            $stf_id = base64_decode($enstfid);
        } else {
            return redirect()->route($this->model . ".index");
        }
        if ($request->isMethod('POST')) {
            if (!empty($stf_id)) {
                $validated = $request->validate([
                    'new_password' => ['required', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
                    'confirm_password' => 'required|same:new_password',
                ]);
                $userDetails   =  Admin::find($stf_id);
                $userDetails->password     =  Hash::make($request->new_password);
                $SavedResponse =  $userDetails->save();
                if (!$SavedResponse) {
                    Session()->flash('error', trans("Something went wrong."));
                    return Redirect()->back();
                }
                Session()->flash('success', trans("User password changed successfully").'.');
                return Redirect()->route($this->model . '.index');
            }
        }
        $stfDetails = array();
        $stfDetails   =  Admin::find($stf_id);
        $data = compact('stfDetails');
        return view("admin.$this->model.change_password", $data);
    }
}
