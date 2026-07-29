<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Create test user
$user = User::firstOrCreate(
    ['phone_number' => '9999988888'],
    [
        'name' => 'Ankit',
        'first_name' => 'Ankit',
        'last_name' => 'Test',
        'email' => 'ankit.test.ticket@sahayya.co.in',
        'phone_number_prefix' => '+91',
        'phone_number_country_code' => 'IN',
        'password' => Hash::make('Test@123456'),
        'is_verified' => 1,
        'is_active' => 1,
        'user_role_id' => 3,
    ]
);

echo "User: {$user->name} (ID: {$user->id})\n";

// Create ticket
$ticket = Ticket::create([
    'user_id' => $user->id,
    'subject' => 'Test Support Ticket - Ankit',
    'description' => "This is a test support ticket created by Ankit to verify Zoho Desk integration.\n\nPlease ignore this ticket. It is for testing purposes only.",
    'category' => 'General',
    'priority' => 'Medium',
    'status' => 'Open',
]);

echo "Ticket created! ID: {$ticket->id}\n";
echo "Subject: {$ticket->subject}\n";
echo "Status: {$ticket->status}\n";

echo "\n--- Zoho Desk sync ---\n";
try {
    $zohoService = new \App\Services\ZohoService('desk');
    if ($zohoService->isAuthorized()) {
        echo "Zoho Desk authorized! Creating ticket on Zoho...\n";
        $deptResult = $zohoService->makeRequest('GET', '/departments');
        
        $departmentId = null;
        if ($deptResult['ok'] && !empty($deptResult['data']['departments'])) {
            $departmentId = $deptResult['data']['departments'][0]['id'];
            echo "Department: {$departmentId}\n";
        }

        if ($departmentId) {
            $body = "Category: {$ticket->category}\nPriority: {$ticket->priority}\n\n{$ticket->description}";
            $body .= "\n\nUser: {$user->name} ({$user->email})";
            $body .= "\nUser Phone: {$user->phone_number}";

            $result = $zohoService->makeRequest('POST', '/tickets', [
                'subject' => $ticket->subject,
                'description' => $body,
                'departmentId' => $departmentId,
                'priority' => $ticket->priority,
                'status' => 'Open',
                'channel' => 'Sahayya App',
            ]);

            echo "Zoho response: " . json_encode($result) . "\n";

            if ($result['ok'] && isset($result['data']['id'])) {
                $ticket->update(['zoho_ticket_id' => $result['data']['id']]);
                echo "SUCCESS! Zoho Desk ticket ID: {$result['data']['id']}\n";
            } else {
                echo "FAILED to create Zoho Desk ticket\n";
            }
        } else {
            echo "No departments found\n";
        }
    } else {
        echo "Zoho Desk NOT authorized\n";
        echo "Check ZOHO_DESK_CLIENT_ID, ZOHO_DESK_CLIENT_SECRET, ZOHO_DESK_REFRESH_TOKEN in .env\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nDone!\n";
