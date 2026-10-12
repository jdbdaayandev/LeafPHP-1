<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/autoload.php';
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->runHttp();
