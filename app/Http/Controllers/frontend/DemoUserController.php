<?php
namespace App\Http\Controllers\frontend;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB, Session;
use App\Models\EmailAction;
use App\Models\EmailTemplate;
use App\Models\UserInformation;
use App\Models\User;
use App\Models\ContactUs;
use App\Models\MobileIntroScreen;
use App\Models\Lookup;
use App\Models\Faq;
use App\Models\Category;
use App\Models\ProductWishlist;
use App\Models\CategoryColor;
use App\Models\Size;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductVariant;
use App\Models\ProductSize;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use DateTime,DateTimeZone,Config;
use Illuminate\Validation\Rules\Password;
use App\Models\UserDeviceToken;
use App\Classes\AgoraDynamicKey\RtcTokenBuilder;
use App\Rules\MaxVideoDuration;
use App\Traits\ImageUpload;

class DemoUserController extends Controller
{
    use ImageUpload;

	public function __construct(Request $request)
	{
		parent::__construct();
		$this->request              =   $request;
	}
	public function editProduct(Request $request,$id){
		dd($id);
		if ($request->isMethod("POST")) {
			$formData	=	$request->all();
			if (!empty($formData)) {
					$validator = Validator::make(
						$request->all(),
						array(
							'name' 			   					=> 'required',
							'description' 			   			=> 'required',
							'parent_category' 			   		=> 'required',
							'category_level_2' 			   		=> 'required',
							'video_video.*'					=> ['nullable','mimes:mp4,mov,avi,mkv,wmv,flv,webm,3gp,ogg', new MaxVideoDuration(15)]
						),
						array(
							"name.required"             		=> trans("messages.this_field_is_required"),
							"description.required"             	=> trans("messages.this_field_is_required"),
							"parent_category.required"          => trans("messages.this_field_is_required"),
							"category_level_2.required"         => trans("messages.this_field_is_required"),
							"variant.*.mimes"         	=> trans("messages.the_file_must_be_a_video"),
						),
					);
				if ($validator->fails()) {
					if ($request->wantsJson()) {
						$response			=	$this->change_error_msg_layout($validator->errors()->getMessages());
						$response["status"] = "error";
						$response["msg"] = trans("messages.input_field_is_required");
						return $response;
					}
				} else {
					$user = Auth::guard('api')->user();

					$product                                    = new Product;
					$product->user_id                   		= $user->id;
					$product->name    	                        = $request->name;
					$product->description    	                = $request->description;
					$product->parent_category                   = $request->parent_category;
					$product->is_active                   		= 1;
					$product->is_approved                   	= '0';
					$product->is_deleted                   		= 0;
					$product->category_level_2                  = $request->category_level_2;
					
					$product->save(); 
					$productId								= $product->id;
	
					if(!$productId){
						Session()->flash('error', trans("Something went wrong.")); 
						return Redirect()->back()->withInput();
					}
					$sizeIds_arr = [];
					if(isset($formData['variant'])){
						foreach($formData['variant'] as $colorId => $variant){
							foreach($variant as $key => $value){
								$productVariant                  = new ProductVariant;
								$productVariant->product_id      = $productId;
								$productVariant->color_id        = $colorId;
								$productVariant->size_id         = $value['size_id'];
								$productVariant->price           = $value['price'];
								$productVariant->stock_qty       = $value['stock_qty'];
								$productVariant->save();
								if(!in_array($value['size_id'], $sizeIds_arr)){
									$sizeIds_arr[] = $value['size_id'];
								}
							}
							$ProductColor              		= new ProductColor; 
							$ProductColor->product_id  		= $productId;
							$ProductColor->color_id    		= $colorId;

							if ($request->hasFile('variant_video.'.$colorId)) {
								$ProductColorvideo                	= $this->upload($request, 'variant_video.'.$colorId, '/uploads/product_video/');
								$ProductColor->video                = $ProductColorvideo;
								$ProductColor->video_thumbnail		= time() . '-video-thumbnail.jpg';
								generateThumbnail('storage/uploads/product_video/'.$ProductColorvideo, 'storage/uploads/product_video/thumbnail/'.$ProductColor->video_thumbnail );
							}
							$ProductColor->save();
						}
					}
					if(count($sizeIds_arr)){
						foreach($sizeIds_arr as $sizeId){
							 $ProductSize              		= new ProductSize; 
							 $ProductSize->product_id  		= $productId;
							 $ProductSize->size_id    		= $sizeId;
							 $ProductSize->save();
						}
					}
					$response               		=  array();
					$response['status']     		= 'success';
					$response['msg']		        = trans('messages.the_product_has_been_added_please_wait_for_approval');
					$response['data']     			= [];
			
					return $response;
				}
			}
		}
		$categories   = Category::where('categories.parent_id', null)->where(['categories.is_active' => 1, 'categories.is_deleted' => 0])
			->with(['categoryDescription' => function($query){
			 $query->where('language_id', $this->current_language_id()); 
		}])->get(); 

		$sizes   = Size::where(['is_active' => 1, 'is_deleted' => 0])
			->with(['SizesDescription' => function($query){
			 $query->where('language_id', $this->current_language_id()); 
			}])->get(); 	
		$response               		=  array();
		$response['status']     		= 'success';
		$response['data']     			= array(
			'categories'	=> $categories,
			'sizes'			=> $sizes
		);

		return $response;

	}
}

