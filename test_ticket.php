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
    ['email' => 'ankit.test@sahayya.co.in'],
    [
        'name' => 'Ankit',
        'first_name' => 'Ankit',
        'last_name' => 'Test',
        'phone_number' => '9876543210',
        'phone_number_prefix' => '+91',
        'phone_number_country_code' => 'IN',
        'password' => Hash::make('Test@123456'),
        'is_verified' => 1,
        'is_active' => 1,
        'user_role_id' => 2,
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
echo "User: {$user->name}\n";

echo "\n--- Attempting Zoho Desk sync ---\n";
try {
    $zohoService = new \App\Services\ZohoService('desk');
    if ($zohoService->isAuthorized()) {
        echo "Zoho Desk authorized! Syncing...\n";
        $deptResult = $zohoService->makeRequest('GET', '/departments');
        echo "Departments: " . json_encode($deptResult) . "\n";
    } else {
        echo "Zoho Desk NOT authorized. ZOHO_DESK_REFRESH_TOKEN not set in .env\n";
        echo "Ticket saved in local DB only.\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nDone!\n";
