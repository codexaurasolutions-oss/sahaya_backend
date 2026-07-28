<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ZohoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ZohoController extends Controller
{
    // ═══════════════════════════════════════════════════════════════════
    // AUTH STATUS
    // ═══════════════════════════════════════════════════════════════════

    public function authStatus(): JsonResponse
    {
        try {
            $crmService = new ZohoService('crm');
            $deskService = new ZohoService('desk');

            return response()->json([
                'success' => true,
                'data' => [
                    'crm' => ['authorized' => $crmService->isAuthorized()],
                    'desk' => ['authorized' => $deskService->isAuthorized()],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // OAUTH - GET AUTHORIZATION URL
    // ═══════════════════════════════════════════════════════════════════

    public function getAuthUrl(Request $request): JsonResponse
    {
        $service = $request->get('service', 'crm');

        if (!in_array($service, ['crm', 'desk'])) {
            return response()->json(['success' => false, 'message' => 'Invalid service'], 422);
        }

        try {
            $zohoService = new ZohoService($service);
            $url = $zohoService->getAuthorizationUrl();

            return response()->json([
                'success' => true,
                'data' => ['url' => $url],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // OAUTH CALLBACK (exchanges code for tokens)
    // ═══════════════════════════════════════════════════════════════════

    public function oauthCallback(Request $request)
    {
        $code = $request->get('code');
        $state = $request->get('state', 'crm');

        if (!$code) {
            return response()->view('zoho.callback', [
                'success' => false,
                'service' => $state,
                'message' => 'Authorization code is missing. Please try connecting again.',
            ]);
        }

        try {
            $zohoService = new ZohoService($state);
            $tokens = $zohoService->exchangeCodeForTokens($code);

            return response()->view('zoho.callback', [
                'success' => true,
                'service' => $state,
                'message' => ucfirst($state) . ' connected successfully! You can close this window and return to the admin panel.',
            ]);
        } catch (\Exception $e) {
            Log::error("Zoho {$state} OAuth callback failed", ['error' => $e->getMessage()]);
            return response()->view('zoho.callback', [
                'success' => false,
                'service' => $state,
                'message' => 'Failed to connect: ' . $e->getMessage(),
            ]);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // ZOHO CRM - LEADS
    // ═══════════════════════════════════════════════════════════════════

    public function getLeads(Request $request): JsonResponse
    {
        try {
            $zohoService = new ZohoService('crm');
            $params = http_build_query(array_filter([
                'page' => $request->get('page', 1),
                'per_page' => $request->get('per_page', 200),
                'sort_by' => $request->get('sort_by'),
                'sort_order' => $request->get('sort_order'),
            ]));

            $result = $zohoService->makeRequest('GET', "/Leads?{$params}");

            return response()->json([
                'success' => $result['ok'],
                'data' => $result['data'],
            ], $result['status']);
        } catch (\Throwable $e) {
            Log::error("Zoho CRM getLeads failed", ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function createLead(Request $request): JsonResponse
    {
        $request->validate([
            'Last_Name' => 'required|string',
            'Email' => 'nullable|email',
            'Phone' => 'nullable|string',
            'Company' => 'nullable|string',
        ]);

        try {
            $zohoService = new ZohoService('crm');
            $result = $zohoService->makeRequest('POST', '/Leads', [
                'data' => [$request->only(['First_Name', 'Last_Name', 'Email', 'Phone', 'Company', 'Lead_Source', 'Lead_Status', 'Description'])],
            ]);

            return response()->json([
                'success' => $result['ok'],
                'data' => $result['data'],
            ], $result['status']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function searchLeads(Request $request): JsonResponse
    {
        $criteria = $request->get('criteria');
        if (!$criteria) {
            return response()->json(['success' => false, 'message' => 'Search criteria is required'], 422);
        }

        try {
            $zohoService = new ZohoService('crm');
            $encoded = urlencode($criteria);
            $result = $zohoService->makeRequest('GET', "/Leads/search?criteria={$encoded}");

            return response()->json([
                'success' => $result['ok'],
                'data' => $result['data'],
            ], $result['status']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // ZOHO CRM - CONTACTS
    // ═══════════════════════════════════════════════════════════════════

    public function getContacts(Request $request): JsonResponse
    {
        try {
            $zohoService = new ZohoService('crm');
            $params = http_build_query(array_filter([
                'page' => $request->get('page', 1),
                'per_page' => $request->get('per_page', 200),
            ]));

            $result = $zohoService->makeRequest('GET', "/Contacts?{$params}");

            return response()->json([
                'success' => $result['ok'],
                'data' => $result['data'],
            ], $result['status']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function createContact(Request $request): JsonResponse
    {
        $request->validate([
            'Last_Name' => 'required|string',
            'Email' => 'nullable|email',
            'Phone' => 'nullable|string',
        ]);

        try {
            $zohoService = new ZohoService('crm');
            $result = $zohoService->makeRequest('POST', '/Contacts', [
                'data' => [$request->only(['First_Name', 'Last_Name', 'Email', 'Phone', 'Account_Name', 'Description'])],
            ]);

            return response()->json([
                'success' => $result['ok'],
                'data' => $result['data'],
            ], $result['status']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // ZOHO CRM - DEALS
    // ═══════════════════════════════════════════════════════════════════

    public function getDeals(Request $request): JsonResponse
    {
        try {
            $zohoService = new ZohoService('crm');
            $params = http_build_query(array_filter([
                'page' => $request->get('page', 1),
                'per_page' => $request->get('per_page', 200),
            ]));

            $result = $zohoService->makeRequest('GET', "/Deals?{$params}");

            return response()->json([
                'success' => $result['ok'],
                'data' => $result['data'],
            ], $result['status']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function createDeal(Request $request): JsonResponse
    {
        $request->validate([
            'Deal_Name' => 'required|string',
            'Amount' => 'nullable|numeric',
        ]);

        try {
            $zohoService = new ZohoService('crm');
            $result = $zohoService->makeRequest('POST', '/Deals', [
                'data' => [$request->only(['Deal_Name', 'Amount', 'Stage', 'Closing_Date', 'Contact_Name', 'Description'])],
            ]);

            return response()->json([
                'success' => $result['ok'],
                'data' => $result['data'],
            ], $result['status']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // ZOHO CRM - MODULES METADATA (fields, layouts etc.)
    // ═══════════════════════════════════════════════════════════════════

    public function getCrmModules(): JsonResponse
    {
        try {
            $zohoService = new ZohoService('crm');
            $result = $zohoService->makeRequest('GET', '/modules');

            return response()->json([
                'success' => $result['ok'],
                'data' => $result['data'],
            ], $result['status']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getCrmModulesSummary(): JsonResponse
    {
        try {
            $crmService = new ZohoService('crm');

            $leads = $crmService->makeRequest('GET', '/Leads?per_page=1');
            $contacts = $crmService->makeRequest('GET', '/Contacts?per_page=1');
            $deals = $crmService->makeRequest('GET', '/Deals?per_page=1');

            $leadCount = $leads['ok'] ? ($leads['data']['info']['total_count'] ?? 0) : 0;
            $contactCount = $contacts['ok'] ? ($contacts['data']['info']['total_count'] ?? 0) : 0;
            $dealCount = $deals['ok'] ? ($deals['data']['info']['total_count'] ?? 0) : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'leads' => $leadCount,
                    'contacts' => $contactCount,
                    'deals' => $dealCount,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error("Zoho CRM summary failed", ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // ZOHO DESK - TICKETS
    // ═══════════════════════════════════════════════════════════════════

    public function getTickets(Request $request): JsonResponse
    {
        try {
            $zohoService = new ZohoService('desk');
            $params = http_build_query(array_filter([
                'from' => $request->get('from', 1),
                'limit' => $request->get('limit', 50),
                'status' => $request->get('status'),
                'priority' => $request->get('priority'),
                'assigneeId' => $request->get('assignee_id'),
                'departmentId' => $request->get('department_id'),
            ]));

            $result = $zohoService->makeRequest('GET', "/tickets?{$params}");

            return response()->json([
                'success' => $result['ok'],
                'data' => $result['data'],
            ], $result['status']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getTicket(string $id): JsonResponse
    {
        try {
            $zohoService = new ZohoService('desk');
            $result = $zohoService->makeRequest('GET', "/tickets/{$id}?include=contacts,departments,assignee");

            return response()->json([
                'success' => $result['ok'],
                'data' => $result['data'],
            ], $result['status']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function createTicket(Request $request): JsonResponse
    {
        $request->validate([
            'subject' => 'required|string',
            'departmentId' => 'required|string',
        ]);

        try {
            $zohoService = new ZohoService('desk');
            $result = $zohoService->makeRequest('POST', '/tickets', [
                'subject' => $request->subject,
                'description' => $request->get('description', ''),
                'departmentId' => $request->departmentId,
                'priority' => $request->get('priority', 'Medium'),
                'status' => $request->get('status', 'Open'),
                'contactId' => $request->get('contact_id'),
                'assigneeId' => $request->get('assignee_id'),
                'channel' => $request->get('channel', 'API'),
            ]);

            return response()->json([
                'success' => $result['ok'],
                'data' => $result['data'],
            ], $result['status']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateTicket(Request $request, string $id): JsonResponse
    {
        try {
            $zohoService = new ZohoService('desk');
            $fields = $request->only([
                'subject', 'description', 'status', 'priority',
                'assigneeId', 'departmentId', 'contactId',
            ]);

            $result = $zohoService->makeRequest('PUT', "/tickets/{$id}", $fields);

            return response()->json([
                'success' => $result['ok'],
                'data' => $result['data'],
            ], $result['status']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function addTicketComment(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        try {
            $zohoService = new ZohoService('desk');
            $result = $zohoService->makeRequest('POST', "/tickets/{$id}/comments", [
                'content' => $request->content,
                'isPublic' => $request->get('is_public', true),
            ]);

            return response()->json([
                'success' => $result['ok'],
                'data' => $result['data'],
            ], $result['status']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // ZOHO DESK - DEPARTMENTS, AGENTS, CONTACTS
    // ═══════════════════════════════════════════════════════════════════

    public function getDepartments(): JsonResponse
    {
        try {
            $zohoService = new ZohoService('desk');
            $result = $zohoService->makeRequest('GET', '/departments');

            return response()->json([
                'success' => $result['ok'],
                'data' => $result['data'],
            ], $result['status']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getAgents(): JsonResponse
    {
        try {
            $zohoService = new ZohoService('desk');
            $result = $zohoService->makeRequest('GET', '/agents');

            return response()->json([
                'success' => $result['ok'],
                'data' => $result['data'],
            ], $result['status']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getDeskContacts(Request $request): JsonResponse
    {
        try {
            $zohoService = new ZohoService('desk');
            $params = http_build_query(array_filter([
                'from' => $request->get('from', 1),
                'limit' => $request->get('limit', 50),
            ]));

            $result = $zohoService->makeRequest('GET', "/contacts?{$params}");

            return response()->json([
                'success' => $result['ok'],
                'data' => $result['data'],
            ], $result['status']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getTicketCounts(): JsonResponse
    {
        try {
            $zohoService = new ZohoService('desk');

            // Single call for all tickets — count client-side
            $result = $zohoService->makeRequest('GET', '/tickets?limit=100');
            $tickets = $result['data']['data'] ?? [];
            $totalCount = $result['data']['info']['total_count'] ?? 0;

            $open = 0;
            $inProgress = 0;
            $closed = 0;

            foreach ($tickets as $t) {
                $status = $t['status'] ?? '';
                if ($status === 'Open') $open++;
                elseif ($status === 'In Progress') $inProgress++;
                elseif ($status === 'Closed') $closed++;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'open' => $open,
                    'in_progress' => $inProgress,
                    'closed' => $closed,
                    'total' => $totalCount,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // PUSH SAHAYYA DATA TO ZOHO CRM
    // ═══════════════════════════════════════════════════════════════════

    public function syncStaffToCrm(Request $request): JsonResponse
    {
        try {
            $zohoService = new ZohoService('crm');
            $staff = \App\Models\User::where('user_role_id', 2)
                ->where('is_deleted', 0)
                ->where('is_staff_added', 1)
                ->select('id', 'first_name', 'last_name', 'email', 'phone_number', 'phone_number_country_code')
                ->limit($request->get('limit', 50))
                ->get();

            $synced = 0;
            $errors = 0;

            foreach ($staff as $member) {
                try {
                    $phone = $member->phone_number_country_code . $member->phone_number;
                    $result = $zohoService->makeRequest('POST', '/Leads/upsert', [
                        'data' => [[
                            'First_Name' => $member->first_name,
                            'Last_Name' => $member->last_name,
                            'Email' => $member->email,
                            'Phone' => $phone,
                            'Lead_Source' => 'Sahayya App',
                            'Description' => "Staff member synced from Sahayya (User ID: {$member->id})",
                        ]],
                        'trigger' => ['approval', 'workflow', 'blueprint'],
                    ]);

                    if ($result['ok']) {
                        $synced++;
                    } else {
                        $errors++;
                        Log::warning("Zoho CRM sync failed for staff {$member->id}", $result['data']);
                    }
                } catch (\Exception $e) {
                    $errors++;
                    Log::error("Zoho CRM sync exception for staff {$member->id}", ['error' => $e->getMessage()]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Synced {$synced} staff, {$errors} errors",
                'data' => ['synced' => $synced, 'errors' => $errors, 'total' => $staff->count()],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function syncOwnersToCrm(Request $request): JsonResponse
    {
        try {
            $zohoService = new ZohoService('crm');
            $owners = \App\Models\User::where('user_role_id', 3)
                ->where('is_deleted', 0)
                ->select('id', 'first_name', 'last_name', 'email', 'phone_number', 'phone_number_country_code')
                ->limit($request->get('limit', 50))
                ->get();

            $synced = 0;
            $errors = 0;

            foreach ($owners as $owner) {
                try {
                    $phone = $owner->phone_number_country_code . $owner->phone_number;
                    $result = $zohoService->makeRequest('POST', '/Contacts/upsert', [
                        'data' => [[
                            'First_Name' => $owner->first_name,
                            'Last_Name' => $owner->last_name,
                            'Email' => $owner->email,
                            'Phone' => $phone,
                            'Lead_Source' => 'Sahayya App',
                            'Description' => "House owner synced from Sahayya (User ID: {$owner->id})",
                        ]],
                        'trigger' => ['approval', 'workflow', 'blueprint'],
                    ]);

                    if ($result['ok']) {
                        $synced++;
                    } else {
                        $errors++;
                    }
                } catch (\Exception $e) {
                    $errors++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Synced {$synced} owners, {$errors} errors",
                'data' => ['synced' => $synced, 'errors' => $errors, 'total' => $owners->count()],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // DEBUG - Test Zoho connection
    // ═══════════════════════════════════════════════════════════════════

    public function debugZoho(): JsonResponse
    {
        $results = [];

        try {
            $crmService = new ZohoService('crm');
            $results['crm_config'] = [
                'client_id' => config('zoho.crm.client_id') ? 'set' : 'MISSING',
                'client_secret' => config('zoho.crm.client_secret') ? 'set' : 'MISSING',
                'data_center' => config('zoho.crm.data_center'),
            ];
            $results['crm_authorized'] = $crmService->isAuthorized();

            $token = \App\Models\ZohoToken::where('service', 'crm')->first();
            $results['crm_token'] = $token ? [
                'has_refresh' => !empty($token->refresh_token),
                'has_access' => !empty($token->access_token),
                'expires' => $token->token_expires_at?->toISOString(),
                'is_expired' => $token->token_expires_at ? $token->token_expires_at->isPast() : 'unknown',
            ] : 'no token record';

            if ($results['crm_authorized']) {
                try {
                    $accessToken = $crmService->getAccessToken();
                    $results['crm_access_token'] = substr($accessToken, 0, 10) . '...';
                } catch (\Throwable $e) {
                    $results['crm_token_error'] = $e->getMessage();
                }
            }
        } catch (\Throwable $e) {
            $results['crm_init_error'] = $e->getMessage();
        }

        try {
            $deskService = new ZohoService('desk');
            $results['desk_config'] = [
                'client_id' => config('zoho.desk.client_id') ? 'set' : 'MISSING',
                'client_secret' => config('zoho.desk.client_secret') ? 'set' : 'MISSING',
                'org_id' => config('zoho.desk.org_id') ?: 'MISSING',
                'data_center' => config('zoho.desk.data_center'),
            ];
            $results['desk_authorized'] = $deskService->isAuthorized();

            $token = \App\Models\ZohoToken::where('service', 'desk')->first();
            $results['desk_token'] = $token ? [
                'has_refresh' => !empty($token->refresh_token),
                'has_access' => !empty($token->access_token),
                'expires' => $token->token_expires_at?->toISOString(),
                'is_expired' => $token->token_expires_at ? $token->token_expires_at->isPast() : 'unknown',
            ] : 'no token record';

            if ($results['desk_authorized']) {
                try {
                    $accessToken = $deskService->getAccessToken();
                    $results['desk_access_token'] = substr($accessToken, 0, 10) . '...';
                } catch (\Throwable $e) {
                    $results['desk_token_error'] = $e->getMessage();
                }
            }
        } catch (\Throwable $e) {
            $results['desk_init_error'] = $e->getMessage();
        }

        // Test actual API calls
        try {
            $crmService = new ZohoService('crm');
            $leadsResult = $crmService->makeRequest('GET', '/Leads?per_page=1');
            $results['crm_leads_test'] = ['status' => $leadsResult['status'], 'ok' => $leadsResult['ok'], 'data' => $leadsResult['data']];
        } catch (\Throwable $e) {
            $results['crm_leads_error'] = $e->getMessage();
        }

        try {
            $deskService = new ZohoService('desk');
            $ticketsResult = $deskService->makeRequest('GET', '/tickets?from=1&limit=1');
            $results['desk_tickets_test'] = ['status' => $ticketsResult['status'], 'ok' => $ticketsResult['ok'], 'data' => $ticketsResult['data']];
        } catch (\Throwable $e) {
            $results['desk_tickets_error'] = $e->getMessage();
        }

        return response()->json(['success' => true, 'data' => $results]);
    }
}
