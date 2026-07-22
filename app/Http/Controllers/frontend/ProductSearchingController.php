<?php

namespace App\Http\Controllers\frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Language;
use App\Models\Product;
use Carbon\Carbon;
use App\Models\Category;
use App\Models\ProductVariant;
use App\Models\ProductColor;
use App\Models\ProductSize;
use App\Models\BlockUser;
use App\Models\ReviewRating;
use App\Models\ProductAction;
use App\Models\User;
use App\Models\EmailAction;
use App\Models\EmailTemplate;
use DB;
use Auth;

class ProductSearchingController extends Controller
{
    
    public function index(Request $request){
        $name = $request->name;
        $ProductsSearch = '';
        $user 		 = Auth::guard('api')->user() ?? 0;
        $allBlockUsersId = BlockUser::where('user_id',$user)->pluck('block_user_id');        
        $products = Product::where('name', 'like', '%' . $name . '%')
                            ->where('is_active', 1)
                            ->where('is_approved', 1)
                            ->where('is_deleted', 0)
                            ->select('id', 'name')
                            ->whereNotIn('user_id',$allBlockUsersId)
                            ->addSelect(DB::raw("'product' as type"))
                            ->get();
    
        $categories = Category::where('name', 'like', '%' . $name . '%')
                              ->where('is_active', 1)
                              ->whereNull('parent_id')
                              ->where('is_deleted', 0)
                              ->select('id', 'name')
                              ->addSelect(DB::raw("'category' as type"))
                              ->get();    
    
        $subcategories = Category::where('name', 'like', '%' . $name . '%')
                                 ->where('is_active', 1)
                                 ->whereNotNull('parent_id')
                                 ->where('is_deleted', 0)
                                 ->select('id', 'name')
                                 ->addSelect(DB::raw("'sub_category' as type"))
                                 ->get();    
    
        $searchResults = $products->concat($categories)->concat($subcategories);
    
        $min_price = ProductVariant::join('products', 'products.id', '=', 'product_variants.product_id')
                                   ->where('products.is_active', 1)
                                   ->where('products.is_approved', 1)
                                   ->where('products.is_deleted', 0)
                                   ->min('product_variants.price');
        
        $max_price = ProductVariant::join('products', 'products.id', '=', 'product_variants.product_id')
                                   ->where('products.is_active', 1)
                                   ->where('products.is_approved', 1)
                                   ->where('products.is_deleted', 0)
                                   ->max('product_variants.price');
    
        $searchVariable = array();
        $inputGet = $request->all();
        
        $DB = Product::query();
        $DB->leftJoin('product_variants', 'products.id', '=', 'product_variants.product_id')
        ->select(
            'products.id',
            'products.name',
            'product_variants.product_id','products.parent_category','products.category_level_2',
            'products.created_at',
            DB::raw('MAX(product_variants.price) as price'),
            DB::raw('MAX(product_variants.color_id) as color_id'), 
            DB::raw('MAX(product_variants.size_id) as size_id') 
        )
        ->groupBy('products.id','products.name','product_variants.product_id','products.parent_category','products.category_level_2','products.created_at');
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
                    if ($fieldName == "min") {
                        $DB->where("product_variants.price", '>=', $fieldValue);
                    } 
                    if ($fieldName == "max") {
                        $DB->where("product_variants.price", '<=', $fieldValue);
                    }
                    if ($fieldName == "sort_by") {
                        $order = ($fieldValue == "high_to_low") ? 'DESC' : 'ASC';
                        $DB->orderBy('product_variants.price', $order);
                    } 
                    if (isset($searchData['type']) && $searchData['type'] == "category") {
                        if (isset($searchData['name'])) {
                            $categoryName = $searchData['name']; 
                            $categoryIds = Category::
                                select('id')
                                ->where('name', 'like', '%' . $categoryName . '%')
                                ->get()
                                ->pluck('id');
                            
                            if (count($categoryIds) > 0) {
                                $DB->whereIn('parent_category', $categoryIds);
                            }
                        }
                    }
    
                    if (isset($searchData['type']) && $searchData['type'] == "sub_category") {
                        if (isset($searchData['name'])) {
                            $categoryName = $searchData['name']; 
                            $categoryIds = Category::
                                select('id')
                                ->where('name', 'like', '%' . $categoryName . '%')
                                ->get()
                                ->pluck('id');
                            
                            if (count($categoryIds) > 0) {
                                $DB->whereIn('category_level_2', $categoryIds);
                            }
                        }
                    }           
    
                    if (isset($searchData['type']) && $searchData['type'] == "product") {
                        if (isset($searchData['name'])) {
                            $DB->where("products.name", 'like', '%' . $searchData['name'] . '%');
                        }
                    }
                    if ($fieldName == "name" && !isset($searchData['type'])) {
                        $DB->where("products.name", 'like', '%' . $fieldValue . '%');
                    }
                }
                $searchVariable = array_merge($searchVariable, array($fieldName => $fieldValue));
            }
        }
        $DB->where('is_active', 1)->where('is_approved', '1')->where('is_deleted', 0);
        if(Auth::guard('api')->user()){
        $user 		 = Auth::guard('api')->user();
        $allBlockUsersId = BlockUser::where('user_id',$user->id)->pluck('block_user_id');
        $DB->whereNotIn('user_id',$allBlockUsersId);
        $DB->whereNotIn('user_id',[$user->id]);
        }

        $sortBy = ($request->input('sortBy')) ? $request->input('sortBy') : 'products.created_at';
        $order = ($request->input('order')) ? $request->input('order') : 'DESC';
        $records_per_page = ($request->input('per_page')) ? $request->input('per_page') : Config("Reading.records_per_page");
        $results = $DB->orderBy($sortBy, $order)->paginate($records_per_page);
        $desiredRatings = $request->rating ? array_map('intval', explode(',', $request->rating)) : [];

        $results->setCollection(
            $results->getCollection()
                ->filter(function ($product) use ($desiredRatings) {
                    $avgRatingReview = ReviewRating::where('product_id', $product->id)->avg('rating');
                    $avgRatingReview = $avgRatingReview == 0 ? 0 : (int)floor($avgRatingReview);
        
                    return empty($desiredRatings) || in_array($avgRatingReview, $desiredRatings);
                })
                ->map(function ($product) {
                    $product->productCategory = Category::find($product->parent_category)?->select('id', 'image', 'name')->first();
        
                    $avgRatingReview = ReviewRating::where('product_id', $product->id)->avg('rating');
                    $avgRatingReview = $avgRatingReview == 0 ? 0 : (int)floor($avgRatingReview);
                    $product->avgRatingReview = $avgRatingReview;
        
                    $ratingReviewCount = ReviewRating::where('product_id', $product->id)->count();
                    $formattedRatingReviewCount = formatCount($ratingReviewCount);
                    $product->formattedRatingReviewCount = $formattedRatingReviewCount;
        
                    $ratingReviewArray = ReviewRating::where('product_id', $product->id)->get()->map(function($ratingReview) {
                        return array_merge(
                            $ratingReview->toArray(),
                            [
                                'userName' => $ratingReview->user->name,
                                'userImage' => $ratingReview->user->image,
                                'created_at' => Carbon::parse($ratingReview->created_at)->diffForHumans(),
                            ]
                        );
                    });
                    $product->ratingReviewArray = $ratingReviewArray;
        
                    $product->productSubCategory = Category::find($product->category_level_2)?->select('id', 'image', 'name')->first();
        
                    $product->productColorDetails = ProductColor::with([
                        'colorDetails' => function ($query) { 
                            $query->select('id', 'name', 'color_code');
                        },
                        'colorDetails.ColorsDescription' => function ($query) {  
                            $query->select('id', 'parent_id', 'language_id', 'name'); 
                        }
                    ])->where('product_id', $product->product_id)
                      ->where('color_id', $product->color_id)
                      ->first(['id', 'product_id', 'color_id', 'video', 'video_thumbnail']);
                    
                    if ($product->productColorDetails) {
                        $product->productColorDetails->video_thumbnail = "https://" . env("CDN_HOSTNAME") . "/" . $product->productColorDetails->video . "/thumbnail.jpg";
                        $product->productColorDetails->video = "https://" . env("CDN_HOSTNAME") . "/" . $product->productColorDetails->video . "/playlist.m3u8";
                    }
        
                    $product->productSizeDetails = ProductSize::with([
                        'sizeDetails' => function ($query) { 
                            $query->select('id', 'name');
                        },
                        'sizeDetails.SizesDescription' => function ($query) { 
                            $query->select('id', 'parent_id', 'language_id', 'name'); 
                        }
                    ])->where('product_id', $product->product_id)
                      ->where('size_id', $product->size_id)
                      ->first(['id', 'product_id', 'size_id']);
                    
                    return [
                        'id'                         => $product->id,
                        'name'                       => $product->name,
                        'product_id'                 => $product->product_id,
                        'productCategory'            => $product->productCategory,
                        'avgRatingReview'            => $product->avgRatingReview,
                        'formattedRatingReviewCount' => $product->formattedRatingReviewCount,
                        'ratingReviewArray'          => $product->ratingReviewArray,
                        'productSubCategory'         => $product->productSubCategory,
                        'productColorDetails'        => $product->productColorDetails,
                        'productSizeDetails'         => $product->productSizeDetails,
                    ];
                })
                ->values()
        );
        

        $complete_string = $request->query();
        unset($complete_string["sortBy"]);
        unset($complete_string["order"]);
        $query_string = http_build_query($complete_string);
        
        $results->appends($inputGet)->render();
        return response()->json([
            'results' => $results,
            'products_search' => $searchResults,
            'searchVariable' => $searchVariable,
            'sortBy' => $sortBy,
            'order' => $order,
            'min_price' => $min_price,
            'max_price' => $max_price,
            'query_string' => $query_string
        ]);
    }
    
    
    
    public function oldindex(Request $request){
        $name           = $request->name;
        $ProductsSearch = '';
        $products       = Product::where('name', 'like', '%' . $name . '%')->where('is_active', 1)->where('is_approved', 1)->where('is_deleted', 0)->select('id', 'name')->addSelect(DB::raw("'product' as type"))->get();
        $categories     = Category::where('name', 'like', '%' . $name . '%')->where('is_active', 1)->whereNull('parent_id')->where('is_deleted', 0)->select('id', 'name')->addSelect(DB::raw("'category' as type"))->get();    
        $subcategories  = Category::where('name', 'like', '%' . $name . '%')->where('is_active', 1)->whereNotNull('parent_id')->where('is_deleted', 0)->select('id', 'name')->addSelect(DB::raw("'sub_category' as type"))->get();    
        $searchResults  = $products->concat($categories)->concat($subcategories);
       
        $min_price      = ProductVariant::join('products', 'products.id', '=', 'product_variants.product_id')->where('products.is_active', 1)->where('products.is_approved', 1)->where('products.is_deleted', 0)->min('product_variants.price');
        $max_price      = ProductVariant::join('products', 'products.id', '=', 'product_variants.product_id')->where('products.is_active', 1)->where('products.is_approved', 1)->where('products.is_deleted', 0)->max('product_variants.price');
        $searchVariable = array();
        $inputGet       = $request->all();
        $DB             = Product::query();
        $DB->leftJoin('product_variants', 'products.id', '=', 'product_variants.product_id');
        $DB->select('products.*','product_variants.price','product_variants.color_id','product_variants.size_id','product_variants.product_id');
      //  $DB->groupBy('product_variants.product_id');

      //  dd($DB->count());
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
                    if ($fieldName == "min") {
                        $DB->where("product_variants.price", '>=', $fieldValue);
                    } 
                    if ($fieldName == "max") {
                        $DB->where("product_variants.price", '<=', $fieldValue);
                    }
                    if ($fieldName == "sort_by") {
                        $order = ($fieldValue == "high_to_low") ? 'DESC' : 'ASC';
                        $DB->orderBy('product_variants.price', $order);
                    } 
                    if (isset($searchData['type']) && $searchData['type'] == "category") {
                        if (isset($searchData['name'])) {
                            $categoryName = $searchData['name']; 
                            $categoryIds = Category::
                                select('id')
                                ->where('name', 'like', '%' . $categoryName . '%')
                                ->get()
                                ->pluck('id');
                            
                            if (count($categoryIds) > 0) {
                                $DB->whereIn('parent_category', $categoryIds);
                            }
                        }
                    }

                    if (isset($searchData['type']) && $searchData['type'] == "sub_category") {
                        if (isset($searchData['name'])) {
                            $categoryName = $searchData['name']; 
                            $categoryIds = Category::
                                select('id')
                                ->where('name', 'like', '%' . $categoryName . '%')
                                ->get()
                                ->pluck('id');
                            
                            if (count($categoryIds) > 0) {
                                $DB->whereIn('category_level_2', $categoryIds);
                            }
                        }
                    }           
         
                    if (isset($searchData['type']) && $searchData['type'] == "product") {
                        if (isset($searchData['name'])) {
                        //    $DB->where("products.name", $searchData['name']);
                            $DB->where("products.name", 'like', '%' . $searchData['name'] . '%');
                        }
                    }
                    if ($fieldName == "name" && !isset($searchData['type'])) {
                        $DB->where("products", 'like', '%' . $fieldValue . '%');
                    }
                 
                }
                $searchVariable = array_merge($searchVariable, array($fieldName => $fieldValue));
            }
        }
       // dd($DB->count());
        $DB->where('is_active', 1)->where('is_approved', 1)->where('is_deleted',0);

        $sortBy                   = ($request->input('sortBy')) ? $request->input('sortBy') : 'products.created_at';
        $order                    = ($request->input('order')) ? $request->input('order') : 'DESC';
        $records_per_page         = ($request->input('per_page')) ? $request->input('per_page') : Config("Reading.records_per_page");
        $results                  = $DB->orderBy($sortBy, $order)->paginate($records_per_page);
        $desiredRatings = $request->ratings;
        $results->getCollection()->transform(function ($product) use ($desiredRatings) {
             $avgRatingReview = ReviewRating::where('product_id', $product->id)->avg('rating');
            $avgRatingReview = $avgRatingReview == 0 ? '0' : number_format($avgRatingReview, 1);
            if (!in_array((int)floor($avgRatingReview), $desiredRatings)) {
                return null;
            }
            $product->avgRatingReview     = $avgRatingReview;
            $ratingReviewArray = ReviewRating::where('product_id', $product->id)->get()->map(function($ratingReview) {  return array_merge($ratingReview->toArray(),['userName' => $ratingReview->user->name,'userImage' => $ratingReview->user->image,'created_at' => Carbon::parse($ratingReview->created_at)->diffForHumans(),]);});
            $ratingReviewCount = ReviewRating::where('product_id', $product->id)->count();
            $formattedRatingReviewCount = formatCount($ratingReviewCount);
            if (strpos($formattedRatingReviewCount, 'k') !== false) {
                $numericCount = floatval(str_replace('k', '', $formattedRatingReviewCount));
                if ($numericCount <= 1) {
                    $formattedRatingReviewCount = $formattedRatingReviewCount . ' Review';
                } else {
                    $formattedRatingReviewCount = $formattedRatingReviewCount ;
                }
            } else {
                if ($formattedRatingReviewCount <= 1) {
                    $formattedRatingReviewCount = $formattedRatingReviewCount . ' Review';
                } else {
                    $formattedRatingReviewCount = $formattedRatingReviewCount ;
                }
            }
            $product->formattedRatingReviewCount   = $formattedRatingReviewCount;
            $product->ratingReviewArray   = $ratingReviewArray;
            $product->productCategory = Category::find($product->parent_category)?->select('id', 'image', 'name')->first();
            $product->productSubCategory = Category::find($product->category_level_2)?->select('id', 'image', 'name')->first();
            $product->productColorDetails = ProductColor::with(['colorDetails' => function ($query) { $query->select('id', 'name', 'color_code');},'colorDetails.ColorsDescription' => function ($query) {  $query->select('id', 'parent_id', 'language_id', 'name'); } ])->where('product_id', $product->product_id)->where('color_id', $product->color_id)->first(['id', 'product_id', 'color_id', 'video', 'video_thumbnail']);
            if ($product->productColorDetails) {
                $product->productColorDetails->video_thumbnail = "https://" . env("CDN_HOSTNAME") . "/" . $product->productColorDetails->video . "/thumbnail.jpg";
                $product->productColorDetails->video = "https://" . env("CDN_HOSTNAME") . "/" . $product->productColorDetails->video . "/playlist.m3u8";
            }
            $product->productSizeDetails = ProductSize::with(['sizeDetails' => function ($query) { $query->select('id', 'name');},'sizeDetails.SizesDescription' => function ($query) { $query->select('id', 'parent_id', 'language_id', 'name');}])->where('product_id', $product->product_id)->where('size_id', $product->size_id)->first(['id', 'product_id', 'size_id']);
            return $product;
        });
        
        $complete_string          = $request->query();
        unset($complete_string["sortBy"]);
        unset($complete_string["order"]);
        $query_string            = http_build_query($complete_string);
        $results->appends($inputGet)->render();
        return response()->json([
            'results' => $results,
            'products_search' => $searchResults,
            'searchVariable' => $searchVariable,
            'sortBy' => $sortBy,
            'order' => $order,
            'min_price' => $min_price,
            'max_price' => $max_price,
            'query_string' => $query_string
        ]);
    }

    public function changeProductsNames()
    {
        Product::chunk(100, function ($products) {
            foreach ($products as $product) {
                $product->name = $this->generateRandomName();
                $product->save();
            }
        });
        

        return response()->json(['message' => 'Product names updated successfully!']);
    }

    private function generateRandomName()
    {
        $sampleNames = [
            'UltraX Widget', 'PowerPro V2', 'SmartTech Device', 'QuickFlex Charger', 
            'VibeX Headphones', 'NanoTech Pen', 'ProMaster Camera', 'Speedy Charger', 
            'MegaBoost Powerbank', 'XtremePlus Tablet', 'HyperX Speaker', 'VisionMax Projector',
            'EcoFlow Lamp', 'FusionFlex Drone', 'SonicBeam Earbuds', 'CrystalClear Monitor', 
            'TurboCharge Adapter', 'Zenith Smartwatch', 'NanoGlo Flashlight', 'VortexBlender', 
            'StealthTech Laptop', 'PulseBeat Fitness Tracker', 'FlexiGrip Mouse', 'SmartMobi Phone', 
            'BlueEdge Printer', 'CorePro Gaming Console', 'XpertTouch Tablet', 'VividScan Scanner', 
            'NovaX Smart Glasses', 'FutureWave TV', 'ClearSound Microphone', 'ProVibe Headphones', 
            'GigaBoost Wireless Router', 'FlashVolt Power Bank', 'QuickFlow Water Bottle', 
            'SkyDrive Drone', 'PureSync Keyboard', 'TitanForce Gaming Chair', 'SilverPeak Camera', 
            'LightWave VR Headset', 'InfinityCharge Power Adapter', 'ZenFlex Yoga Mat', 
            'SpeedSync Smart Plug', 'UltraFlex Laptop Stand', 'ProPlus Smart Speaker', 'BluePixel Camera Lens',
            'SwiftCharge Car Adapter', 'EliteSound Bluetooth Speaker', 'MagicGrill Smart Oven', 
            'BoostLite Smart Bulb', 'QuantumMax Speaker', 'SnapTrack Fitness Band', 'VibeRider Scooter', 
            'ThunderGlide Electric Skateboard', 'EchoWave Sound System', 'QuantumDrive Flash Drive', 
            'TurboGlow Desk Lamp', 'EliteSync Wireless Earphones', 'InstaCook Air Fryer', 
            'XcelTrack Fitness Watch', 'DreamBeam LED Light Strip', 'SteadyFlow Electric Kettle', 
            'NextGen Power Adapter', 'WaveFlex Wireless Charger', 'UltraPower Mini Fan', 'iProClean Robot Vacuum', 
            'AirPure Purifier', 'ProTech Helmet', 'HyperJet Bluetooth Earphones', 'SwiftScreen Portable Monitor',
            'EcoFlow Smart Water Bottle', 'PixelTide Digital Camera', 'ZenithAir Smart Ventilator', 
            'SoundCraft Guitar Amplifier', 'PureBreeze Air Conditioner', 'VortexSwift Smart Scooter'
        ];
        

        return $sampleNames[array_rand($sampleNames)];
    }

    public function share_url_product(Request $request, $id) {
        $product_id          = base64_decode($id);
        $product_details     = Product::with(['userDetails','parentCategoryDetails','subCategoryDetails','prodcutColorDetails.colorDetails','prodcutColorDetails.colorDetails.ColorsDescription',])->where('id', $product_id)->where('is_deleted', 0)->first();  
        $productColorDetails = ProductColor::with(['colorDetails', 'colorDetails.ColorsDescription'])->where('product_id', $product_id)->get();
        $productSizeDetails  = ProductSize::with(['sizeDetails', 'sizeDetails.SizesDescription'])->where('product_id', $product_id)->get(); 
         $avgRatingReview = ReviewRating::where('product_id', $product_id)->avg('rating');
        $avgRatingReview = $avgRatingReview == 0 ? '0' : number_format($avgRatingReview, 1);
        $ratingReviewArray = ReviewRating::where('product_id', $product_id)->get()->map(function($ratingReview) {  return array_merge($ratingReview->toArray(),['userName' => $ratingReview->user->name,'userImage' => $ratingReview->user->image,'created_at' => Carbon::parse($ratingReview->created_at)->diffForHumans(),]);});
        $ratingReviewCount = ReviewRating::where('product_id', $product_id)->count();
        $formattedRatingReviewCount = formatCount($ratingReviewCount);
        if (strpos($formattedRatingReviewCount, 'k') !== false) {
            $numericCount = floatval(str_replace('k', '', $formattedRatingReviewCount));
            if ($numericCount <= 1) {
                $formattedRatingReviewCount = $formattedRatingReviewCount . ' Review';
            } else {
                $formattedRatingReviewCount = $formattedRatingReviewCount ;
            }
        } else {
            if ($formattedRatingReviewCount <= 1) {
                $formattedRatingReviewCount = $formattedRatingReviewCount . ' Review';
            } else {
                $formattedRatingReviewCount = $formattedRatingReviewCount ;
            }
        }
       if (!$product_details) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found.'
            ], 404);
        }
        $product_details->avgRatingReview                    = $avgRatingReview;
        $product_details->RatingReviewArray                  = $ratingReviewArray;
        $product_details->formattedRatingReviewCount         = $formattedRatingReviewCount;
        $product_details->created_date                       = date(config("Reading.date_format"), strtotime($product_details->created_at));
        $productImage                                        = Category::find($product_details->category_level_2)?->value('image') ?? Category::find($product_details->parent_category)?->value('image');
		$product_details->url_image                          = $productImage;
        $product_details->url                                = route('share-url-product', ['id' => base64_encode($product_details->id)]);
        $product_details->url_text                           = $product_details->name;
        $product_details->url_with_text                      = $product_details->name . ' ' . $product_details->url;
        $product_details->is_favorite                        = ProductAction::where(['product_id' => $product_details->id,'action_type' => 'favorite','user_id' => auth()->id()])->count();
        $product_details->total_likes                        = ProductAction::where(['product_id' => $product_details->id,'action_type' => 'like'])->count();
        $product_details->is_liked                           = ProductAction::where(['product_id' => $product_details->id,'action_type' => 'like','user_id' => auth()->id()])->count();
        $product_details->total_save                         = ProductAction::where(['product_id' => $product_details->id,'action_type' => 'save'])->count();
        $product_details->is_saved                           = ProductAction::where(['product_id' => $product_details->id,'action_type' => 'save','user_id' => auth()->id()])->count();
        $product_details->total_comments                     = 0; 
        $product_details->total_asks                         = 0;   
        return View("common.share_product", compact('product_details','productSizeDetails','productColorDetails')); 
    }

}