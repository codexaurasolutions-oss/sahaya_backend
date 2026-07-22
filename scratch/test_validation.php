<?php
// Mock Laravel environment for quick validation testing
require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

// Simulate updateStaff validation logic
function testValidation($data, $id = 1) {
    $rules = [
        'first_name' => 'sometimes|required|string|max:255',
        'last_name' => 'sometimes|required|string|max:255',
        'email' => 'nullable|required|email|unique:users,email,' . $id,
        'phone_number' => 'sometimes|required|string|max:15|unique:users,phone_number,' . $id,
        'gender' => 'sometimes|required|in:male,female,other',
        'dob' => 'sometimes|required|date',
        
        // Address fields
        'street' => 'sometimes|required|string|max:255',
        'city' => 'sometimes|required|string|max:255',
        'state' => 'sometimes|required|string|max:255',
        'pincode' => 'sometimes|required|string|max:10',
        
        // Work details
        'role_designation' => 'sometimes|required|string|max:255',
        'joining_date' => 'sometimes|required|date',
        'salary' => 'sometimes|required|numeric',
        'pay_frequency' => 'sometimes|required|in:weekly,monthly,bi-weekly',
        
        // Emergency contact
        'emergency_contact_name' => 'sometimes|required|string|max:255',
        'emergency_contact_number' => 'sometimes|required|string|max:15',
        
        'aadhar_number' => 'sometimes|required|string|max:12|unique:users,aadhar_number,' . $id,
        'upi_id' => 'nullable|string|max:255',
        'staff_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
        
        // Private Documents
        'employer_aadhar_front' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
        'employer_aadhar_back' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
        'employer_police_verification' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
        'employer_other_doc' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
        'fir_document' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
    ];

    // Note: We can't easily run real Validator without full Laravel boot, 
    // but we can see that 'email' => 'nullable|required' will fail if email is missing.
    // because 'required' means "field must be present and not empty".
    
    if (!isset($data['email'])) {
        echo "FAIL: 'email' is required but missing from request!\n";
    } else {
        echo "PASS: 'email' is present.\n";
    }
}

echo "Testing upload with ONLY employer_aadhar_front...\n";
testValidation(['employer_aadhar_front' => 'mock_file_object']);
