<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class PromoCodeController extends Controller
{
    public function index()
    {
                $userId = Auth::guard('api')->user()->id;
        try {
            $promoCodes = PromoCode::with('user')->where('user_id',$userId)->get();
            return response()->json([
                'success' => true,
                'data' => $promoCodes
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve promo codes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'promo_code' => 'required|unique:promo_codes',
        'type' => 'required|in:percentage,flat',
        'amount' => 'required',
        'start_on' => 'required|date',
        'expired_on' => 'required|date|after:start_on',
        'description' => 'nullable|string',
        'isActive' => 'boolean',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }

    // Additional validation for percentage type
    if ($request->type === 'percentage') {
        $amount = floatval($request->amount);
        if ($amount > 100 || $amount < 0) {
            return response()->json([
                'success' => false,
                'message' => 'Percentage amount must be between 0 and 100'
            ], 422);
        }
    } else {
        // For flat amount, ensure it's a positive number
        $amount = floatval($request->amount);
        if ($amount < 0) {
            return response()->json([
                'success' => false,
                'message' => 'Flat amount must be a positive number'
            ], 422);
        }
    }

    try {
        // Get authenticated user ID
        $userId = Auth::guard('api')->user()->id;
        
        // Create promo code with user_id
        $promoCode = PromoCode::create(array_merge(
            $request->all(),
            ['user_id' => $userId]
        ));
        
        return response()->json([
            'success' => true,
            'message' => 'Promo code created successfully',
            'data' => $promoCode
        ], 201);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to create promo code',
            'error' => $e->getMessage()
        ], 500);
    }
}

    public function show($id)
    {
        try {
            $promoCode = PromoCode::with('user')->find($id);
            
            if (!$promoCode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Promo code not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $promoCode
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve promo code',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $promoCode = PromoCode::find($id);
        
        if (!$promoCode) {
            return response()->json([
                'success' => false,
                'message' => 'Promo code not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'promo_code' => 'sometimes|required|unique:promo_codes,promo_code,' . $id,
            'type' => 'sometimes|required|in:percentage,flat',
            'amount' => 'sometimes|required',
            'start_on' => 'sometimes|required|date',
            'expired_on' => 'sometimes|required|date|after:start_on',
            'description' => 'nullable|string',
            'isActive' => 'boolean',
            'user_id' => 'nullable|exists:users,id',
            'extra_text' => 'nullable|string',
            'is_highlighted' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Additional validation for percentage type
        if ($request->has('type') && $request->type === 'percentage' && 
            $request->has('amount')) {
            $amount = floatval($request->amount);
            if ($amount > 100 || $amount < 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Percentage amount must be between 0 and 100'
                ], 422);
            }
        }

        // Additional validation for flat type
        if ($request->has('type') && $request->type === 'flat' && 
            $request->has('amount')) {
            $amount = floatval($request->amount);
            if ($amount < 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Flat amount must be a positive number'
                ], 422);
            }
        }

        try {
            $promoCode->update($request->all());
            return response()->json([
                'success' => true,
                'message' => 'Promo code updated successfully',
                'data' => $promoCode
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update promo code',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $promoCode = PromoCode::find($id);
            
            if (!$promoCode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Promo code not found'
                ], 404);
            }

            $promoCode->delete();
            return response()->json([
                'success' => true,
                'message' => 'Promo code deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete promo code',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function validatePromoCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'promo_code' => 'required|exists:promo_codes,promo_code',
            'total_amount' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $promoCode = PromoCode::where('promo_code', $request->promo_code)
                                ->active()
                                ->first();

            if (!$promoCode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Promo code is not active or has expired'
                ], 400);
            }

            $discount = $promoCode->calculateDiscount($request->total_amount);
            $finalAmount = $request->total_amount - $discount;

            return response()->json([
                'success' => true,
                'message' => 'Promo code is valid',
                'data' => [
                    'promo_code' => $promoCode,
                    'original_amount' => $request->total_amount,
                    'discount_amount' => $discount,
                    'final_amount' => $finalAmount
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate promo code',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}