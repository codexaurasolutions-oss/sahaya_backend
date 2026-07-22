<?php

use App\Models\User;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

$user = User::find(1);
if ($user) {
    echo "User found: Name: " . $user->name . ", Code: " . $user->verification_code . "\n";
} else {
    echo "User 1 not found\n";
}
