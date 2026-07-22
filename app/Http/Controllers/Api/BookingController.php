<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Booking;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Models\Category;
use App\Models\Banner;
use App\Models\Transaction;
use App\Models\Notification;
use App\Models\Cart;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
class BookingController extends Controller
{
    /**
     * Add Booking
     */
   public function addBooking(Request $request)
{
    $validator = Validator::make($request->all(), [
        'order_id'        => 'nullable',
        'customer_id'     => 'required',
        'vendor_id'       => 'required',
        'schedule_time'   => 'required',
        'service_id'      => 'required',
        'amount'          => 'required|numeric|min:1',
        'tax'             => 'required|numeric|min:0',
        'platform_fee'    => 'required|numeric|min:0',
        'status'          => 'required|in:pending,confirmed,rescheduled,cancelled,rejected',
        'reschedule_time' => 'nullable|date|after:'.now()->addHours(24),
        'rejection_reason'=> 'nullable|string',
        'is_paid_key'     => 'required|boolean',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status'  => 'error',
            'message' => 'Validation failed',
            'errors'  => $validator->errors()
        ], 422);
    }
    $data = $request->all();
    if ($request->is_paid_key == 1) {
        $api_key    = config('services.razorpay.key');
        $api_secret = config('services.razorpay.secret');
        $razorpayData = [
            "amount"          => $request->amount * 100, 
            "currency"        => "INR",
            "receipt"         => "booking_" . uniqid(),
            "payment_capture" => 1
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://api.razorpay.com/v1/orders",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => "$api_key:$api_secret",
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($razorpayData),
        ]);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) {
            return response()->json(['status' => false, 'message' => $err], 500);
        }
        $order = json_decode($response, true);
        $data['order_id'] = $order['id']; 
    } else {
        $data['order_id'] = null;
    }
    $user = Auth::guard('api')->user();
    $booking = Booking::create($data);
     Cart::where('user_id', $user->id)
        ->where('service_id', $booking->service_id)
        ->delete();
    return response()->json([
        'status'  => 'success',
        'message' => 'Booking added successfully',
        'data'    => $booking
    ], 201);
}

public function bookingCreate(Request $request, $id)
{
    $booking = Booking::find($id);

    if (!$booking) {
        return response()->json([
            'status' => 'error',
            'message' => 'Booking not found'
        ], 404);
    }

    // Only proceed if booking is not paid yet
    if (!$booking->order_id) {
        $api_key    = config('services.razorpay.key');
        $api_secret = config('services.razorpay.secret');
        $razorpayData = [
            "amount"          => $booking->amount * 100, // in paise
            "currency"        => "INR",
            "receipt"         => "booking_" . uniqid(),
            "payment_capture" => 1
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://api.razorpay.com/v1/orders",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => "$api_key:$api_secret",
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($razorpayData),
        ]);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return response()->json(['status' => false, 'message' => $err], 500);
        }

        $order = json_decode($response, true);
        $booking->order_id = $order['id'];
        $booking->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Razorpay order created successfully',
            'data'    => $booking
        ], 200);
    }

    return response()->json([
        'status' => 'info',
        'message' => 'Booking already has an order Id',
        'data' => $booking
    ], 200);
}

public function verifyBookingPayment(Request $request)
{
    $request->validate([
        'razorpay_order_id'   => 'required',
        'razorpay_payment_id' => 'required',
        'razorpay_signature'  => 'required',
        'booking_id'          => 'required|exists:bookings,id',
    ]);

    $user = Auth::guard('api')->user();
    $booking = Booking::findOrFail($request->booking_id);
    $generated_signature = hash_hmac(
        'sha256',
        $request->razorpay_order_id . "|" . $request->razorpay_payment_id,
        config('services.razorpay.secret')
    );

    if ($generated_signature !== $request->razorpay_signature) {
        return response()->json([
            'status'  => false,
            'message' => 'Invalid payment signature'
        ], 400);
    }
    $booking->update([
        'payment_id' => $request->razorpay_payment_id,
        'status'     => 'confirmed'
    ]);
   
   Notification::create([
    'user_id' => $user->id,
    'title'   => 'Booking Created',
    'message' => 'Your booking ' . $booking->service->name . ' has been created successfully.',
    'status'  => 'unread',
]);

Notification::create([
    'user_id' => $booking->vendor_id,
    'title'   => 'New Booking Received',
    'message' => 'You have received a new booking '. $booking->service->name . ' from a customer.',
    'status'  => 'unread',
]);


    return response()->json([
        'status'  => true,
        'message' => 'Payment verified successfully and booking confirmed.',
        'booking' => $booking
    ]);
}

    /**
     * Booking List
     */
 public function bookingList(Request $request)
{
    $userId = Auth::guard('api')->user()->id;

    $query = Booking::with(['customer', 'vendor', 'service'])
        ->where('customer_id', $userId)
        ->orderBy('created_at', 'desc');

    if ($request->has('status') && !empty($request->status)) {
        $query->where('status', $request->status);
    }

    if ($request->has('is_has_order_id') && $request->is_has_order_id == 1) {
        $query->whereNotNull('order_id');
    }

    if ($request->has('date') && !empty($request->date)) {
        $query->whereDate('created_at', $request->date);
    }

    $bookings = $query->get();

    // Generate PDF links for each booking
    $bookings->each(function ($booking) {
        $booking->invoice_pdf = $this->generateBookingInvoicePdf($booking);
    });

    return response()->json([
        'status' => 'success',
        'data'   => $bookings
    ]);
}

