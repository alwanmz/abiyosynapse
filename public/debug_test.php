<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);
set_time_limit(30);

// Check memory
echo "Memory limit: " . ini_get('memory_limit') . "\n";
echo "Current memory: " . memory_get_usage(true) / 1024 / 1024 . " MB\n";

// Bootstrap Laravel  
define('LARAVEL_START', microtime(true));
require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

echo "After bootstrap: " . memory_get_usage(true) / 1024 / 1024 . " MB\n";

// Simulate /dashboard request
$request = \Illuminate\Http\Request::create(
    'https://pm.sistemkesehatan.id/dashboard',
    'GET',
    [],  // query
    ['laravel-session' => $_COOKIE['laravel-session'] ?? ''],  // cookies
    [],  // files
    ['HTTP_HOST' => 'pm.sistemkesehatan.id', 'HTTPS' => 'on', 'SERVER_PORT' => '443']
);

$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

echo "Processing dashboard request...\n";
$start = microtime(true);

$response = $kernel->handle($request);

$elapsed = microtime(true) - $start;
echo "Done in " . round($elapsed * 1000) . "ms\n";
echo "Response status: " . $response->getStatusCode() . "\n";
echo "Response size: " . strlen($response->getContent()) . " bytes\n";
echo "Peak memory: " . memory_get_peak_usage(true) / 1024 / 1024 . " MB\n";
