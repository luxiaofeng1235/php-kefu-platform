#!/usr/bin/env php
<?php
chdir(__DIR__);

$envLoader = __DIR__ . '/config/.env.php';
if (is_file($envLoader)) {
    require_once $envLoader;
}

require_once __DIR__ . '/vendor/autoload.php';
support\App::run();
