<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'staff_id'  => 'required|integer|exists:users,id',
            'rating'    => 'required|integer|min:1|max:5',
            'review'    => 'nullable|string',
        ]);

        $userId = Auth::guard('api')->user()->id;

        $review = Review::create([
            'given_by_id'      => $userId,
            'given_by_type'    => 'user',
            'received_by_id'   => $validated['staff_id'],
            'received_by_type' => 'user',
            'rating'           => $validated['rating'],
            'review'           => $validated['review'] ?? null,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Review added successfully',
            'data'    => $review,
        ]);
    }

    public function index(Request $request)
    {
        $reviews = Review::with(['service'])
            ->when($request->given_by_id, fn($q) => $q->where('given_by_id', $request->given_by_id))
            ->when($request->received_by_id, fn($q) => $q->where('received_by_id', $request->received_by_id))
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Reviews fetched successfully',
            'data'    => $reviews,
        ]);
    }

    public function selfIndex(Request $request)
    {
        $userId = Auth::guard('api')->user()->id;

        $reviews = Review::with(['service'])
            ->where(function ($q) use ($userId) {
                $q->where('given_by_id', $userId)
                  ->orWhere('received_by_id', $userId);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Reviews fetched successfully',
            'data'    => $reviews,
        ]);
    }

    public function destroy($id)
    {
        $review = Review::find($id);

        if (!$review) {
            return response()->json([
                'status'  => false,
                'message' => 'Review not found',
            ], 404);
        }

        $review->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Review deleted successfully',
        ]);
    }
}
