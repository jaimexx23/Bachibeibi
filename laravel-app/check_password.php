<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
// Bootstrap the application
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
$student = DB::table('students')->where('id', 3)->first();
$pw = getenv('DEFAULT_STUDENT_PASSWORD') ?: 'alumno123';
if (! $student) {
    echo "no student\n";
    exit(1);
}
$match = Hash::check($pw, $student->password_hash);
echo $match ? "match\n" : "no-match\n";
