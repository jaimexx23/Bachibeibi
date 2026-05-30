<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;

$request = Request::create('/api/checkin','POST', ['student_code'=>'202207015','source'=>'manual']);
$response = $app->handle($request);
http_response_code($response->getStatusCode());
echo $response->getContent();
