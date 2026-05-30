<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
echo 'default=' . $app->make('config')->get('database.default') . PHP_EOL;
$conn = $app->make('config')->get('database.connections.' . $app->make('config')->get('database.default'));
echo 'driver=' . ($conn['driver'] ?? '') . ' host=' . ($conn['host'] ?? '') . ' database=' . ($conn['database'] ?? '') . ' user=' . ($conn['username'] ?? '') . PHP_EOL;
