<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketComment;
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

    private function stripHtml(string $html): string
    {
        $text = strip_tags($html, "\n");
        $text = str_replace(["<br>", "<br/>", "<br />", "<p>", "</p>", "<div>", "</div>"], "\n", $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }

    private function mapZohoStatus(string $zohoStatus): string
    {
        $map = [
            'open' => 'Open',
            'on hold' => 'In Progress',
            'in progress' => 'In Progress',
            'escalated' => 'In Progress',
            'closed' => 'Closed',
            'cancelled' => 'Closed',
        ];
        return $map[strtolower($zohoStatus)] ?? 'Open';
    }

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

            // Sync status from Zoho Desk if connected
            if ($ticket->zoho_ticket_id) {
                try {
                    $zohoService = new ZohoService('desk');
                    $result = $zohoService->makeRequest('GET', "/tickets/{$ticket->zoho_ticket_id}");
                    if ($result['ok'] && isset($result['data'])) {
                        $zohoStatus = $result['data']['status'] ?? null;
                        if ($zohoStatus) {
                            $mappedStatus = $this->mapZohoStatus($zohoStatus);
                            if ($ticket->status !== $mappedStatus) {
                                $ticket->update(['status' => $mappedStatus]);
                                $ticket->refresh();
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Zoho status sync failed: ' . $e->getMessage());
                }
            }

            // Fetch local comments
            $localComments = TicketComment::where('ticket_id', $ticket->id)
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(fn($c) => [
                    'id' => $c->id,
                    'content' => $c->content,
                    'author' => $c->author,
                    'isPublic' => true,
                    'created_at' => $c->created_at->toIso8601String(),
                    'source' => 'local',
                ])
                ->toArray();

            // Fetch comments from Zoho Desk if connected
            $zohoComments = [];
            if ($ticket->zoho_ticket_id) {
                $zohoComments = $this->getZohoComments($ticket->zoho_ticket_id);
            }

            // Merge: local comments first, then Zoho comments not already synced
            $localZohoIds = array_filter(array_map(fn($c) => $c['zoho_comment_id'] ?? null, $localComments));
            $uniqueZohoComments = array_filter($zohoComments, fn($c) => !in_array($c['id'] ?? null, $localZohoIds));

            $allComments = array_merge($localComments, array_map(fn($c) => [
                'id' => $c['id'] ?? null,
                'content' => $c['content'] ?? '',
                'author' => $c['author'] ?? 'Support Team',
                'isPublic' => $c['isPublic'] ?? true,
                'created_at' => $c['created_at'] ?? null,
                'source' => 'zoho',
            ], $uniqueZohoComments));

            // Sort by created_at
            usort($allComments, function ($a, $b) {
                return strtotime($a['created_at'] ?? 'now') - strtotime($b['created_at'] ?? 'now');
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Ticket retrieved successfully',
                'data' => [
                    'ticket' => $ticket,
                    'comments' => $allComments,
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

            // Store comment locally
            $authorName = $user->first_name
                ? trim($user->first_name . ' ' . ($user->last_name ?? ''))
                : ($user->name ?? 'User');

            $localComment = TicketComment::create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'content' => $request->comment,
                'author' => $authorName,
                'is_local' => true,
            ]);

            // Also send to Zoho Desk if connected
            if ($ticket->zoho_ticket_id) {
                $this->addZohoComment($ticket->zoho_ticket_id, $request->comment, $user);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Comment added successfully',
                'data' => [
                    'id' => $localComment->id,
                    'content' => $localComment->content,
                    'author' => $localComment->author,
                    'created_at' => $localComment->created_at->toIso8601String(),
                ],
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
            $searchEmail = $user->email;
            $searchPhone = $user->phone_number;

            // Search by email using the search endpoint
            if ($searchEmail) {
                $result = $zohoService->makeRequest('GET', '/contacts/search', ['email' => $searchEmail]);
                if ($result['ok']) {
                    $contacts = $result['data']['data'] ?? $result['data']['contacts'] ?? [];
                    if (!empty($contacts)) {
                        return $contacts[0]['id'];
                    }
                }
            }

            // Search by phone using the search endpoint
            if ($searchPhone) {
                $result = $zohoService->makeRequest('GET', '/contacts/search', ['phone' => $searchPhone]);
                if ($result['ok']) {
                    $contacts = $result['data']['data'] ?? $result['data']['contacts'] ?? [];
                    if (!empty($contacts)) {
                        return $contacts[0]['id'];
                    }
                }
            }

            // Create new contact if not found
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

            Log::info('Zoho Desk comments response', ['ticket_id' => $zohoTicketId, 'result' => $result]);

            if ($result['ok'] && isset($result['data'])) {
                $zohoData = $result['data'];
                $rawComments = $zohoData['data'] ?? $zohoData['comments'] ?? [];

                return array_map(function ($comment) {
                    $rawContent = $comment['content'] ?? '';
                    return [
                        'id' => $comment['id'] ?? null,
                        'content' => $this->stripHtml($rawContent),
                        'author' => $comment['authorName'] ?? $comment['authorId'] ?? 'Support Team',
                        'isPublic' => $comment['isPublic'] ?? true,
                        'created_at' => $comment['createdTime'] ?? $comment['created_time'] ?? null,
                    ];
                }, $rawComments);
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
