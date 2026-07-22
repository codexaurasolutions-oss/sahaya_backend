<?php
namespace App\Http\Controllers\adminpnlx;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Language;
use Carbon\Carbon;
use App\Models\ContactUs;
use App\Models\ContactUsReply;
use App\Models\EmailAction;
use App\Models\EmailTemplate;
use Blade,Config,Cache,Cookie,DB,File,Hash,Input,Mail,Redirect,Response,Session,URL,View,Validator;



class ContactQueryController extends Controller
{
    public $model		        =	'ContactQuery';
	public $sectionName	        =	'Contact Queries';
	public $sectionNameSingular	=	'Contact Query';
    
    public $modelName            =    'ContactQuery';

    public function __construct(Request $request)
    {   
        parent::__construct();
        View()->share('model', $this->model);
        View()->share('modelName', $this->modelName);
        View()->share('sectionNameSingular', $this->sectionNameSingular);
        $this->request = $request;
    }

    public function index(Request $request){

		if($request->status == 'archive'){
			$DB			    =	ContactUs::query();
		}else{
			$DB			    =	ContactUs::where('status','<>','archive');
		}


		$searchVariable		=	array();
		$inputGet			=	$request->all();
		if ($request->all()) {
			$searchData			=	$request->all();

			if ($request->action && $request->selectedSubscribers) {
                if ($request->action == "delete") {
              
                    $statusMessage   =   "Contact us has been removed successfully";

                    ContactUs::whereIn('contact_us.id', $request->selectedSubscribers)->delete();
					ContactUsReply::whereIn('contact_replies.contact_id', $request->selectedSubscribers)->delete();
                    Session()->flash('flash_notice', $statusMessage);
                }
                return back();

            }

			unset($searchData['display']);
			unset($searchData['_token']);

			if(isset($searchData['order'])){
				unset($searchData['order']);
			}
			if(isset($searchData['sortBy'])){
				unset($searchData['sortBy']);
			}
			if(isset($searchData['page'])){
				unset($searchData['page']);
			}

			if((!empty($searchData['date_from'])) && (!empty($searchData['date_to']))){
				$dateS = $searchData['date_from'];
				$dateE = $searchData['date_to'];
				$DB->whereBetween('contact_us.created_at', [$dateS." 00:00:00", $dateE." 23:59:59"]);
			}elseif(!empty($searchData['date_from'])){
				$dateS = $searchData['date_from'];
				$DB->where('contact_us.created_at','>=' ,[$dateS." 00:00:00"]);
			}elseif(!empty($searchData['date_to'])){
				$dateE = $searchData['date_to'];
				$DB->where('contact_us.created_at','<=' ,[$dateE." 00:00:00"]);
			}elseif(!empty($searchData['status'])){
				$status = $searchData['status'];
				$DB->where('contact_us.status','=',$status);
			}

			foreach($searchData as $fieldName => $fieldValue){
                if($fieldValue != ""){

					if ($fieldName == "name") {
                        $DB->where("contact_us.name", 'like', '%' . $fieldValue . '%');
                    }

					if ($fieldName == "email") {
                        $DB->where("contact_us.email", 'like', '%' . $fieldValue . '%');
                    }

					if ($fieldName == "phone") {
                        $DB->where("contact_us.mobile_number", 'like', '%' . $fieldValue . '%');
                    }

					if ($fieldName == "contact_number") {
                        $DB->where("contact_us.contact_number", 'like', '%' . $fieldValue . '%');
                    }

					if ($fieldName == "message") {
                        $DB->where("contact_us.message", 'like', '%' . $fieldValue . '%');
                    }

					if ($fieldName == "contact_status") {
                        $DB->where("contact_us.status", 'like', '%' . $fieldValue . '%');
                    }

				}
				$searchVariable	=	array_merge($searchVariable,array($fieldName => $fieldValue));
			}

		}
        $data = $DB->get();

		$sortBy = ($request->input('sortBy')) ? $request->input('sortBy') : 'created_at';
		$order  = ($request->input('order')) ? $request->input('order')   : 'DESC';

		$records_per_page	    =	($request->input('per_page')) ? $request->input('per_page') : Config::get("Reading.records_per_page");

		$results = $DB->orderBy($sortBy, $order)->paginate($records_per_page);
		$complete_string		=	$request->query();
		unset($complete_string["sortBy"]);
		unset($complete_string["order"]);
		$query_string			=	http_build_query($complete_string);
		$results->appends($inputGet)->render();

		if (Session::has('contact_queries_export_all_data')) {
            Session::forget('contact_queries_export_all_data');
        }
        Session::put('contact_queries_export_all_data', $data);

		$query_string			=	http_build_query($complete_string);
		$results->appends($inputGet)->render();

		return  View::make("admin.$this->model.index",compact('results','searchVariable','sortBy','order','query_string'));
	}
	public function reply(Request $request)
	{
		$user_id            =   Auth::guard('admin')->user()->id;
		$contactMessage     =  ContactUs::where('id',$request->contact_id)->pluck('message')->first();
		if($request->contact_id != ''){
			$ContactRequestReplies  = new ContactUsReply;
			$ContactRequestReplies->contact_id = $request->contact_id;
			$ContactRequestReplies->user_id  = $user_id;
			$ContactRequestReplies->message = $request->message;
			$ContactRequestReplies->save();
			if($ContactRequestReplies != ''){
				$send = array($request->message,$contactMessage);
        		$this->setEmailTemplate('contact_reply',$send,ContactUs::find($request->contact_id)->email);
			}
			return redirect()->back()->with('success','Reply send successfully.');
		}else{
			return redirect()->back()->with('error','Please try again.');
		}	
	}


