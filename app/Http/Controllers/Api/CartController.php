<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    /**
     * Add items to cart
     */
 public function addToCart(Request $request)
{
    try {
        // Validate request
        $validator = Validator::make($request->all(), [
            'service_id' => 'required|exists:services,id',
            'price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not authenticated',
            ], 401);
        }

        $serviceId = $request->input('service_id');
        $service = Service::find($serviceId);

        if (!$service) {
            return response()->json([
                'status' => 'error',
                'message' => 'Service not found',
            ], 404);
        }

        $vendorId = $service->user_id;

        // Get all cart items for this user
        $existingCartItems = Cart::where('user_id', $user->id)
            ->with('service')
            ->get();

        if ($existingCartItems->isNotEmpty()) {
            // Try to get first valid service with user_id
            $existingVendorId = null;
            foreach ($existingCartItems as $item) {
                if ($item->service && $item->service->user_id) {
                    $existingVendorId = $item->service->user_id;
                    break;
                }
            }

            // If found and vendor is different, clear cart
            if ($existingVendorId && $existingVendorId != $vendorId) {
                Cart::where('user_id', $user->id)->delete();
            }
        }

        // Check if the item is already in cart
        $existingCartItem = Cart::where('user_id', $user->id)
            ->where('service_id', $serviceId)
            ->first();

        if ($existingCartItem) {
            return response()->json([
                'status' => 'success',
                'message' => 'Item already in cart',
                'data' => [
                    'added_items' => 0,
                    'already_in_cart' => 1
                ]
            ], 200);
        }

        // Add new item to cart
        $cartItem = Cart::create([
            'user_id' => $user->id,
            'service_id' => $serviceId,
            'price' => $request->input('price'),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Item added to cart successfully',
            'data' => [
                'added_items' => 1,
                'already_in_cart' => 0
            ]
        ], 201);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Something went wrong',
            'error' => $e->getMessage()
        ], 500);
    }
}



    /**
     * Get user's cart items
     */
 public function getCart()
{
    $user = Auth::guard('api')->user();

    $cartItems = Cart::where('user_id', $user->id)
        ->with(['service' => function($query) {
            $query->with([
                'subServices',
                'category',
                'user' => function($userQuery) {
                    $userQuery->select(
                        'id',
                        'name',
                        'business_name',
                        'business_description',
                        'years_of_experience',
                        'service_category'
                    );
                }
            ]);
        }])
        ->get();

    // Calculate total price safely
    $totalPrice = $cartItems->sum(function($item) {
        if (!$item->service) {
            return 0; // Skip items with missing service
        }
        return $item->service->discount_price ?? $item->service->price;
    });

    $formattedItems = $cartItems->map(function($item) {
        if (!$item->service) {
            return [
                'cart_id' => $item->id,
                'service' => null, // service deleted
                'item_price' => 0,
                'added_date' => $item->created_at->format('Y-m-d H:i:s')
            ];
        }

        $itemPrice = $item->service->discount_price ?? $item->service->price;

        return [
            'cart_id' => $item->id,
            'service' => [
                'id' => $item->service->id,
                'name' => $item->service->name,
                'description' => $item->service->description,
                'image' => $item->service->image,
                'price' => $item->service->price,
                'discount_price' => $item->service->discount_price,
                'category' => $item->service->category ? [
                    'id' => $item->service->category->id,
                    'name' => $item->service->category->name
                ] : null,
                'subServices' => $item->service->subServices,
                'vendor' => $item->service->user ? [
                    'id' => $item->service->user->id,
                    'name' => $item->service->user->name,
                    'business_name' => $item->service->user->business_name,
                    'business_description' => $item->service->user->business_description,
                    'years_of_experience' => $item->service->user->years_of_experience,
                    'service_category' => $item->service->user->service_category
                ] : null
            ],
            'item_price' => $itemPrice,
            'added_date' => $item->created_at->format('Y-m-d H:i:s')
        ];
    });

    // Vendor only if service exists
    $vendor = $cartItems->isNotEmpty() && $cartItems[0]->service
        ? $formattedItems[0]['service']['vendor']
        : null;

    return response()->json([
        'status' => 'success',
        'message' => 'Cart retrieved successfully',
        'data' => [
            'vendor' => $vendor,
            'total_items' => $cartItems->count(),
            'total_price' => $totalPrice,
            'items' => $formattedItems
        ]
    ]);
}

    /**
     * Remove item from cart
     */
    public function removeFromCart($id)
    {
        $user = Auth::guard('api')->user();

        $cartItem = Cart::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$cartItem) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cart item not found'
            ], 404);
        }

        $cartItem->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Item removed from cart successfully'
        ]);
    }

    /**
     * Clear entire cart
     */
    public function clearCart()
    {
        $user = Auth::guard('api')->user();

        Cart::where('user_id', $user->id)->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Cart cleared successfully'
        ]);
    }
}