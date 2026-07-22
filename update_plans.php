<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Subscription;

$plans = Subscription::where('role_id', 3)->get();

foreach($plans as $plan) {
    $plan->staff_limit = 5;
    $plan->extra_staff_price = 500;
    $plan->job_limit = 5;
    $plan->extra_job_price = 500;
    $plan->save();
    echo "Updated Plan: {$plan->subscription_name} (Staff Limit: {$plan->staff_limit}, Extra Staff Price: {$plan->extra_staff_price})\n";
}

echo "All owner plans updated successfully.\n";