	public function view($modelId = 0){
        $contactDetail				    =	ContactUs::where('id',$modelId)->first();
		if(empty($contactDetail)) {
			return Redirect::route($this->model.".index");
        }
        ContactUs::where('id',$modelId)->update(array('is_read'=>'1'));
		$contactReplies=ContactUsReply::where('contact_id',$modelId)->orderBy('id','desc')->get();

		$languages = Language::where('is_active', 1)->get();
        $language_code = Config('constants.DEFAULT_LANGUAGE.LANGUAGE_CODE');
        return  View("admin.$this->model.view", compact('contactDetail', 'contactReplies','languages', 'language_code'));
    }


	public function Approvestatus($modelId = 0, $status = 0){
		$result		=	ContactUs::where('id',$modelId)->first();
		if(empty($result)) {
			return Redirect::route($this->model.".index");
        }
		if($status == 'on_going'){
			$statusMessage	=	"Contact us status has been updated to on going successfully";
		}elseif($status == 'close'){
			$statusMessage	=	"Contact us status has been updated to close successfully";
		}elseif($status == 'archive'){
			$statusMessage	=	"Contact us status has been updated to archive successfully";
		}else{
			$statusMessage	=	"Contact us status is not correct";
			Session::flash('error', "Status is not correct.");
		}
			if($status == 'on_going' || $status == 'close' || $status == 'archive'){
				$update 		=	ContactUs::where('id',$modelId)->update(['status'=>$status]);
				Session::flash('success', $statusMessage);
			}

		return Redirect::back();
	}// end changeApproveStatus()



	public function destroy($encontactid)
    {
        $contactId = '';
        if (!empty($encontactid)) {
            $contactId = base64_decode($encontactid);
        }
        $contactDetails   =   ContactUs::find($contactId);
        if (empty($contactDetails)) {
            return Redirect()->route($this->model . '.index');
        }
        if ($contactId) {
           ContactUs::where('id', $contactId)->delete();
            Session()->flash('flash_notice', ucfirst(trans("messages.admin_Manage_Contracts__has_been_removed_successfully")));
        }
        return back();
    }


    public function export(Request $request)
    {
        

        $output = "";
        $output .= '
        <table border="1" id="example">
        <thead>
        <th style="width:230px">'.'Name'.'</th>
        <th style="width:300px">'.'Email'.'</th>
        <th style="width:130px">'.'Phone_Number'.'</th>
        <th style="width:100px">'.'Contact_Number'.'</th>
        <th style="width:100px">'.'Status'.'</th>
        </thead>
        <tbody>';

        $customers_export_all_data = Session::get('contact_queries_export_all_data');
        if (empty($customers_export_all_data)) {
            $table      = User::where('users.user_role_id', Config("constants.ROLE_ID.ADMIN_ID"))->select('users.*')->get();
        } else {
            $table      = $customers_export_all_data;
        }






        foreach ($table as $key => $result) {


			$statusstr = '';
			if($result->status == 'archive'){
				$statusstr .= "Archive";
			}else{
				if($result->status=='open'){
					$statusstr .= "Open";
				}else if($result->status=='on_going'){
					$statusstr .= "On_Going";
				}elseif($result->status=='close'){
					$statusstr .= "Closed";
				}
				// $statusstr .= '<br>';
				$statusstr .= '/';
				if($result->is_read==1){
					$statusstr .= "Read";
				}else{
					$statusstr .= "Unread";
				}
			}

            $output .= '<tr style="height:100px">' .
                '<td style="text-align:center; vertical-align: middle;">' . ($result->name ?? '') . '</td>' .
                '<td style="text-align:center; vertical-align: middle;">' . ($result->email ?? '' ) . '</td>' .
                '<td style="text-align:center; vertical-align: middle;">' . ($result->mobile_number ?? '') . '</td>' .
                '<td style="text-align:center; vertical-align: middle;">' . ($result->contact_number ?? '') . '</td>' .
                '<td style="text-align:center; vertical-align: middle;">' . $statusstr .'</tr>';
        }


        $output .= '</tbody></table>';

        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header("Content-Disposition: attachment; filename=contact_queries.xls");
        header("Cache-Control: max-age=0");
        echo $output;
    }




}