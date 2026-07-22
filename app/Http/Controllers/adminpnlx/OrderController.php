<?php

namespace App\Http\Controllers\adminpnlx;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\OrderProducts;
use App\Models\ProductVariant;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\User;
use App\Models\ShippingAddressModel;
use App\Models\UserDeviceToken;
use App\Models\RefundOrder;
use App\Models\Notification;
use App\Models\Lookup;
use App\Models\RefundOrderImage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Auth;

class OrderController extends Controller
{
    public $model = 'orders';
    public function __construct(Request $request)
    {
        parent::__construct();
        View()->share('model', $this->model);
        $this->request = $request;
    }

    public function index(Request $request)
    {
        $DB = Order::query();
        $searchVariable = [];
        $inputGet = $request->all();

        if ($request->all()) {
            $searchData = $request->all();
            unset($searchData['display']);
            unset($searchData['_token']);
            unset($searchData['order']);
            unset($searchData['sortBy']);
            unset($searchData['page']);

            foreach ($searchData as $fieldName => $fieldValue) {
                if ($fieldValue != '') {
                    if ($fieldName === 'customer_email') {
                        $DB->where('orders.customer_email', 'LIKE', '%' . $fieldValue . '%');
                    }
                    if ($fieldName === 'order_number') {
                        $DB->where('orders.order_number', 'LIKE', '%' . $fieldValue . '%');
                    }

                    if ($fieldName === 'customer_phone') {
                        $DB->where('orders.customer_phone', 'LIKE', '%' . $fieldValue . '%');
                    }

                    if ($fieldName === 'method') {
                        $DB->where('orders.method', 'LIKE', '%' . $fieldValue . '%');
                    }

                    if ($fieldName == 'start_date' && $fieldValue != '') {
                        $DB->whereDate('orders.created_at', '>=', $fieldValue);
                    }

                    if ($fieldName == 'end_date' && $fieldValue != '') {
                        $DB->whereDate('orders.created_at', '<=', $fieldValue);
                    }

                    $searchVariable[$fieldName] = $fieldValue;
                }
            }
        }

        $sortBy = $request->input('sortBy', 'created_at');
        $order = $request->input('order', 'DESC');
        $records_per_page = $request->input('per_page', Config('Reading.records_per_page'));
        $results = $DB->orderBy($sortBy, $order)->paginate($records_per_page);
        $complete_string = $request->query();
        unset($complete_string['sortBy'], $complete_string['order']);
        $query_string = http_build_query($complete_string);
        $results->appends($inputGet);
        return view("admin.$this->model.index", compact('results', 'searchVariable', 'sortBy', 'order', 'query_string'));
    }

    public function viewlogs($enordid)
    {
        $DB = OrderLog::query();
        $order_id = !empty($enordid) ? base64_decode($enordid) : null;
        $orderRecord = DB::table('orders')->select('order_number')->where('id', $order_id)->first();
        if (!$orderRecord) {
            return redirect()
                ->route($this->model . '.index')
                ->with('error', 'Order not found');
        }
        $order_number = $orderRecord->order_number;

        $orderlogdetails = OrderLog::where('order_number', $order_number)->orderBy('id','desc')->get();
      
        return view("admin.$this->model.orderlogs", compact('orderlogdetails', 'order_number'));
    }

