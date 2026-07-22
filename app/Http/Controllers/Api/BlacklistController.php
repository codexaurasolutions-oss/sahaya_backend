<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Termination;

class BlacklistController extends Controller
{
    public function index()
    {
        $blacklists = Termination::with(['user', 'approver', 'reporter'])
            ->where('is_blacklist', 1)
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'Blacklist records retrieved successfully.',
            'data' => $blacklists,
        ]);
    }
}
