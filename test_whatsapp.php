<?php
// Test360dialog WhatsApp API directly
$d360ApiKey = 'DIy4T7j4E1lRlC14toeedbViAK';

// Test 1: Indian number (staff_added template)
echo "=== TEST 1: staff_added to Indian number ===\n";
$result = sendTemplate($d360ApiKey, '919876543210', 'staff_added', ['Ahmed Khan']);
echo "Response: $result\n\n";

// Test 2: Pakistani number (staff_added template)
echo "=== TEST 2: staff_added to Pakistani number ===\n";
$result = sendTemplate($d360ApiKey, '923101058254', 'staff_added', ['Ahmed Khan']);
echo "Response: $result\n\n";

// Test 3: kyc_approved (no params)
echo "=== TEST 3: kyc_approved (no params) ===\n";
$result = sendTemplate($d360ApiKey, '919876543210', 'kyc_approved', []);
echo "Response: $result\n\n";

// Test 4: salary_paid
echo "=== TEST 4: salary_paid ===\n";
$result = sendTemplate($d360ApiKey, '919876543210', 'salary_paid', ['50000', 'Admin']);
echo "Response: $result\n\n";

function sendTemplate($apiKey, $phone, $templateName, $params) {
    $components = [];
    if (!empty($params)) {
        $bodyParams = array_map(function($p) {
            return ['type' => 'text', 'text' => (string) $p];
        }, $params);

        $components[] = [
            'type' => 'body',
            'parameters' => $bodyParams,
        ];
    }

    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => $phone,
        'type' => 'template',
        'template' => [
            'name' => $templateName,
            'language' => [
                'code' => 'en_US',
                'policy' => 'deterministic',
            ],
            'components' => $components,
        ],
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://waba-v2.360dialog.io/messages',
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'D360-API-KEY: ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    return "HTTP $httpCode | Error: $error | Body: $response";
}