    public function view($enordid)
    {
        $order_id = !empty($enordid) ? base64_decode($enordid) : null;
        if (is_null($order_id)) {
            return redirect()->route($this->model . '.index');
        }
        $orderdetails = Order::find($order_id);
        if (!$orderdetails) {
            return redirect()
                ->route($this->model . '.index')
                ->with('error', 'Order not found');
        }

        $shipping_id = $orderdetails->shipping_id;
        $order_number = $orderdetails->order_number;

        $shipping_details = ShippingAddressModel::with('user')->where('id', $shipping_id)->first();

        if ($shipping_details) {
            $shipping_details->user_email = $shipping_details->user->email;
        }

        $orderproduct_details = OrderProducts::where('order_number', $order_number)->get();
        $product_varients = [];
        foreach ($orderproduct_details as $orderproduct) {
            $product_varient_id = $orderproduct->product_varient_id;
            $product_variant = ProductVariant::find($product_varient_id);

            $sellerId = $orderproduct->seller_id; 

            if ($product_variant) {
                $product_varients[] = $product_variant;
            }

            $seller = User::where('id', $orderproduct->seller_id)->select('name')->first();
        
            if ($seller) {
                $orderproduct->seller_name = $seller->name;
            }
        }

        $seller = User::find($sellerId);
      

        $new_order = Order::where('order_number', $order_number)->first();
        if ($new_order && $new_order->is_read == 0) {
            $new_order->is_read = 1;
            $new_order->save();
        }

        $productdetails = Product::where('id', $orderdetails->product_id)->first();
        $productImage =null;
        if ($productdetails) {
            $productImage = $productdetails->category_level_2
                ? Category::find($productdetails->category_level_2)?->image
                : ($productdetails->parent_category
                    ? Category::find($productdetails->parent_category)?->image
                    : '');
        }
        $varientcolorDetails  =  ProductColor::with(['colorDetails','colorDetails.ColorsDescription'])->where('product_id',$product_variant->product_id)->where('color_id',$product_variant->color_id)->first();

        return view("admin.$this->model.view", compact('varientcolorDetails','orderdetails','seller', 'shipping_details', 'orderproduct_details', 'product_varients', 'productImage'));
    }

    public function orderreturn($enordid){
        $order_id = !empty($enordid) ? base64_decode($enordid) : null;
        if (is_null($order_id)) {
            return redirect()->route($this->model . '.index');
        }
        $orderdetails = OrderProducts::find($order_id);
        $refundOrder = RefundOrder::where('order_number', $orderdetails->order_number)->first();
        $refundreason =Lookup::where('id', $refundOrder->reason)->first();
        $refundOrderImages = RefundOrderImage::where('refund_order_id', $refundOrder->id)->get();

        if (!$orderdetails) {
            return redirect()
                ->route($this->model . '.index')
                ->with('error', 'Order not found');
        }
        return view("admin.$this->model.return_reason", compact('orderdetails','refundreason','refundOrderImages','refundOrder'));

    }

    public function accept($enordid)
    {
        $order_id     = !empty($enordid) ? base64_decode($enordid) : null;
        $order        = OrderProducts::findOrFail($order_id);
        $orderdetails = Order::where('order_number', $order->order_number)->first();
    
        if ($order->order_status === 'pending') {
            $order->order_status = 'confirmed';
            $order->order_confirmed_at = now();
            $order->save();
            $updated_order_statuses = OrderProducts::where('order_number', $order->order_number)->pluck('order_status');
            if ($updated_order_statuses->every(fn($status) => $status === 'confirmed')) {
                $orderdetails->status = 'confirmed';
                $orderdetails->order_confirmed_at = now();
                $orderdetails->save();
            }
            $userDetail  = User::find($order->user_id);
        $userDetailToken =UserDeviceToken::where('user_id',$userDetail->id)->orderBy('id', 'desc')->first();
        if ($userDetail->language == 2) {
            $order_des = 'Sipariş başarıyla verildi';
            $msg_title = 'Sipariş #'.$orderdetails->order_number. 'Kabul edildi';
            $notification_title = 'Sipariş';
            $notification_desc = 'Sipariş başarıyla verildi';
        } else {
            $order_des = 'Order has been accepted';
            $msg_title = 'Order #'.$orderdetails->order_number. 'has been Accpected';
            $notification_desc = 'Order has been accepted';
        }
        $data=[
        'order_number'       => $order->order_number, 
        'product_id'         => $order->product_id,
        'product_varient_id' => $order->product_varient_id,
        ];
        if($userDetail->push_notification == 1){
             if (!empty($userDetailToken->device_token)) {
                 $this->send_push_notification(
                     $userDetailToken->device_token,
                     $userDetailToken->device_type,
                     $order_des,
                     $msg_title,
                     'confirmed',
                     $data
                 );
                 $notification = new Notification;
                 $notification->user_id   = $userDetail->id;
                 $notification->action_user_id = Auth::guard('admin')->user()->id;
                 $notification->order_number = $order->order_number ?? "";
                 $notification->product_id  = $order->product_id ?? "";
                 $notification->product_varient_id = $order->product_varient_id ?? "";
                 $notification->description_en = 'Order has been Accpected';
                 $notification->title_en  = 'Order #'.$orderdetails->order_number. 'has been Accpected';
                 $notification->title_tur ='Sipariş #'.$orderdetails->order_number. 'Kabul edildi';
                 $notification->description_tur =  'Sipariş başarıyla verildi';
                 $notification->type           = "confirmed";
                 $notification->send_by           = 0;
                 $notification->save();
             }
        }
            $this->logOrderAction($orderdetails,$order->product_id, $order->product_varient_id, 'Processing', trans('messages.order_has_been_accepted'));
    
            return response()->json(['success' => true, 'message' => 'Order has been accepted and logged.']);
        }
    
        return response()->json(['success' => false, 'message' => 'Order cannot be accepted.']);
    }
    
