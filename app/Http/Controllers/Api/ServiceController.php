<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Response;
use App\Models\Service;
use App\Models\Category;
use App\Models\SubService;
use App\Models\Wishlist;
use App\Models\PromoCode;
use App\Models\Booking;
use DateTime;
use App\Models\Cart;
use App\Traits\ImageUpload;

class ServiceController extends Controller
{
    use ImageUpload;
    public function index()
{
    $user = Auth::guard('api')->user();

    $services = Service::with(['user', 'category'])
        ->where('user_id', $user->id)
        ->get()
        ->map(function ($service) {
            // Decode JSON fields into arrays
            
            $service->available_schedule = $service->available_schedule ? $service->available_schedule: [];
            $service->peak_hours = $service->peak_hours ? $service->peak_hours : [];
            $service->addons = $service->addons ? $service->addons : [];
            return $service;
        });

    return response()->json([
        'status' => 'success',
        'message' => 'Services fetched successfully',
        'data' => $services
    ], 200);
}

 public function store(Request $request)
{
    $user = Auth::guard('api')->user();
    
    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:255',
        'description' => 'nullable|string',
        'price' => 'required|numeric|min:0',
        'gender' => 'required|in:male,female,unisex',
        'duration' => 'required|string',
        'peak_hours' => 'nullable|array',
        'available_schedule' => 'nullable|array',
        'addons' => 'nullable|array',
        'service_id' => 'nullable',
        'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
    ]);
    
    if ($validator->fails()) {
        return response()->json([
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }

    // Prepare service data
    $serviceData = [
        'user_id'     => $user->id,
        'name'        => $request->name,
        'description' => $request->description,
        'category_id' => $request->category_id,
        'price'       => $request->price,
        'gender'      => $request->gender,
        'duration'    => $request->duration,
        'available_schedule'  => $request->available_schedule ? $request->available_schedule : null,
        'peak_hours'  => $request->peak_hours ? $request->peak_hours : null,
        'addons'      => $request->addons ? $request->addons : null,
        'service_id'  => $request->service_id ?? null,
    ];

    // Handle image upload
    if ($request->hasFile('image')) {
        // $image = $request->file('image');
        $directory = 'uploads/services';

        // // Create directory if not exists
        // if (!file_exists(public_path($directory))) {
        //     mkdir(public_path($directory), 0755, true);
        // }

        // $extension = $image->getClientOriginalExtension();
        // $fileName = time() . '_' . uniqid() . '.' . $extension;

        // $image->move(public_path($directory), $fileName);

        // $path = $directory . '/' . $fileName;
        $path = $this->uploadCloudary($request,"image",$directory);

        $serviceData['image'] = $path;
    }

    // Save service
    $service = Service::create($serviceData);
    
    return response()->json([
        'status'  => 'success',
        'message' => 'Service created successfully',
        'data'    => $service->load(['user', 'category'])
    ], 201);
}

   public function show(Service $service)
{
    $user = Auth::guard('api')->user();
    
    if ($service->user_id !== $user->id) {
        return response()->json([
            'status' => 'error',
            'message' => 'Unauthorized access to service'
        ], 403);
    }

    // Decode JSON fields
    $service->peak_hours = $service->peak_hours ? $service->peak_hours : [];
    $service->addons = $service->addons ? $service->addons : [];

    return response()->json([
        'status' => 'success',
        'message' => 'Service retrieved successfully',
        'data' => $service->load(['user', 'category'])
    ], 200);
}

    
    public function update(Request $request, Service $service)
    {
        $user = Auth::guard('api')->user();
        
        if ($service->user_id !== $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access to service'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'sometimes|required|exists:categories,id',
            'price' => 'sometimes|required|numeric|min:0',
            'gender' => 'sometimes|required|in:male,female,unisex',
            'duration' => 'sometimes|required|string',
            'peak_hour' => 'nullable',
            'available_schedule' => 'nullable',
            'addons' => 'nullable'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Process peak hours
        $peakHours = [];
        foreach ($request->all() as $key => $value) {
            if (strpos($key, 'peak_hours[') === 0) {
                $timeRange = substr($key, strlen('peak_hours['), -1);
                $peakHours[$timeRange] = (float) $value;
            }
        }

        $available_schedule = [];
        foreach ($request->all() as $key => $value) {
            if (strpos($key, 'available_schedule[') === 0) {
                $timeRange = substr($key, strlen('available_schedule['), -1);
                $available_schedule[$timeRange] = (float) $value;
            }
        }
        
        // Process addons
        $addons = [];
        foreach ($request->all() as $key => $value) {
            if (strpos($key, 'addons[') === 0) {
                $addonName = substr($key, strlen('addons['), -1);
                $addons[] = [
                    'name' => $addonName,
                    'price' => (float) $value
                ];
            }
        }
        
        $updateData = $request->only(['name', 'description', 'category_id', 'price', 'gender', 'duration']);
        
        if (!empty($peakHours)) {
            $updateData['peak_hours'] = $peakHours;
        }
        if (!empty($available_schedule)) {
            $updateData['available_schedule'] = $available_schedule;
        }
        
        if (!empty($addons)) {
            $updateData['addons'] = $addons;
        }
        if (!empty($request->addons)) {
            $updateData['addons'] = $request->addons;
        }
        if (!empty($request->available_schedule)) {
            $updateData['available_schedule'] = $request->available_schedule;
        }
        if (!empty($request->peak_hours)) {
            $updateData['peak_hours'] = $request->peak_hours;
        }
        $service->update($updateData);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Service updated successfully',
            'data' => $service->load(['user', 'category'])
        ]);
    } 

  
    public function destroy(Service $service)
    {
        $service->delete();
        return response()->json(['message' => 'Service deleted successfully']);
    }
    
    public function getByCategory($categoryId): JsonResponse
    {
        $services = Service::with(['user', 'category'])
            ->where('category_id', $categoryId)
            ->get();
            
        return response()->json($services);
    }
    
    public function getByUser($userId): JsonResponse
    {
        $services = Service::with(['user', 'category'])
            ->where('user_id', $userId)
            ->get();
            
        return response()->json($services);
    }

   public function categoryShopList(Request $request, $id)
{
    $query = Service::with(['category', 'user', 'subServices.category'])
        ->where('user_id', $id);
    $list = $query->get();
    return response()->json([
        'status'  => 'success',
        'message' => 'Service list fetched successfully',
        'data'    => $list,
    ]);
}

