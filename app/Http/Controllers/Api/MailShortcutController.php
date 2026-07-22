<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MailShortcut;
use App\Models\User;
use App\Mail\ShortcutMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class MailShortcutController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $shortcuts = MailShortcut::with('user')->paginate($perPage);
        return response()->json([
            'status' => 'success',
            'message' => 'Mail shortcuts fetched successfully',
            'data' => $shortcuts
        ]);
    }

    public function store(Request $request)
    {
        $userId = Auth::guard('api')->user()->id;

        $data = $request->validate([
            'subject' => 'required|string|max:255',
            'body' => 'nullable|string',
            'is_all_users' => 'nullable|boolean',
            'user_ids' => 'nullable|array',
        ]);

        $data['user_id'] = $userId;

        $shortcut = MailShortcut::create($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Mail shortcut created successfully',
            'data' => $shortcut
        ], 201);
    }

    public function show($id)
    {
        $shortcut = MailShortcut::with('user')->findOrFail($id);
        return response()->json([
            'status' => 'success',
            'message' => 'Mail shortcut fetched successfully',
            'data' => $shortcut
        ]);
    }

    public function update(Request $request, $id)
    {
        $shortcut = MailShortcut::findOrFail($id);
        $data = $request->validate([
            'subject' => 'sometimes|string|max:255',
            'body' => 'nullable|string',
            'is_all_users' => 'nullable',
            'user_ids' => 'nullable|array',
        ]);
        $shortcut->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Mail shortcut updated successfully',
            'data' => $shortcut
        ]);
    }

    public function destroy($id)
    {
        $shortcut = MailShortcut::findOrFail($id);
        $shortcut->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Mail shortcut deleted successfully'
        ]);
    }

    public function sendShortcutMail($id)
    {
        $shortcut = MailShortcut::findOrFail($id);

        if ($shortcut->is_all_users) {
            $users = User::all();
        } else {
            $users = User::whereIn('id', $shortcut->user_ids ?? [])->get();
        }

        foreach ($users as $user) {
            if ($user->email) {
                Mail::to($user->email)->send(
                    new ShortcutMail($shortcut->subject, $shortcut->body)
                );
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Mails sent successfully',
            'sent_to' => $users->pluck('email')
        ]);
    }
}