    public function reject(Request $request, $enordid)
    {
        $request->validate([
            'reject_reason' => 'required',
        ]);
    
        $order_id = !empty($enordid) ? base64_decode($enordid) : null;
        $order = OrderProducts::findOrFail($order_id);
        $orderdetails = Order::where('order_number', $order->order_number)->first();
    
        if ($order->order_status === 'pending') {
            $reasons = $request->input('reject_reason', []);
            $order->reject_reason = $reasons;
            $order->reject_status = 1;
            $order->order_status = 'declined';
            $order->save();
    
            $updated_order_statuses = OrderProducts::where('order_number', $order->order_number)->pluck('order_status');
            if ($updated_order_statuses->every(fn($status) => $status === 'declined')) {
                $orderdetails->status = 'declined';
                $orderdetails->reject_reason = $reasons;
                $orderdetails->save();
            }
            $userDetail  = User::find($orderdetails->user_id);
            $userDetailToken =UserDeviceToken::where('user_id',$userDetail->id)->orderBy('id', 'desc')->first();
            if($userDetail->language == 2){
                $order_des ='Sipariş Reddedildi'.$order->order_number;
                $msg_title = 'Sipariş reddedildi #'.$order->order_number;
            }else{
                $order_des ='Order has been  Rejected'.$order->order_number;
                $msg_title = 'Order rejected #'.$order->order_number;
            }
            $data=[
            'order_number' => $order->order_number, 
            'product_id' => $order->product_id,
            'product_varient_id'=> $order->product_varient_id,
            ];
            if($userDetail->push_notification == 1){
              if (!empty($userDetailToken->device_token)) {
                  $this->send_push_notification(
                      $userDetailToken->device_token,
                      $userDetailToken->device_type,
                      $order_des,
                      $msg_title,
                      'rejected',
                      $data
                  );
                  $notification = new Notification;
                  $notification->user_id   = $userDetail->id;
                  $notification->action_user_id = Auth::guard('admin')->user()->id;
                  $notification->order_number = $order->order_number ?? "";
                  $notification->product_id  = $order->product_id ?? "";
                  $notification->product_varient_id = $order->product_varient_id ?? "";
                  $notification->description_en = 'Order has been  Rejected'. $order->order_number;
                  $notification->title_en  ='Order rejected #' . $order->order_number;
                  $notification->title_tur ='Sipariş reddedildi #' . $order->order_number;
                  $notification->description_tur =  'Sipariş reddedildi #' . $order->order_number;
                  $notification->type           = "rejected";
                  $notification->send_by           = 0;
                  $notification->save();
              }
            }
            $this->logOrderAction($orderdetails,$order->product_id, $order->product_varient_id, $order->order_status, $reasons);
    
            return response()->json(['success' => true, 'message' => 'Order has been rejected successfully.']);
        }
    
        return response()->json(['success' => false, 'message' => 'Order cannot be rejected.'], 400);
    }
    
