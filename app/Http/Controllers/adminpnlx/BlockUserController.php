<?php

namespace App\Http\Controllers\adminpnlx;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BlockUser;

class BlockUserController extends Controller
{
    public $model = 'block-users';
    public function __construct(Request $request){
        parent::__construct();
        View()->share('model', $this->model);
        $this->request = $request;
    }

    public function index(Request $request){
        $DB = BlockUser::query();
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
                        $DB->where("user.name", 'like', '%' . $fieldValue . '%');
                    }
                    if ($fieldName == "block-user" && $fieldValue != '') {
                        $DB->where("block_user.name", 'like', '%' . $fieldValue . '%');
                    }
                }
                $searchVariable = array_merge($searchVariable, array($fieldName => $fieldValue));
            }
        }
        // $DB->where('is_deleted', 0);
        // $DB->where('parent_id', NULL);
        $DB->leftJoin('users as user', 'user.id', '=', 'block_users.user_id')
        ->leftJoin('users as block_user', 'block_user.id', '=', 'block_users.block_user_id')
            ->select(
                'block_users.*',
                'user.name as userName',
                'block_user.name as blockUserName'
            );
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

    public function show($encmsid){
        $cms_id = '';
        if (!empty($encmsid)) {
            $cms_id = base64_decode($encmsid);
        } else {
            return Redirect()->route($this->model . ".index");
        }
        $blockUserDetails   =  BlockUser::find($cms_id);
        $data = compact('blockUserDetails');
        return view("admin.$this->model.view", $data);
    }
}
