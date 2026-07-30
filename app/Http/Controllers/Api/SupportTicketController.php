<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\ZohoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Traits\ImageUpload;

class SupportTicketController extends Controller
{
    use ImageUpload;

    private $categories = ['Payment', 'Staff', 'App Bug', 'Attendance', 'Leave', 'Salary', 'KYC', 'General'];

    public function store(Request $request)
    {
        try {
            $user = Auth::guard('api')->user();

            $validator = Validator::make($request->all(), [
                'subject' => 'required|string|max:255',
                'description' => 'required|string',
                'category' => 'nullable|string|in:Payment,Staff,App Bug,Attendance,Leave,Salary,KYC,General',
                'priority' => 'nullable|string|in:Low,Medium,High,Urgent',
                'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors()->first(),
                ], 422);
            }

            $data = $request->only(['subject', 'description', 'category', 'priority']);
            $data['user_id'] = $user->id;
            $data['priority'] = $data['priority'] ?? 'Medium';
            $data['status'] = 'Open';

            if ($request->hasFile('image')) {
                $directory = 'uploads/tickets';
                $path = $this->uploadCloudary($request, 'image', $directory);
                $data['image'] = $path;
            }

            $ticket = Ticket::create($data);

            // Sync to Zoho Desk
            $zohoTicketId = $this->createZohoTicket($ticket, $user);
            if ($zohoTicketId) {
                $ticket->update(['zoho_ticket_id' => $zohoTicketId]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Support ticket created successfully',
                'data' => $ticket->fresh(),
            ]);
        } catch (\Exception $e) {
            Log::error('Ticket create error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $user = Auth::guard('api')->user();

            $query = Ticket::where('user_id', $user->id);

            if ($request->has('status') && $request->status !== 'All') {
                $query->where('status', $request->status);
            }

            $tickets = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Tickets retrieved successfully',
                'data' => $tickets,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $user = Auth::guard('api')->user();

            $ticket = Ticket::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$ticket) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Ticket not found',
                ], 404);
            }

            // Fetch comments from Zoho Desk if connected
            $comments = [];
            if ($ticket->zoho_ticket_id) {
                $comments = $this->getZohoComments($ticket->zoho_ticket_id);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Ticket retrieved successfully',
                'data' => [
                    'ticket' => $ticket,
                    'comments' => $comments,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function addComment(Request $request, $id)
    {
        try {
            $user = Auth::guard('api')->user();

            $validator = Validator::make($request->all(), [
                'comment' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors()->first(),
                ], 422);
            }

            $ticket = Ticket::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$ticket) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Ticket not found',
                ], 404);
            }

            // Add comment to Zoho Desk
            if ($ticket->zoho_ticket_id) {
                $this->addZohoComment($ticket->zoho_ticket_id, $request->comment, $user);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Comment added successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function categories()
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->categories,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // ZOHO DESK INTEGRATION
    // ═══════════════════════════════════════════════════════════════

    private function createZohoTicket(Ticket $ticket, $user): ?string
    {
        try {
            $zohoService = new ZohoService('desk');

            if (!$zohoService->isAuthorized()) {
                Log::warning('Zoho Desk not authorized, skipping ticket sync');
                return null;
            }

            // Get departments to find default
            $deptResult = $zohoService->makeRequest('GET', '/departments');
            $departmentId = null;
            if ($deptResult['ok']) {
                $departments = $deptResult['data']['departments'] ?? $deptResult['data']['data'] ?? [];
                if (!empty($departments)) {
                    $departmentId = $departments[0]['id'];
                }
            }

            if (!$departmentId) {
                Log::warning('No Zoho Desk department found, skipping ticket sync');
                return null;
            }

            // Create or find contact in Zoho Desk
            $contactId = $this->getOrCreateZohoContact($zohoService, $user);

            $body = "Category: {$ticket->category}\nPriority: {$ticket->priority}\n\n{$ticket->description}";
            if ($user->email) {
                $body .= "\n\nUser Email: {$user->email}";
            }
            if ($user->phone_number) {
                $body .= "\nUser Phone: {$user->phone_number}";
            }

            $ticketData = [
                'subject' => $ticket->subject,
                'description' => $body,
                'departmentId' => $departmentId,
                'priority' => $ticket->priority,
                'status' => 'Open',
                'channel' => 'Sahayya App',
            ];

            if ($contactId) {
                $ticketData['contactId'] = $contactId;
            }

            $result = $zohoService->makeRequest('POST', '/tickets', $ticketData);

            if ($result['ok'] && isset($result['data']['id'])) {
                Log::info("Zoho Desk ticket created: {$result['data']['id']}");
                return $result['data']['id'];
            }

            Log::error('Zoho Desk ticket creation failed', $result);
            return null;
        } catch (\Exception $e) {
            Log::error('Zoho Desk ticket sync error: ' . $e->getMessage());
            return null;
        }
    }

    private function getOrCreateZohoContact(ZohoService $zohoService, $user): ?string
    {
        try {
            // Search existing contact by email or phone
            $searchEmail = $user->email;
            $searchPhone = $user->phone_number;

            if ($searchEmail) {
                $result = $zohoService->makeRequest('GET', '/contacts', ['email' => $searchEmail]);
                if ($result['ok']) {
                    $contacts = $result['data']['data'] ?? $result['data']['contacts'] ?? [];
                    if (!empty($contacts)) {
                        return $contacts[0]['id'];
                    }
                }
            }

            if ($searchPhone) {
                $result = $zohoService->makeRequest('GET', '/contacts', ['phone' => $searchPhone]);
                if ($result['ok']) {
                    $contacts = $result['data']['data'] ?? $result['data']['contacts'] ?? [];
                    if (!empty($contacts)) {
                        return $contacts[0]['id'];
                    }
                }
            }

            // Create new contact
            $contactData = [
                'firstName' => !empty($user->first_name) ? trim($user->first_name) : trim($user->name ?? 'Sahayya'),
                'lastName' => !empty($user->last_name) ? trim($user->last_name) : 'Kumar',
            ];
            if ($searchEmail) {
                $contactData['email'] = $searchEmail;
            }
            if ($searchPhone) {
                $contactData['phone'] = $searchPhone;
            }

            $createResult = $zohoService->makeRequest('POST', '/contacts', $contactData);
            if ($createResult['ok'] && isset($createResult['data']['id'])) {
                Log::info("Zoho Desk contact created: {$createResult['data']['id']}");
                return $createResult['data']['id'];
            }

            Log::error('Zoho Desk contact creation failed', $createResult);
            return null;
        } catch (\Exception $e) {
            Log::error('Zoho Desk contact error: ' . $e->getMessage());
            return null;
        }
    }

    private function getZohoComments(string $zohoTicketId): array
    {
        try {
            $zohoService = new ZohoService('desk');
            $result = $zohoService->makeRequest('GET', "/tickets/{$zohoTicketId}/comments");

            if ($result['ok'] && isset($result['data'])) {
                return $result['data']['comments'] ?? [];
            }
            return [];
        } catch (\Exception $e) {
            Log::error('Zoho Desk fetch comments error: ' . $e->getMessage());
            return [];
        }
    }

    private function addZohoComment(string $zohoTicketId, string $commentText, $user): void
    {
        try {
            $zohoService = new ZohoService('desk');
            $zohoService->makeRequest('POST', "/tickets/{$zohoTicketId}/comments", [
                'content' => $commentText,
                'isPublic' => true,
            ]);
        } catch (\Exception $e) {
            Log::error('Zoho Desk add comment error: ' . $e->getMessage());
        }
    }
}