    public function packed($enordid)
    {
        $order_id = !empty($enordid) ? base64_decode($enordid) : null;
        $order = OrderProducts::findOrFail($order_id);
        $orderdetails = Order::where('order_number', $order->order_number)->first();
    
        if ($order->order_status === 'confirmed') {
            $order->order_status = 'packed';
            $order->on_the_way = now();
            $order->save();
    
            $updated_order_statuses = OrderProducts::where('order_number', $order->order_number)->pluck('order_status');
            if ($updated_order_statuses->every(fn($status) => $status === 'packed')) {
                $orderdetails->status = 'packed';
                $orderdetails->on_the_way = now();
                $orderdetails->save();
            }
            $userDetail  = User::find($orderdetails->user_id);
            $userDetailToken =UserDeviceToken::where('user_id',$userDetail->id)->orderBy('id', 'desc')->first();
            
            if($userDetail->language == 2){
                $order_des ='Siparişiniz #'.$order->order_number .'paketlendi ve gönderime hazır!';
                $msg_title = 'Siparişiniz #'.$order->order_number . 'Gönderime Hazır';
            }else{
                $order_des ='We’ve packed your order #'.$order->order_number .'and it’s now ready to be shipped!';
                $msg_title = 'Your Order #' .$order->order_number .' has been packed';
            }
            $data=[
                'order_number' => $order->order_number, 
                'product_id' => $order->product_id,
                'product_varient_id'=> $order->product_varient_id,
            ];
            if($userDetail->push_notification == 1){
               if (!empty($userDetailToken->device_token)) {
                   $this->send_push_notification(
                       $userDetailToken->device_token,
                       $userDetailToken->device_type,
                       $order_des,
                       $msg_title,
                       'packed',
                       $data
                   );

                   $notification = new Notification;
                   $notification->user_id   = $userDetail->id;
                   $notification->action_user_id = Auth::guard('admin')->user()->id;
                   $notification->order_number = $order->order_number ?? "";
                   $notification->product_id  = $order->product_id ?? "";
                   $notification->product_varient_id = $order->product_varient_id ?? "";
                   $notification->description_en = 'We’ve packed your order #'.$order->order_number .'and it’s now ready to be shipped!';
                   $notification->title_en  = 'Your Order #'.$order->order_number . 'has been packed';
                   $notification->title_tur ='Siparişiniz #'.$order->order_number. 'Gönderime Hazır';
                   $notification->description_tur ='Siparişiniz #'.$order->order_number .'paketlendi ve gönderime hazır!';
                   $notification->type           = "packed";
                   $notification->send_by           = 0;
                   $notification->save();
               }
            }
            $this->logOrderAction($orderdetails,$order->product_id, $order->product_varient_id, $order->order_status, trans('messages.order_is_now_packed'));
    
            return response()->json(['success' => true, 'message' => 'Order is now packed.']);
        }
    
        return response()->json(['success' => false, 'message' => 'Order cannot be packed.']);
    }
    
