<?php

namespace App\Http\Controllers\adminpnlx;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use Auth;
use App\Models\Order;
use App\Models\User;
use App\Models\ReviewRating;
use App\Models\Product;
use DB;
use View;
use App;
use Session;
use Validator,Config;


class RatingReviewController extends Controller
{

	public $model		=	'Reviews';
	public $sectionName	=	'Reviews and Ratings';
	public $sectionNameSingular	=	'Review and Rating';
	
	public function __construct(Request $request) {
		parent::__construct();
		View::share('modelName',$this->model);
		View::share('sectionName',$this->sectionName);
		View::share('sectionNameSingular',$this->sectionNameSingular);
		$this->request = $request;
	}

    public function index(Request $request){  
		
		$DB					=	ReviewRating::query()->leftjoin('products as H','H.id','review_ratings.product_id')->leftjoin('users as U','U.id','review_ratings.user_id');
		$searchVariable		          =	array(); 
		$inputGet			          =	$request->all();
		
		if ($request->all()) {

			$searchData			      =	$request->all();
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
				$DB->whereBetween('artist_review_ratings.created_at', [$dateS." 00:00:00", $dateE." 23:59:59"]); 											
			}elseif(!empty($searchData['date_from'])){
				$dateS = $searchData['date_from'];
				$DB->where('artist_review_ratings.created_at','>=' ,[$dateS." 00:00:00"]); 
			}elseif(!empty($searchData['date_to'])){
				$dateE = $searchData['date_to'];
				$DB->where('artist_review_ratings.created_at','<=' ,[$dateE." 00:00:00"]); 						
			} 

			foreach($searchData as $fieldName => $fieldValue){
				if($fieldValue != ""){
					if($fieldName == "review_by"){
						$DB->where("U.name",'like','%'.$fieldValue.'%');
					}
					if($fieldName == "review"){
						$DB->where("review_ratings.review",'like','%'.$fieldValue.'%');
					}
					if($fieldName == "product_name"){
						$DB->where("H.name",'like','%'.$fieldValue.'%');
					}
				}
				$searchVariable	=	array_merge($searchVariable,array($fieldName => $fieldValue));
			}
		}
		$DB->select("review_ratings.*",'review_ratings.id as review_id','H.id as review_to_product_id','U.name as review_by','H.name as review_to_product_name','U.id as User_id');
		$sortBy                 = ($request->input('sortBy')) ? $request->input('sortBy') : 'review_ratings.created_at';
		$order                  = ($request->input('order')) ? $request->input('order')   : 'DESC';
		$all_result_ids         = $DB->get()->pluck('id')->toArray();
		$records_per_page	    =	($request->input('per_page')) ? $request->input('per_page') : Config::get("Reading.records_per_page");
		$results                = $DB->orderBy($sortBy, $order)->paginate($records_per_page);
		$complete_string		=	$request->query();
		unset($complete_string["sortBy"]);
		unset($complete_string["order"]);
		$query_string			=	http_build_query($complete_string);
		$results->appends($inputGet)->render();
		Session()->put('main_review_rating_lists_records',$results);
		return  View::make("admin.$this->model.index",compact('results','all_result_ids','searchVariable','sortBy','order','query_string'));
	}

	public function view($enuserid = null)
    {
        $user_id = '';
        if (!empty($enuserid)) {
            $review_id = base64_decode($enuserid);
        } else {
            return redirect()->route($this->model . ".index");
        }
        $reviewDetails   = ReviewRating::where('id', $review_id)->first();
        $productDetails  = Product::where('id',$reviewDetails->product_id)->first();
        if (!$reviewDetails) {
            return redirect()->back();
        }
        return  View("admin.$this->model.view", compact('reviewDetails','productDetails'));
    }

    	public function update(Request $request, $enuserid = null)
    {
        if ($request->isMethod('POST')) {
            $reviewId = !empty($enuserid) ? base64_decode($enuserid) : null;
            if (is_null($reviewId)) {
                return redirect()->route($this->model . '.index');
            }
            $validator = Validator::make($request->all(), [
                'rating' => 'required',
                'review' => 'required',
            ]);
            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }
            $rating = ReviewRating::find($reviewId);
            if (!$rating) {
                session()->flash('error', 'Review not found.');
                return redirect()->back()->withInput();
            }
            $rating->rating = $request->input('rating');
            $rating->review = $request->input('review');    
            if (!$rating->save()) {
                session()->flash('error', 'Something went wrong while saving the rating.');
                return redirect()->back()->withInput();
            }    
            session()->flash('success', 'Rating has been updated successfully.');
            return redirect()->route($this->model . '.index');
        }    
        return redirect()->route($this->model . '.index');
    }

	public function edit(Request $request,  $enuserid = null)
	{ 
		$reviewId = '';
		if (!empty($enuserid)) {
			$reviewId     = base64_decode($enuserid);
			$ratingDetails = ReviewRating::find($reviewId);
			$productDetails  = Product::where('id',$ratingDetails->product_id)->first();
			return  View("admin.$this->model.edit", compact('ratingDetails','productDetails'));
		} else {
			return redirect()->route($this->model . ".index");
		}
	}

	public function reviews_delete($enaclid)
	{
		$acl_id = '';
		if (!empty($enaclid)) {
			$acl_id = base64_decode($enaclid);
		}
		$aclDetails   =  ReviewRating::find($acl_id);
		// $ntf = new Notification();
		// $ntf->user_id = $aclDetails->seller_user_id;
		// $ntf->member_user_id = $aclDetails->seller_user_id;
		// $ntf->group_id = 0;
		// $ntf->product_id = $aclDetails->product_id;
		// $ntf->notification_type = 'delete_review';
		// $ntf->status = 'unviewed';
		// $ntf->save();
		$aclDetails->delete();

		Session()->flash('flash_notice', " Rating removed successfully");
		return back();
	}



	public function review_export()
	{
		$output  = "";
        $output .= '
        <table border="1" id="example">
        <thead>
        <th style="width:230px"> PRODUCT NAME </th>
        <th style="width:300px"> CUSTOMER NAME </th>
        <th style="width:100px"> CUSTOMER EMAIL </th>
        <th style="width:300px"> CUSTOMER REVIEW </th>
        <th style="width:300px"> CUSTOMER RATING </th>
        <th style="width:100px"> REVIEWED ON</th>
        </thead>
        <tbody>';
        $table = Session::get('main_review_rating_lists_records');
        if (count($table) > 0) {
            foreach ($table as $key => $excel_export) {
                $output .= '<tr style="height:100px">' .
                    '<td style="text-align:center; vertical-align: middle;">' . $excel_export->review_to_product_name . '</td>' .
                    '<td style="text-align:center; vertical-align: middle;">' . $excel_export->review_by . '</td>' .
                    '<td style="text-align:center; vertical-align: middle;">' . $excel_export->email . '</td>' .
                    '<td style="text-align:center; vertical-align: middle;">' . $excel_export->review . '</td>' .
                    '<td style="text-align:center; vertical-align: middle;">' . $excel_export->rating . '</td>' .
                    '<td style="text-align:center; vertical-align: middle;">' . $excel_export->created_at->format('d-m-Y H:i A') . '</td>' .
                    '</tr>';
            }
        }
        $output .= '</tbody></table>';
        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header("Content-Disposition: attachment; filename=rating-review.xls");
        header("Cache-Control: max-age=0");
        echo $output;
	}


    public function ReviewsRemove(Request $request)
    {

        $ids = $request->ReviewRating_ids;
        if (!$ids) {
            return response()->json(['success' => false, 'message' => 'No Review Rating IDs provided.'], 400);
        }
        $ReviewRatingIds = ReviewRating::whereIn('id', $ids)->get();
        if ($ReviewRatingIds->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No category provided.'], 400);
        } else {
            ReviewRating::whereIn('id', $ids)->delete();
            return response()->json(['success' => true, 'message' => 'Review Rating has been removed successfully.'], 200);
        }
    }


}