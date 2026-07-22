<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create(
    '/api/profile/update',
    'POST',
    [
        'dob' => '2030-05-26', 
        'join_date' => '26-05-30', 
        'end_date' => '26-05-30', 
        'first_name' => 'Test',
        'last_name' => 'User',
        'gender' => 'male',
    ]
);
// We need to bypass auth or just run the validation directly.
// Actually, updateProfile expects a logged-in user. Let's just mock Auth or test the Validator directly.

use Illuminate\Support\Facades\Validator;
use App\Models\User;

$user = new User();
$user->id = 1;

$validator = Validator::make($request->all(), [
    'first_name' => 'nullable|string|max:255',
    'last_name' => 'nullable|string|max:255',
    'name' => 'nullable|string|max:255',
    'email' => 'nullable|email|unique:users,email,' . $user->id,
    'phone_number' => 'nullable|string|max:20',
    'gender' => 'nullable|string|in:male,female,other',
    'dob' => 'nullable|date',
    'user_role_id' => 'nullable|integer',
    'addresses' => 'nullable|array',
    'addresses.*.street' => 'nullable|string',
    'addresses.*.city' => 'nullable|string',
    'addresses.*.state' => 'nullable|string',
    'addresses.*.pincode' => 'nullable|string',
    'addresses.*.is_primary' => 'nullable|boolean',
    'residence_type' => 'nullable|string|max:255',
    'number_of_rooms' => 'nullable|integer|min:1',
    'adults_count' => 'nullable|integer|min:0',
    'children_count' => 'nullable|integer|min:0',
    'elderly_count' => 'nullable|integer|min:0',
    'special_requirements' => 'nullable|string|max:1000',
    'pet_details' => 'nullable|array',
    'pet_details.*.pet_type' => 'nullable|string|max:255',
    'pet_details.*.pet_count' => 'nullable|integer|min:1',
    'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
    'languages_spoken' => 'nullable|array',
    'auto_attendence' => 'nullable|boolean',
    'upi_id' => 'nullable|string|max:255',
    'emergency_contact_name' => 'nullable|string|max:255',
    'emergency_contact_number' => 'nullable|string|max:255',
    'preferred_work_location' => 'nullable|string|max:255'
]);

if ($validator->fails()) {
    echo "422 Validation Failed:\n";
    print_r($validator->errors()->toArray());
} else {
    echo "200 Validation Passed with YYYY-MM-DD!\n";
}

// Now test with the OLD format that failed (e.g. MM-DD-YYYY or Invalid Date)
$request2 = Illuminate\Http\Request::create(
    '/api/profile/update',
    'POST',
    [
        'dob' => '05-26-2030', // Old React Native JS bug format
    ]
);

$validator2 = Validator::make($request2->all(), [
    'dob' => 'nullable|date',
]);

if ($validator2->fails()) {
    echo "\nOld Format 422 Validation Failed (as expected):\n";
    print_r($validator2->errors()->toArray());
} else {
    echo "\nOld Format Passed\n";
}
