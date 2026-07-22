<?php
$request = request()->merge(['dob' => '2030-05-26', 'email' => 'test@example.com']);
$v = Validator::make($request->all(), ['dob' => 'nullable|date', 'email' => 'nullable|email']);
echo "Result for 2030-05-26 (fails?): ";
var_dump($v->fails());

$request2 = request()->merge(['dob' => '05-26-2030', 'email' => 'test@example.com']);
$v2 = Validator::make($request2->all(), ['dob' => 'nullable|date', 'email' => 'nullable|email']);
echo "Result for 05-26-2030 (fails?): ";
var_dump($v2->fails());
