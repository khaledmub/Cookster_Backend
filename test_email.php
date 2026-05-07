<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    Mail::raw('Test email', function($msg) {
        $msg->to('test@example.com')->subject('Test Email');
    });
    echo "Email sent successfully.\n";
} catch (\Exception $e) {
    echo "Error sending email: " . $e->getMessage() . "\n";
}