    public function shipped(Request $request, $enordid)
    {
        $shippingDate = $request->input('shipped');
        $formattedShippingDate = Carbon::createFromFormat('Y-m-d\TH:i', $shippingDate)->format('Y-m-d H:i:s');
        $order_id = !empty($enordid) ? base64_decode($enordid) : null;
        $order = OrderProducts::findOrFail($order_id);
        $orderdetails = Order::where('order_number', $order->order_number)->first();
    
        if ($order->order_status === 'packed') {
            $order->order_status = 'shipped';
            $order->estimated_date = $formattedShippingDate;
            $order->save();
    
            $updated_order_statuses = OrderProducts::where('order_number', $order->order_number)->pluck('order_status');
            if ($updated_order_statuses->every(fn($status) => $status === 'shipped')) {
                $orderdetails->status = 'shipped';
                $orderdetails->estimated_date = $formattedShippingDate;
                $orderdetails->save();
            }
            $userDetail  = User::find($orderdetails->user_id);
            $userDetailToken =UserDeviceToken::where('user_id',$userDetail->id)->orderBy('id', 'desc')->first();
            if($userDetail->language == 2){
                $order_des ='Ürünleriniz dikkatlice gönderildi ve en kısa sürede elinize ulaşacak.';
                $msg_title = 'Siparişiniz Gönderildi! #'.$order->order_number;
            }else{
                $order_des ='Your items have been carefully shipped and will arrive shortly.';
                $msg_title = 'Your Order Has Been Shipped! #'.$order->order_number;
            }
            $data=[
                'order_number' => $order->order_number, 
                'product_id' => $order->product_id,
                'product_varient_id'=> $order->product_varient_id,
            ];
            if($userDetail->push_notification == 1){
               if (!empty($userDetailToken->device_token)) {
                   $this->send_push_notification(
                       $userDetailToken->device_token,
                       $userDetailToken->device_type,
                       $order_des,
                       $msg_title,
                       'shipped',
                       $data
                   );
               $notification = new Notification;
               $notification->user_id   = $userDetail->id;
               $notification->action_user_id = Auth::guard('admin')->user()->id;
               $notification->order_number = $order->order_number ?? "";
               $notification->product_id  = $order->product_id ?? "";
               $notification->product_varient_id = $order->product_varient_id ?? "";
               $notification->description_en = 'Your items have been carefully shipped and will arrive shortly.';
               $notification->title_en  = 'Your Order Has Been Shipped! #'.$order->order_number;
               $notification->title_tur ='Siparişiniz Gönderildi! #'.$order->order_number;
               $notification->description_tur =  'Ürünleriniz dikkatlice gönderildi ve en kısa sürede elinize ulaşacak.';
               $notification->type           = "shipped";
               $notification->send_by           = 0;
               $notification->save();
            }
        }
            $this->logOrderAction($orderdetails,$order->product_id, $order->product_varient_id, $order->order_status, trans('messages.order_has_been_shipped'));
    
            return response()->json(['success' => true, 'message' => 'Order has been shipped.']);
        }
    
        return response()->json(['success' => false, 'message' => 'Order cannot be shipped.']);
    }
    
    public function delivered($enordid)
    {
        $order_id = !empty($enordid) ? base64_decode($enordid) : null;
        $order = OrderProducts::findOrFail($order_id);
        $orderdetails = Order::where('order_number', $order->order_number)->first();
    
        if ($order->order_status === 'shipped') {
            $order->order_status = 'delivered';
            $order->order_completed_at = now();
            $order->save();
    
            $updated_order_statuses = OrderProducts::where('order_number', $order->order_number)->pluck('order_status');
            if ($updated_order_statuses->every(fn($status) => $status === 'delivered')) {
                $orderdetails->status = 'delivered';
                $orderdetails->order_completed_at = now();
                $orderdetails->save();
            }

            $userDetail  = User::find($orderdetails->user_id);
            $userDetailToken =UserDeviceToken::where('user_id',$userDetail->id)->orderBy('id', 'desc')->first();
            if($userDetail->language == 2){
                $order_des ='Siparişiniz bugün teslim edildi. Keyifle kullanmanızı dileriz!';
                $msg_title = 'Sipariş Teslim Edildi #'.$order->order_number;
            }else{
                $order_des ='Your order was delivered today. We hope you enjoy your purchase!';
                $msg_title = 'Order Delivered #'.$order->order_number;
            }
            $data=[
                'order_number' => $order->order_number, 
                'product_id' => $order->product_id,
                'product_varient_id'=> $order->product_varient_id,
            ];
            if($userDetail->push_notification == 1){
               if (!empty($userDetailToken->device_token)) {
                   $this->send_push_notification(
                       $userDetailToken->device_token,
                       $userDetailToken->device_type,
                       $order_des,
                       $msg_title,
                       'delivered',
                       $data
                   );
               $notification = new Notification;
               $notification->user_id   = $userDetail->id;
               $notification->action_user_id = Auth::guard('admin')->user()->id;
               $notification->order_number = $order->order_number ?? "";
               $notification->product_id  = $order->product_id ?? "";
               $notification->product_varient_id = $order->product_varient_id ?? "";
               $notification->description_en = 'Your order was delivered today. We hope you enjoy your purchase!';
               $notification->title_en  = 'Order Delivered #'.$order->order_number;
               $notification->title_tur ='Sipariş Teslim Edildi #'.$order->order_number;
               $notification->description_tur =  'Siparişiniz bugün teslim edildi. Keyifle kullanmanızı dileriz!';;
               $notification->type           = "delivered";
               $notification->send_by           = 0;
               $notification->save();
               }
            }
    
            $this->logOrderAction($orderdetails,$order->product_id, $order->product_varient_id, $order->order_status, trans('messages.order_has_been_delivered'));
    
            return response()->json(['success' => true, 'message' => 'Order has been delivered successfully.']);
        }
    
        return response()->json(['success' => false, 'message' => 'Order cannot be delivered.']);
    }
    
