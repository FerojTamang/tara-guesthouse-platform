<?php

declare(strict_types=1);

$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';

if (!is_file($autoloadPath)) {
    throw new RuntimeException('Composer dependencies are missing. Run composer install before executing tests.');
}

require $autoloadPath;

date_default_timezone_set('Asia/Kathmandu');

$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';
putenv('APP_ENV=testing');

$_ENV['APP_DEBUG'] = 'false';
$_SERVER['APP_DEBUG'] = 'false';
putenv('APP_DEBUG=false');
