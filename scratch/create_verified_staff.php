<?php
$user = new App\Models\User();
$user->name = 'Verified Staff';
$user->first_name = 'Verified';
$user->last_name = 'Staff';
$user->email = 'verified.staff@test.com';
$user->phone_number = '9999999999';
$user->password = Hash::make('password123');
$user->user_role_id = 2;
$user->aadhar__verify = true;
$user->aadhar_number = '123412341234';
$user->status = 'active';
$user->save();
echo 'Created Staff ID: ' . $user->id;