    public function acceptall($enordid)
    {
        $order_id = !empty($enordid) ? base64_decode($enordid) : null;
        $order = Order::findOrFail($order_id);
    
        if ($order->status === 'pending') {
            $orderProducts = OrderProducts::where('order_number', $order->order_number)->get();
            $updated_order_statuses = $orderProducts->pluck('order_status');
    
            if ($updated_order_statuses->isNotEmpty() && $updated_order_statuses->every(fn($status) => $status === $order->status)) {
                foreach ($orderProducts as $product) {
                    $product->order_status = 'confirmed';
                    $product->order_confirmed_at = now();
                    $product->save();
                }
            }
    
            $order->status = 'confirmed';
            $order->order_confirmed_at = now();
            $order->save();

            $userDetail  = User::find($order->user_id);
            $userDetailToken =UserDeviceToken::where('user_id',$userDetail->id)->first();
            if($userDetail->language == 2){
                $order_des ='Sipariş başarıyla verildi';
                $msg_title = 'Yeni Sipariş #' . $order->order_number;
            }else{
                $order_des ='Order has been  All Product Accpected';
                $msg_title = 'New Order #' . $order->order_number;
            }
            $data=[
                'order_number' => $order->order_number, 
                'product_id' => $order->product_id,
                'product_varient_id'=> $order->product_varient_id,
            ];
    
            if (!empty($userDetailToken->device_token)) {
                $this->send_push_notification(
                    $userDetailToken->device_token,
                    $userDetailToken->device_type,
                    $order_des,
                    $msg_title,
                    'order_confirm',
                    $data
                );
            }
            $this->logOrderAction($order,$order->product_id, $order->product_varient_id, $order->status, trans('messages.all_orders_has_been_accepted'));
    
            return response()->json(['success' => true, 'message' => 'Order has been accepted.']);
        }
    
        return response()->json(['success' => false, 'message' => 'Order cannot be accepted.']);
    }
    
    public function rejectall(Request $request, $enordid)
    {
        $request->validate([
            'reject_reason' => 'required',
        ]);
    
        $order_id = !empty($enordid) ? base64_decode($enordid) : null;
        $order = Order::findOrFail($order_id);
    
        if ($order->status === 'pending') {
            $reasons = $request->input('reject_reason');
            $orderProducts = OrderProducts::where('order_number', $order->order_number)->get();
            $updated_order_statuses = $orderProducts->pluck('order_status');
    
            if ($updated_order_statuses->isNotEmpty() && $updated_order_statuses->every(fn($status) => $status === $order->status)) {
                foreach ($orderProducts as $product) {
                    $product->order_status = 'declined';
                    $product->reject_status = 1;
                    $product->reject_reason = $reasons;
                    $product->save();
                }
            }
    
            $order->reject_reason = $reasons;
            $order->reject_status = 1;
            $order->status = 'declined';
            $order->save();
            $userDetail  = User::find($order->user_id);
            $userDetailToken =UserDeviceToken::where('user_id',$userDetail->id)->first();
            if($userDetail->language == 2){
                $order_des ='Sipariş başarıyla verildi';
                $msg_title = 'Yeni Sipariş #' . $order->order_number;
            }else{
                $order_des ='Order has been  All Product Rejected';
                $msg_title = 'New Order #' . $order->order_number;
            }
            $data=[
                'order_number' => $order->order_number, 
                'product_id' => $order->product_id,
                'product_varient_id'=> $order->product_varient_id,
            ];
    
            if (!empty($userDetailToken->device_token)) {
                $this->send_push_notification(
                    $userDetailToken->device_token,
                    $userDetailToken->device_type,
                    $order_des,
                    $msg_title,
                    'order_rejected',
                    $data
                );
            }

            $this->logOrderAction($order,$order->product_id, $order->product_varient_id,$order->status, $reasons);
    
            return response()->json(['success' => true, 'message' => 'Order has been rejected successfully.']);
        }
    
        return response()->json(['success' => false, 'message' => 'Order cannot be rejected.'], 400);
    }
    
