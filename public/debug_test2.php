<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);
set_time_limit(30);
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error) {
        error_log("SHUTDOWN ERROR: " . print_r($error, true));
        echo "FATAL: " . $error['message'] . " in " . $error['file'] . ":" . $error['line'] . "\n";
    }
});

echo "Memory limit: " . ini_get('memory_limit') . "\n";
echo "Starting Laravel with Request::capture()...\n";

define('LARAVEL_START', microtime(true));
require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

echo "Bootstrap done, handling request...\n";
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$request = \Illuminate\Http\Request::capture();

echo "Request: " . $request->method() . " " . $request->getRequestUri() . "\n";
echo "Is Inertia: " . ($request->hasHeader('X-Inertia') ? 'yes' : 'no') . "\n";

try {
    $response = $kernel->handle($request);
    echo "Status: " . $response->getStatusCode() . "\n";
    echo "Content-Length: " . strlen($response->getContent()) . " bytes\n";
    echo "Peak Memory: " . memory_get_peak_usage(true)/1024/1024 . " MB\n";
} catch (\Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo "In: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
