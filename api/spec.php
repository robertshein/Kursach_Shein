<?php
header('Content-Type: application/json; charset=utf-8');

$spec = json_decode(file_get_contents(__DIR__ . '/openapi.json'), true);

$proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base  = $proto . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

$spec['servers'] = [
    ['url' => $base . '/index.php', 'description' => 'PATH_INFO'],
    ['url' => $base, 'description' => 'Clean URLs'],
];

echo json_encode($spec, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