private function generateBookingInvoicePdf($booking)
{
    try {
        // Generate unique filename
        $filename = 'booking-invoice-' . ($booking->order_id ?? $booking->id) . '-' . Str::random(8) . '.pdf';
        $directory = 'invoices/booking/' . $booking->customer_id;
        $fullPath = $directory . '/' . $filename;

        // Ensure directory exists in public folder
        if (!file_exists(public_path($directory))) {
            mkdir(public_path($directory), 0755, true);
        }

        // PDF data
        $data = [
            'booking' => $booking,
            'company' => [
                'name' => 'QuickMySlot',
                'address' => '123 Company Address',
                'phone' => '+1 234 567 890',
                'email' => 'info@company.com'
            ]
        ];

        // Generate HTML content
        $html = view('pdf.booking_invoice', $data)->render();

        // Setup DomPDF
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Save to public directory
        file_put_contents(public_path($fullPath), $dompdf->output());

        // Return accessible URL
        return url($fullPath);

    } catch (\Exception $e) {
        \Log::error('Booking PDF Generation Error: ' . $e->getMessage());
        return null;
    }
}



public function vendorBookingList(Request $request){
       $userId = Auth::guard('api')->user()->id;
    $query = Booking::with(['customer', 'vendor', 'service'])->where('vendor_id',$userId)
        ->orderBy('created_at', 'desc');

    if ($request->has('status') && !empty($request->status)) {
        $status = $request->status;

        // Handle both single status and multiple statuses (comma-separated)
       
            $query->where('status', $status);
    }

    $bookings = $query->get();
     $bookings->each(function ($booking) {
        $booking->invoice_pdf = $this->generateBookingInvoicePdf($booking);
    });

    return response()->json([
        'status' => 'success',
        'data'   => $bookings
    ]);
}


    /**
     * Reschedule Booking
     */
    public function rescheduleBooking(Request $request, $id)
    {
        $booking = Booking::find($id);

        if (!$booking) {
            return response()->json([
                'status' => 'error',
                'message' => 'Booking not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'reschedule_time' => 'required|date|after:'.now()->addHours(24)
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $booking->update([
            'reschedule_time' => $request->reschedule_time,
            'status' => 'rescheduled'
        ]);

         $userId = Auth::guard('api')->user()->id;
        $initiator = ($userId == $booking->customer_id) ? 'customer' : 'vendor';

        if ($initiator === 'customer') {
            // Notification for vendor
            Notification::create([
                'user_id' => $booking->vendor_id,
                'title' => 'Booking Rescheduled',
                'message' => 'Booking #' . $booking->order_id . ' has been rescheduled by the customer.',
                'status' => 'unread',
                'type' => 'booking_rescheduled'
            ]);
        } else {
            // Notification for customer
            Notification::create([
                'user_id' => $booking->customer_id,
                'title' => 'Booking Rescheduled',
                'message' => 'Your booking #' . $booking->order_id . ' has been rescheduled by the vendor.',
                'status' => 'unread',
                'type' => 'booking_rescheduled'
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Booking rescheduled successfully',
            'data' => $booking
        ]);
    }

    /**
     * Cancel / Reject Booking
     */
    public function cancelBooking(Request $request, $id)
    {
        $booking = Booking::find($id);

        if (!$booking) {
            return response()->json([
                'status' => 'error',
                'message' => 'Booking not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string',
            'status' => 'required|in:cancelled,rejected'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $booking->update([
            'rejection_reason' => $request->rejection_reason,
            'status' => $request->status
        ]);

          $userId = Auth::guard('api')->user()->id;
        $initiator = ($userId == $booking->customer_id) ? 'customer' : 'vendor';
        $action = ($request->status === 'cancelled') ? 'cancelled' : 'rejected';

        if ($initiator === 'customer') {
            // Notification for vendor
            Notification::create([
                'user_id' => $booking->vendor_id,
                'title' => 'Booking ' . ucfirst($action),
                'message' => 'Booking #' . $booking->order_id . ' has been ' . $action . ' by the customer. Reason: ' . $request->rejection_reason,
                'status' => 'unread',
                'type' => 'booking_' . $action
            ]);
        } else {
            // Notification for customer
            Notification::create([
                'user_id' => $booking->customer_id,
                'title' => 'Booking ' . ucfirst($action),
                'message' => 'Your booking #' . $booking->order_id . ' has been ' . $action . ' by the vendor. Reason: ' . $request->rejection_reason,
                'status' => 'unread',
                'type' => 'booking_' . $action
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Booking updated successfully',
            'data' => $booking
        ]);
    }

  /**
 * Booking Details
 */
public function bookingDetails(Request $request, $id)
{
    $booking = Booking::with(['customer', 'vendor', 'service'])->find($id);

    if (!$booking) {
        return response()->json([
            'status' => 'error',
            'message' => 'Booking not found'
        ], 404);
    }
    return response()->json([
        'status' => 'success',
        'data' => $booking
    ]);
}

   public function homeScreen(Request $request)
{
    $banner = Banner::with('user')->get();
    $userId = Auth::guard('api')->user()->id;

    // Self bookings
    $selfBookings = Booking::with(['customer', 'vendor', 'service'])
        ->where('customer_id', $userId)
        ->orderBy('created_at', 'desc')
        ->get();

    // Vendor bookings
    $vendorBookings = Booking::with(['customer', 'vendor', 'service'])
        ->where('vendor_id', $userId)
        ->orderBy('created_at', 'desc')
        ->get();

    // Category search
    $categoryQuery = Category::query();

    if ($request->has('name') && !empty($request->name)) {
        $categoryQuery->where('name', 'like', '%' . $request->name . '%');
    }

    $categories = $categoryQuery->get();

    return response()->json([
        'status' => true,
        'message' => 'Home screen data fetched successfully',
        'data' => [
            'banners'         => $banner,
            'self_bookings'   => $selfBookings,
            'vendor_bookings' => $vendorBookings,
            'categories'      => $categories,
        ]
    ]);
}
  public function transactionList(Request $request){
        $userId = Auth::guard('api')->user()->id;
        $list = Transaction::where('user_id',$userId)->where('role',"customer")->get();
         return response()->json([
        'status' => true,
        'message' => 'Transaction List fetched successfully',
        'data' => $list,
    ]);
  }


   public function vendorTransactionList(Request $request){
        $userId = Auth::guard('api')->user()->id;
        $list = Transaction::where('user_id',$userId)->where('role',"vendor")->get();
         return response()->json([
        'status' => true,
        'message' => 'Transaction List fetched successfully',
        'data' => $list,
    ]);
  }

  public function acceptBooking($id)
    {
        $booking = Booking::findOrFail($id);

        $booking->status = 'accepted';
        $booking->rejection_reason = null; // clear rejection reason if previously rejected
        $booking->save();

        return response()->json([
            'success' => true,
            'message' => 'Booking accepted successfully',
            'data' => $booking
        ]);
    }

    /**
     * Reject booking
     */
    public function rejectBooking(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);

        $booking->status = 'rejected';
        $booking->rejection_reason = $request->input('rejection_reason', 'No reason provided');
        $booking->save();

        return response()->json([
            'success' => true,
            'message' => 'Booking rejected successfully',
            'data' => $booking
        ]);
    }

      public function completedBooking(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);

        $booking->status = 'completed';
        $booking->save();
        return response()->json([
            'success' => true,
            'message' => 'Booking completed successfully',
            'data' => $booking
        ]);
    }

     public function getTransactions(Request $request)
    {
        $request->validate([
            'type' => 'required|in:debit,credit'
        ]);

        $transactions = Transaction::where('type', $request->type)
                                   ->orderBy('created_at', 'desc')
                                   ->get();

        return response()->json([
            'status' => 'success',
            'data' => $transactions
        ]);
    }

}