public function shopDetails(Request $request, $id)
{
    $userId = Auth::guard('api')->user()->id;

    $service = Service::with('category')->findOrFail($id);

    $subServices = SubService::with('category')
        ->where('category_id', $service->category_id)
        ->get();

    // Check if this service is already in wishlist
    $isInWishlist = Wishlist::where('user_id', $userId)
        ->where('service_id', $id)
        ->exists();

    $data = [
        'service'      => $service,
        'category'     => $service->category,
        'sub_services' => $subServices,
        'is_wishlist'  => $isInWishlist
    ];

    return response()->json([
        'status'  => 'success',
        'message' => 'Shop details fetched successfully',
        'data'    => $data,
    ]);
}


public function wishlistList(Request $request)
{
    $userId = Auth::guard('api')->user()->id;

    // Get wishlist items with service details and related information
    $wishlistItems = Wishlist::with(['vendor','user'])
    ->where('user_id', $userId)
    ->orderBy('created_at', 'desc')
    ->get();

    // Format the response data
    $formattedWishlist = $wishlistItems->map(function($item) {
        return [
            'wishlist_id' => $item->id,
            'added_date' => $item->created_at->format('Y-m-d H:i:s'),
            'vendor' => $item->vendor,
            'user' => $item->user,
        ];
    });

    return response()->json([
        'status' => 'success',
        'message' => 'Wishlist fetched successfully',
        'data' => [
            'total_items' => $wishlistItems->count(),
            'wishlist' => $formattedWishlist
        ]
    ]);
}

