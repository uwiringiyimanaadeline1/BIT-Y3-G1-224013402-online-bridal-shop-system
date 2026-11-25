<?php
declare(strict_types=1);

$route=basename((string)($_SERVER['SCRIPT_NAME'] ?? ''),'.php');
$routes=[
    'home','login','register','password-change','dress','dresses','my-rentals',
    'fittings','admin','users','api-search','logout','user-save','user-delete',
    'dress-save','dress-delete','rent','rental-save','rental-update','rental-delete',
    'fitting-save','fitting-delete','rental-payment-update'
];
if (!in_array($route,$routes,true)) {
    http_response_code(404);
    exit('Page not found.');
}

$_GET['page']=$route;
require __DIR__.'/index.php';