    public function packedall($enordid)
    {
        $order_id = !empty($enordid) ? base64_decode($enordid) : null;
        $order = Order::findOrFail($order_id);
    
        if ($order->status === 'confirmed') {
            $orderProducts = OrderProducts::where('order_number', $order->order_number)->get();
            $updated_order_statuses = $orderProducts->pluck('order_status');
    
            if ($updated_order_statuses->isNotEmpty() && $updated_order_statuses->every(fn($status) => $status === $order->status)) {
                foreach ($orderProducts as $product) {
                    $product->order_status = 'packed';
                    $product->on_the_way = now();
                    $product->save();
                }
            }
    
            $order->status = 'packed';
            $order->on_the_way = now();
            $order->save();

            $userDetail  = User::find($order->user_id);
            $userDetailToken =UserDeviceToken::where('user_id',$userDetail->id)->first();
            if($userDetail->language == 2){
                $order_des ='Sipariş başarıyla verildi';
                $msg_title = 'Yeni Sipariş #' . $order->order_number;
            }else{
                $order_des ='Order has been  All Product Packed All';
                $msg_title = 'New Order #' . $order->order_number;
            }
            $data=[
                'order_number' => $order->order_number, 
                'product_id' => $order->product_id,
                'product_varient_id'=> $order->product_varient_id,
            ];
    
            if (!empty($userDetailToken->device_token)) {
                $this->send_push_notification(
                    $userDetailToken->device_token,
                    $userDetailToken->device_type,
                    $order_des,
                    $msg_title,
                    'order_packed',
                    $data
                );
            }
            $this->logOrderAction($order,$order->product_id, $order->product_varient_id, $order->status, trans('messages.all_orders_is_now_packed'));
    
            return response()->json(['success' => true, 'message' => 'Order is now packed.']);
        }
    
        return response()->json(['success' => false, 'message' => 'Order cannot be packed.']);
    }
    
    public function shippedall(Request $request, $enordid)
    {
        $shippingDate = $request->input('shipped');
        $order_id = !empty($enordid) ? base64_decode($enordid) : null;
        $order = Order::findOrFail($order_id);
    
        if ($order->status === 'packed') {
            $orderProducts = OrderProducts::where('order_number', $order->order_number)->get();
            $updated_order_statuses = $orderProducts->pluck('order_status');
            $formattedShippingDate = Carbon::createFromFormat('Y-m-d\TH:i', $shippingDate)->format('Y-m-d H:i:s');
    
            if ($updated_order_statuses->isNotEmpty() && $updated_order_statuses->every(fn($status) => $status === 'packed')) {
                foreach ($orderProducts as $product) {
                    $product->order_status = 'shipped';
                    $product->estimated_date = $formattedShippingDate;
                    $product->save();
                }
            }
    
            $order->status = 'shipped';
            $order->estimated_date = $formattedShippingDate;
            $order->save();


            $userDetail  = User::find($order->user_id);
            $userDetailToken =UserDeviceToken::where('user_id',$userDetail->id)->first();
            if($userDetail->language == 2){
                $order_des ='Sipariş başarıyla verildi';
                $msg_title = 'Yeni Sipariş #' . $order->order_number;
            }else{
                $order_des ='Order has been  All Product Shipped';
                $msg_title = 'New Order #' . $order->order_number;
            }
            $data=[
                'order_number' => $order->order_number, 
                'product_id' => $order->product_id,
                'product_varient_id'=> $order->product_varient_id,
            ];
            if (!empty($userDetailToken->device_token)) {
                $this->send_push_notification(
                    $userDetailToken->device_token,
                    $userDetailToken->device_type,
                    $order_des,
                    $msg_title,
                    'order_shipped',
                    $data
                );
            }
            $this->logOrderAction($order,$order->product_id, $order->product_varient_id, $order->status, trans('messages.all_orders_has_been_shipped'));
    
            return response()->json(['success' => true, 'message' => 'Order has been shipped.']);
        }
    
        return response()->json(['success' => false, 'message' => 'Order cannot be shipped.']);
    }
    