public function saveWishlist(Request $request)
{
    $request->validate([
        'vendorId' => 'required',
    ]);

    $userId = Auth::guard('api')->user()->id;

    // $service = Service::with('user')->findOrFail($request->service_id);
    // $vendorId = $service->user_id;
    
    // Use firstOrCreate to prevent duplicates
    $wishlist = Wishlist::firstOrCreate(
        [
            'user_id'    => $userId,
            'vendorId'   => $request->vendorId,
        ]
    );


    // Check if the wishlist item was just created or already existed
    if ($wishlist->wasRecentlyCreated) {
        return response()->json([
            'status'  => 'success',
            'message' => 'Service added to wishlist successfully',
            'data'    => $wishlist
        ], 201);
    } else {
        return response()->json([
            'status'  => 'info',
            'message' => 'Service already exists in your wishlist',
            'data'    => $wishlist
        ], 200);
    }
}
   public function removeWishlist(Request $request, $id)
{
    $wishlist = Wishlist::find($id);

    if (!$wishlist) {
        return response()->json([
            'status' => false,
            'message' => 'Wishlist item not found'
        ], 404);
    }

    $wishlist->delete();

    return response()->json([
        'status' => true,
        'message' => 'Wishlist item removed successfully'
    ]);
}

  public function bookingWishlist(Request $request, $id)
{
    $wishlist = Booking::find($id);

    if (!$wishlist) {
        return response()->json([
            'status' => false,
            'message' => 'Booking item not found'
        ], 404);
    }

    $wishlist->delete();

    return response()->json([
        'status' => true,
        'message' => 'Booking item removed successfully'
    ]);
}


 public function cartWishlist(Request $request, $id)
{
    $wishlist = Cart::find($id);

    if (!$wishlist) {
        return response()->json([
            'status' => false,
            'message' => 'Cart item not found'
        ], 404);
    }

    $wishlist->delete();

    return response()->json([
        'status' => true,
        'message' => 'Cart item removed successfully'
    ]);
}
 

  public function subcategoryService(Request $request,$id){
     $services = Service::with(['user', 'category'])
        // ->where('user_id', $user->id)
        ->where('service_id',$id)
        ->get()
        ->map(function ($service) {            
            $service->available_schedule = $service->available_schedule ? $service->available_schedule: [];
            $service->peak_hours = $service->peak_hours ? $service->peak_hours : [];
            $service->addons = $service->addons ? $service->addons : [];
            return $service;
        });

    return response()->json([
        'status' => 'success',
        'message' => 'Services fetched successfully',
        'data' => $services
    ], 200);
  }

public function promoCodesList(Request $request,$id){
    $userId = Auth::guard('api')->user()->id;
    $list = PromoCode::where('user_id',$id)->get();
 return response()->json([
            'status'  => 'success',
            'message' => 'List Fatch successfully',
            'data'    => $list
        ], 200);
}

public function promoCodesListHighlighted(Request $request){
    $list = PromoCode::where('is_highlighted',1)->get();
 return response()->json([
            'status'  => 'success',
            'message' => 'List Fatch successfully',
            'data'    => $list
        ], 200);
}

/**
 * Get Available Slots for Service
 */
public function getAvailableSlots($serviceId)
{
    $service = Service::find($serviceId);    
    if (!$service) {
        return response()->json([
            'status' => 'error',
            'message' => 'Service not found'
        ], 404);
    }

    $availableSchedule = $service->available_schedule;

    if (!$availableSchedule || !is_array($availableSchedule)) {
        return response()->json([
            'status' => 'error',
            'message' => 'No available schedule found for this service'
        ], 400);
    }

    // Collect all booked slots
    $bookings = Booking::where('service_id', $serviceId)->get();

    $bookedSlots = [];
    foreach ($bookings as $booking) {
        $scheduleTime = $booking->schedule_time;

        if (is_array($scheduleTime)) {
            foreach ($scheduleTime as $time => $date) {
                $bookedSlots[] = $this->normalizeTimeSlot("$date $time");
            }
        } else {
            // If it's single slot (string like "10:30" => "2/1/2025")
            if (is_array(json_decode($scheduleTime, true))) {
                foreach (json_decode($scheduleTime, true) as $time => $date) {
                    $bookedSlots[] = $this->normalizeTimeSlot("$date $time");
                }
            } else {
                $bookedSlots[] = $this->normalizeTimeSlot($scheduleTime);
            }
        }
    }

    // Filter available slots
    $filteredSlots = [];
    foreach ($availableSchedule as $time => $date) {
        $normalized = $this->normalizeTimeSlot("$date $time");
        if (!in_array($normalized, $bookedSlots)) {
            $filteredSlots[$time] = $date;
        }
    }

    return response()->json([
        'status' => 'success',
        'message' => 'Available slots retrieved successfully',
        'data' => [
            'service_id' => $serviceId,
            'available_slots' => $filteredSlots
        ]
    ]);
}

/**
 * Normalize date-time format to compare easily
 */
private function normalizeTimeSlot($slot)
{
    $formats = ['n/j/Y H:i', 'd/m/Y h:i A', 'Y-m-d H:i:s'];

    foreach ($formats as $format) {
        $datetime = DateTime::createFromFormat($format, $slot);
        if ($datetime) {
            return $datetime->format('Y-m-d H:i');
        }
    }

    return $slot; // fallback
}

}