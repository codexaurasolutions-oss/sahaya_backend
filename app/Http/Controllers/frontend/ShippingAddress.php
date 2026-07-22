<?php

namespace App\Http\Controllers\frontend;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Auth;
use App\Models\ShippingAddressModel;
use DB;
use App;
use Validator;

class ShippingAddress extends Controller
{

    public function shoppingAddressList(Request $request)
    {
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }
        $shoppingAddressList = ShippingAddressModel::where('user_id', $user->id)->where('is_deleted',0)->get();
        return response()->json([
            'success' => true,
            'message' => trans('messages.shopping_Address_List'),
            'data' => $shoppingAddressList,
        ], 200);
    }
    public function StoreShippingAddress(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'city'             => 'required',
            'town'             => 'required',
            'country'          => 'required',
            'address'          => 'required',
            'phone_number'     => 'required',
            'is_type'          => 'required',
            'is_type_name'     => 'required_if:is_type,Other',
            'district'         => 'required',
            'name'             => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 500,
                'errors' => $validator->errors()->all()
            ]);
        }
        try {
            $check_is_deafult= ShippingAddressModel::where('user_id',Auth::guard('api')->user()->id)->where('is_deleted',0)->count();
            $storedata               = new ShippingAddressModel;
            $storedata->user_id      = Auth::guard('api')->user()->id;
            $storedata->city         = $request->city;
            $storedata->town         = $request->town;
            $storedata->district     = $request->district ?? '';
            $storedata->name         = $request->name ?? ''; 
            if($check_is_deafult == 0){
            $storedata->is_default   = 1;
            }
            $storedata->country      = $request->country;
            $storedata->is_type      = $request->is_type;
            $storedata->address      = $request->address;
            $storedata->phone_number = $request->phone_number;
            if($request->is_type == "Other"){
            $storedata->is_type_name = $request->is_type_name;  
            }  
            $storedata->save();
            return response()->json([
                'success' => true,
                'message' => trans('messages.data_saved_successfully'),
                'data' => $storedata
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => trans('messages.failed_to_save_data'),
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function mark_as_default_address(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'address_id' => 'required|exists:shipping_address,id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 400,
                'errors' => $validator->errors()->all(),
            ], 400);
        }

        ShippingAddressModel::where('user_id', Auth::guard('api')->user()->id)
        ->update(['is_default'=> 0]);
        $addressId = $request->address_id;
        $defaultAddress = ShippingAddressModel::find($addressId);
        if (!$defaultAddress) {
            return response()->json([
                'success' => false,
                'message' => 'Address not found',
            ], 404);
        }
        $currentDefaultAddress = ShippingAddressModel::where('user_id', Auth::guard('api')->user()->id)
            ->where('is_default', 1)
            ->first();

        if ($currentDefaultAddress) {
            $currentDefaultAddress->is_default = 0;
            $currentDefaultAddress->save();
        }
        $defaultAddress->is_default = 1;
        $defaultAddress->save();
        return response()->json([
            'success' => true,
            'message' => trans('messages.data_updated_successfully'),
        ], 200);
    }
    
    public function edit(Request $request,$id){
           $shopingId = base64_decode($id);
           $address   = ShippingAddressModel::find($shopingId);
           if($address){
           return response()->json([
               'success' => true,
               'data'    => $address,
           ], 200);
       }
    }
    public function update(Request $request) {
        $validator = Validator::make($request->all(), [
            'city'             => 'required',
            'town'             => 'required',
            'country'          => 'required',
            'address'          => 'required',
            'phone_number'     => 'required',
            'is_type'          => 'required',
            'is_type_name'     => 'required_if:is_type,Other',
            'name'             => 'required',
            'district'         => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 400,
                'errors' => $validator->errors()->all()
            ]);
        }
      //  $shoppingId            = base64_decode($request->shopping_id);
        $shoppingId            = $request->shipping_id;
        $address               = ShippingAddressModel::find($shoppingId);
        if(!$address){
            return response()->json([
                'status' => 500,
                'errors' => trans('messages.address_not_found'),
            ]);
        }
        $address->user_id      = Auth::guard('api')->user()->id;
        $address->city         = $request->city ?? $address->city;
        $address->town         = $request->town ?? $address->town;
        $address->district     = $request->district ?? $address->district ??  ""; 
        $address->name         = $request->name ?? ""; 
        $address->country      = $request->country ?? $address->country;
        $address->is_type      = $request->is_type ?? $address->is_type;
        $address->address      = $request->address ?? $address->address;
        $address->phone_number = $request->phone_number ?? $address->phone_number;
        if ($address->is_type === "Other") {
            $address->is_type_name = $request->is_type_name ?? $address->is_type_name;  
        } 
        if ($address->save()) {
            return response()->json([
                'success' => true,
                'message' => trans('messages.data_updated_successfully'),
                'data' => $address
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => trans('messages.data_not_updated_successfully'),
                'data' => null
            ], 500);
        }
    }
    public function delete(Request $request, $id) {
        $shoppingId = base64_decode($id);
        $address    = ShippingAddressModel::find($shoppingId);
        if ($address) {
            $address->is_deleted = 1;
            $save   = $address->save();
            if($save){
                $shippingAddress = ShippingAddressModel::where('user_id', Auth::guard('api')->user()->id)->where('is_default',0)->where('is_deleted',0)->first();
                if ($shippingAddress) {
                    $shippingAddress->is_default = 1;
                    $shippingAddress->save();
                } 
            }
            return response()->json([
                'success' => true,
                'message' => trans('messages.shipping_address_deleted_successfully'),
            ], 200);
        }
    }

}