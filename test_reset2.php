<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $user = \App\Models\FrontUser::first();
    if (!$user) { echo "No users\n"; exit; }
    echo "Sending to " . $user->email . "\n";
    $res = \App\Helpers\AppHelper::send_verification_code(1, $user);
    print_r($res);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
