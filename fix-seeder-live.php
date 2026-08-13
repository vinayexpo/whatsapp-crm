<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<pre>";

$controllerPath = __DIR__.'/../app/Http/Controllers/Api/V1/CompanyController.php';
echo "=== CompanyController.php on live server ===\n\n";
echo htmlspecialchars(file_get_contents($controllerPath));

echo "\n\n=== ApiConnectionsSeeder.php on live server ===\n\n";
$seederPath = __DIR__.'/../database/seeders/ApiConnectionsSeeder.php';
echo htmlspecialchars(file_get_contents($seederPath));

echo "</pre>";
