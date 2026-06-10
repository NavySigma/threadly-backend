<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$username = $argv[1] ?? 'testposter';
$points = (int)($argv[2] ?? 15);

Illuminate\Support\Facades\DB::table('users')
    ->where('username', $username)
    ->update(['reputation_points' => $points]);

echo "OK:$username:$points\n";
