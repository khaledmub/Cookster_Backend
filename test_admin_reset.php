<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $user = \App\Models\User::first();
    if (!$user) { echo "No admin users\n"; exit; }
    echo "Sending reset link to " . $user->email . "\n";
    $token = app(\Illuminate\Auth\Passwords\PasswordBrokerManager::class)->broker()->createToken($user);
    $user->sendPasswordResetNotification($token);
    echo "Reset link sent.\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
