<?php

$autoload = getenv('PARITY_AUTOLOAD') ?: dirname(__DIR__, 2) . '/vendor/autoload.php';
require $autoload;

if (getenv('PARITY_AUTOLOAD')) {
    require_once __DIR__ . '/CollinsCompatibleQueryGrammar.php';
    require_once __DIR__ . '/ParityConnectionResolver.php';
    require_once __DIR__ . '/ParityModel.php';
    require_once __DIR__ . '/ResponseConnection.php';
}

use Illuminate\Container\Container;
use Illuminate\Config\Repository;
use Illuminate\Support\Facades\Facade;

$app = new Container();
Container::setInstance($app);
$app->instance('config', new Repository([
    'app' => ['timezone' => 'UTC'],
    'database' => ['default' => 'elasticsearch', 'connections' => []],
]));
Facade::setFacadeApplication($app);

$cases = require __DIR__ . '/cases.php';
$output = $cases();
unset($output['extended_aggregations']);

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
