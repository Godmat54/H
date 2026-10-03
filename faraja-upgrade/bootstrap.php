<?php
declare(strict_types=1);

/**
 * Faraja PHP 8.5 bootstrap.
 *
 * Add this once in the system's shared include/config file.
 * It preserves legacy pages while newer code uses modern MySQLi/PDO.
 */

error_reporting(E_ALL);
ini_set('display_errors', getenv('APP_ENV') === 'production' ? '0' : '1');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Africa/Dar_es_Salaam');

require_once __DIR__ . '/compat/legacy_mysql_compat.php';

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}
