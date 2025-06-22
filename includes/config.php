<?php
$env = parse_ini_file(__DIR__ . '/../.env', true);

define('WHATTOMINE_URL', $env['WHATTOMINE_URL']);
define('API_WHATTOMINE_URL', $env['API_WHATTOMINE_URL']);
define('API_NICEHASH_URL', $env['API_NICEHASH_URL']);
define('AFFILIATE_AMAZON', $env['AFFILIATE_AMAZON']);
define('AFFILIATE_NEWEGG', $env['AFFILIATE_NEWEGG']);
define('PUBLIC_DIR', __DIR__ . '/../public');
define('CONFIG_DIR', __DIR__ . '/../config');
?>