    public function deliveredall($enordid)
    {
        $order_id = !empty($enordid) ? base64_decode($enordid) : null;
        $order = Order::findOrFail($order_id);
        $allProductsId = [];
        $allVariantsId = [];
        $order_data    = OrderProducts::where('order_number', $order->order_number)->get();
        foreach ($order_data as $value) {
            $allProductsId[] = $value['product_id'];
            $allVariantsId[] = $value['variant_id'];
        }  
        if ($order->status === 'shipped') {
            $orderProducts = OrderProducts::where('order_number', $order->order_number)->get();
            $updated_order_statuses = $orderProducts->pluck('order_status');
    
            if ($updated_order_statuses->isNotEmpty() && $updated_order_statuses->every(fn($status) => $status === 'shipped')) {
                foreach ($orderProducts as $product) {
                    $product->order_status = 'delivered';
                    $product->order_completed_at = now();
                    $product->save();
                }
            }
            $order->status = 'delivered';
            $order->order_completed_at = now();
            $order->save();
            $userDetail  = User::find($order->user_id);
            $userDetailToken =UserDeviceToken::where('user_id',$userDetail->id)->first();
            if($userDetail->language == 2){
                $order_des ='Siparişin Tamamı Teslim Edildi';
                $msg_title = 'Yeni Sipariş #' . $order->order_number;
            }else{
                $order_des ='Order has been  All Product Delivered';
                $msg_title = 'New Order #' . $order->order_number;
            }
            $data=[
       'order_number' => $order->order_number, 
       'product_id' => $allProductsId,
       'product_varient_id'=> $allVariantsId,
            ];
    
            if (!empty($userDetailToken->device_token)) {
                $this->send_push_notification(
                    $userDetailToken->device_token,
                    $userDetailToken->device_type,
                    $order_des,
                    $msg_title,
                    'all_products_delivered',
                    $data
                );
            $notification = new Notification;
            $notification->user_id   = $userDetail->id;
            $notification->action_user_id = Auth::guard('admin')->user()->id;
            $notification->order_number = $order->order_number ?? "";
            $notification->product_id  = $order->product_id ?? "";
            $notification->product_varient_id = $order->product_varient_id ?? "";
            $notification->description_en = 'Order has been  All Product Delivered';
            $notification->title_en  = 'New Order #' . $order->order_number;
            $notification->title_tur ='Yeni Sipariş #' . $order->order_number;
            $notification->description_tur =  'Siparişin Tamamı Teslim Edildi';
            $notification->type           = "all_products_delivered";
            $notification->send_by           = 0;
            $notification->save();
            }
            $this->logOrderAction($order,$order->product_id, $order->product_varient_id, $order->status, trans('messages.all_orders_has_been_delivered'));
    
            return response()->json(['success' => true, 'message' => 'Order has been delivered successfully.']);
        }
    
        return response()->json(['success' => false, 'message' => 'Order cannot be delivered.']);
    }
    
    private function logOrderAction($orderdetails,$productId, $productVariantId, $notes, $description)
    {
        $newOrderlog = new OrderLog();
        $newOrderlog->user_id = $orderdetails->user_id;
        $newOrderlog->order_id = $orderdetails->id;
        $newOrderlog->order_number = $orderdetails->order_number;
        $newOrderlog->product_id = $productId;
        $newOrderlog->product_variant_id = $productVariantId;
        $newOrderlog->notes = $notes;
        $newOrderlog->description = $description;
        $newOrderlog->save();
    }

    
    
}
