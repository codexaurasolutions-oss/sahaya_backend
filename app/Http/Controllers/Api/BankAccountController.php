<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BankAccountController extends Controller
{
    public function index()
    {
        try {
            $userId = Auth::guard('api')->user()->id;
            
            $bankAccounts = BankAccount::with('user')
                ->where('user_id', $userId)
                ->get();
                
            return response()->json([
                'success' => true,
                'data' => $bankAccounts
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve bank accounts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

  public function setAcc(Request $request, $id)
{
    $userId = Auth::guard('api')->user()->id;

    $bankAccount = BankAccount::where('id', $id)
        ->where('user_id', $userId)
        ->first();

    if (!$bankAccount) {
        return response()->json([
            'status'  => 'error',
            'message' => 'Bank account not found or does not belong to you'
        ], 404);
    }

    // Reset any previously set account for this user
    BankAccount::where('user_id', $userId)
        ->where('is_set', 1)
        ->update(['is_set' => 0]);

    // Set the selected account
    $bankAccount->is_set = 1;
    $bankAccount->save();

    return response()->json([
        'status'  => 'success',
        'message' => 'Bank account set successfully',
        'data'    => $bankAccount
    ]);
}


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_name' => 'required|string',
            'account_number' => 'required|string',
            'ifsc_code' => 'required|string',
            'bank_type' => 'required|in:saving,current'
            // Removed user_id from validation since it comes from auth
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = Auth::guard('api')->user()->id;
            
            $bankAccountData = $request->all();
            $bankAccountData['user_id'] = $userId; // Set user_id from authenticated user
            
            $bankAccount = BankAccount::create($bankAccountData);
            
            return response()->json([
                'success' => true,
                'message' => 'Bank account created successfully',
                'data' => $bankAccount
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create bank account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $userId = Auth::guard('api')->user()->id;
            
            $bankAccount = BankAccount::with('user')
                ->where('user_id', $userId)
                ->find($id);
            
            if (!$bankAccount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank account not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $bankAccount
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve bank account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $userId = Auth::guard('api')->user()->id;
            
            $bankAccount = BankAccount::where('user_id', $userId)
                ->find($id);
            
            if (!$bankAccount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank account not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'bank_name' => 'sometimes|required|string',
                'account_number' => 'sometimes|required|string',
                'ifsc_code' => 'sometimes|required|string',
                'bank_type' => 'sometimes|required|in:saving,current'
                // Removed user_id from validation since it shouldn't be updated
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Remove user_id from request data to prevent updating it
            $updateData = $request->all();
            unset($updateData['user_id']);
            
            $bankAccount->update($updateData);
            
            return response()->json([
                'success' => true,
                'message' => 'Bank account updated successfully',
                'data' => $bankAccount
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update bank account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $userId = Auth::guard('api')->user()->id;
            
            $bankAccount = BankAccount::where('user_id', $userId)
                ->find($id);
            
            if (!$bankAccount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank account not found'
                ], 404);
            }

            $bankAccount->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Bank account deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete bank account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getByType($type)
    {
        try {
            if (!in_array($type, ['saving', 'current'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid bank type. Must be saving or current'
                ], 422);
            }

            $userId = Auth::guard('api')->user()->id;
            
            $bankAccounts = BankAccount::with('user')
                ->where('user_id', $userId)
                ->where('bank_type', $type)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $bankAccounts
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve bank accounts by type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

   public function vendorTransactionsList(Request $request)
{
    $userId = Auth::guard('api')->user()->id;
        $list = Transaction::where('user_id', $userId)
        ->with(['order', 'user'])
        ->orderBy('created_at', 'desc')
        ->get();

    // Generate PDF links for each transaction
    $list->each(function ($transaction) {
        $url = $this->generateInvoicePdf($transaction);
        $transaction->invoice_pdf = $url;
    });

    return response()->json([
        'success' => true,
        'data' => $list
    ], 200);
}
private function generateInvoicePdf($transaction)
{
    try {
        $filename = 'invoice-' . $transaction->order_number . '-' . Str::random(8) . '.pdf';
        $directory = 'invoices/' . $transaction->user_id;
        $fullPath = $directory . '/' . $filename;
        if (!file_exists(public_path($directory))) {
            mkdir(public_path($directory), 0755, true);
        }
        $data = [
            'transaction' => $transaction,
            'company' => [
                'name' => 'QuickMySlot',
                'address' => '123 Company Address',
                'phone' => '+1 234 567 890',
                'email' => 'info@company.com'
            ]
        ];
        $pdf = PDF::loadView('pdf.invoice', $data);
        file_put_contents(public_path($fullPath), $pdf->output());
        return url($fullPath);

    } catch (\Exception $e) {
        \Log::error('PDF Generation Error: ' . $e->getMessage());
        return null;
    }
}

// Additional method to download single invoice
public function downloadInvoice($transactionId)
{
    $userId = Auth::guard('api')->user()->id;
    
    $transaction = Transaction::where('user_id', $userId)
        ->where('id', $transactionId)
        ->with(['order', 'user'])
        ->firstOrFail();

    $data = [
        'transaction' => $transaction,
        'company' => [
            'name' => 'Your Company Name',
            'address' => '123 Company Address',
            'phone' => '+1 234 567 890',
            'email' => 'info@company.com'
        ]
    ];

    $pdf = PDF::loadView('pdf.invoice', $data);
    
    return $pdf->download('invoice-' . $transaction->order_number . '.pdf');
}
}