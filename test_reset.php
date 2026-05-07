<?php
$ch = curl_init('http://localhost/api/forgot_password/verify_email');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'email' => 'test@example.com',
    'medium' => 1
]));
$response = curl_exec($ch);
echo "Response: $response\n";
curl_close($ch);
