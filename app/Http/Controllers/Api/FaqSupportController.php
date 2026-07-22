<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FaqSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class FaqSupportController extends Controller
{
    public function index()
    {
        try {
            $faqs = FaqSupport::with('user')
                ->active()
                ->orderBy('category')
                ->orderBy('created_at', 'desc')
                ->get();
                
            return response()->json([
                'success' => true,
                'data' => $faqs
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve FAQs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

     public function customerIndex()
    {
        try {
            $faqs = FaqSupport::with('user')
            ->where('role','customer')
                ->active()
                ->orderBy('category')
                ->orderBy('created_at', 'desc')
                ->get();
                
            return response()->json([
                'success' => true,
                'data' => $faqs
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve FAQs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category' => 'required|string|max:255',
            'question' => 'required|string',
            'answer' => 'nullable|string',
            'is_active' => 'boolean'
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
            
            $faqData = $request->all();
            $faqData['user_id'] = $userId;
            
            $faq = FaqSupport::create($faqData);
            
            return response()->json([
                'success' => true,
                'message' => 'FAQ created successfully',
                'data' => $faq
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create FAQ',
                'error' => $e->getMessage()
            ], 500);
        }
    }



     public function customerStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category' => 'required|string|max:255',
            'question' => 'required|string',
            'answer' => 'nullable|string',
            'is_active' => 'boolean'
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
            $faqData = $request->all();
            $faqData['user_id'] = $userId;
            $faqData['role'] = "customer";
            $faq = FaqSupport::create($faqData);
            return response()->json([
                'success' => true,
                'message' => 'FAQ created successfully',
                'data' => $faq
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create FAQ',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $faq = FaqSupport::with('user')
                ->active()
                ->find($id);
            
            if (!$faq) {
                return response()->json([
                    'success' => false,
                    'message' => 'FAQ not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $faq
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve FAQ',
                'error' => $e->getMessage()
            ], 500);
        }
    }

     public function customerShow($id)
    {
        try {
            $faq = FaqSupport::with('user')
                ->active()
                ->find($id);
            
            if (!$faq) {
                return response()->json([
                    'success' => false,
                    'message' => 'FAQ not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $faq
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve FAQ',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $userId = Auth::guard('api')->user()->id;
            
            $faq = FaqSupport::find($id);
            
            if (!$faq) {
                return response()->json([
                    'success' => false,
                    'message' => 'FAQ not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'category' => 'sometimes|required|string|max:255',
                'question' => 'sometimes|required|string',
                'answer' => 'nullable|string',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $faq->update($request->all());
            
            return response()->json([
                'success' => true,
                'message' => 'FAQ updated successfully',
                'data' => $faq
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update FAQ',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function customerUpdate(Request $request, $id)
    {
        try {
            $userId = Auth::guard('api')->user()->id;
            
            $faq = FaqSupport::find($id);
            
            if (!$faq) {
                return response()->json([
                    'success' => false,
                    'message' => 'FAQ not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'category' => 'sometimes|required|string|max:255',
                'question' => 'sometimes|required|string',
                'answer' => 'nullable|string',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $faq->update($request->all());
            
            return response()->json([
                'success' => true,
                'message' => 'FAQ updated successfully',
                'data' => $faq
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update FAQ',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function destroy($id)
    {
        try {
            $userId = Auth::guard('api')->user()->id;
            
            $faq = FaqSupport::find($id);
            
            if (!$faq) {
                return response()->json([
                    'success' => false,
                    'message' => 'FAQ not found'
                ], 404);
            }

            $faq->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'FAQ deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete FAQ',
                'error' => $e->getMessage()
            ], 500);
        }
    }

      public function customerDestroy($id)
    {
        try {
            $userId = Auth::guard('api')->user()->id;
            
            $faq = FaqSupport::find($id);
            
            if (!$faq) {
                return response()->json([
                    'success' => false,
                    'message' => 'FAQ not found'
                ], 404);
            }

            $faq->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'FAQ deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete FAQ',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getByCategory($category)
    {
        try {
            $faqs = FaqSupport::with('user')
                ->active()
                ->byCategory($category)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $faqs
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve FAQs by category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getCategories()
    {
        try {
            $categories = FaqSupport::active()
                ->distinct()
                ->pluck('category')
                ->sort()
                ->values();

            return response()->json([
                'success' => true,
                'data' => $categories
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function search(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'query' => 'required|string|min:2'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = $request->input('query');
            
            $faqs = FaqSupport::with('user')
                ->active()
                ->where(function($q) use ($query) {
                    $q->where('question', 'like', "%{$query}%")
                      ->orWhere('answer', 'like', "%{$query}%")
                      ->orWhere('category', 'like', "%{$query}%");
                })
                ->orderBy('category')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $faqs
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search FAQs',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}