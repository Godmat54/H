<?php
declare(strict_types=1);

/**
 * Transitional compatibility for legacy Faraja pages on PHP 8.1.25.
 * Keep this only while old mysql_* calls are being replaced with MySQLi/PDO.
 */

if (!extension_loaded('mysqli')) {
    throw new RuntimeException('mysqli extension is required.');
}

$GLOBALS['__faraja_mysql_link'] = $GLOBALS['__faraja_mysql_link'] ?? null;
$GLOBALS['__faraja_mysql_last_error'] = '';

if (!function_exists('mysql_connect')) {
    function mysql_connect($server = null, $username = null, $password = null, $newLink = false, $clientFlags = 0) {
        $host = $server ?: 'localhost';
        $link = @mysqli_connect($host, $username ?? '', $password ?? '');
        if ($link === false) {
            $GLOBALS['__faraja_mysql_last_error'] = mysqli_connect_error();
            return false;
        }
        mysqli_set_charset($link, 'utf8mb4');
        $GLOBALS['__faraja_mysql_link'] = $link;
        return $link;
    }
}

if (!function_exists('mysql_select_db')) {
    function mysql_select_db($databaseName, $linkIdentifier = null) {
        $link = $linkIdentifier ?: ($GLOBALS['__faraja_mysql_link'] ?? null);
        if (!$link instanceof mysqli) return false;
        return mysqli_select_db($link, $databaseName);
    }
}

if (!function_exists('mysql_query')) {
    function mysql_query($query, $linkIdentifier = null) {
        $link = $linkIdentifier ?: ($GLOBALS['__faraja_mysql_link'] ?? null);
        if (!$link instanceof mysqli) return false;
        $result = @mysqli_query($link, $query);
        if ($result === false) $GLOBALS['__faraja_mysql_last_error'] = mysqli_error($link);
        return $result;
    }
}

if (!function_exists('mysql_fetch_array')) {
    function mysql_fetch_array($result, $resultType = MYSQLI_BOTH) {
        return mysqli_fetch_array($result, $resultType);
    }
}

if (!function_exists('mysql_fetch_assoc')) {
    function mysql_fetch_assoc($result) { return mysqli_fetch_assoc($result); }
}

if (!function_exists('mysql_fetch_row')) {
    function mysql_fetch_row($result) { return mysqli_fetch_row($result); }
}

if (!function_exists('mysql_num_rows')) {
    function mysql_num_rows($result) { return mysqli_num_rows($result); }
}

if (!function_exists('mysql_insert_id')) {
    function mysql_insert_id($linkIdentifier = null) {
        $link = $linkIdentifier ?: ($GLOBALS['__faraja_mysql_link'] ?? null);
        return $link instanceof mysqli ? mysqli_insert_id($link) : 0;
    }
}

if (!function_exists('mysql_real_escape_string')) {
    function mysql_real_escape_string($value, $linkIdentifier = null) {
        $link = $linkIdentifier ?: ($GLOBALS['__faraja_mysql_link'] ?? null);
        return $link instanceof mysqli ? mysqli_real_escape_string($link, $value) : addslashes($value);
    }
}

if (!function_exists('mysql_error')) {
    function mysql_error($linkIdentifier = null) {
        $link = $linkIdentifier ?: ($GLOBALS['__faraja_mysql_link'] ?? null);
        return $link instanceof mysqli ? mysqli_error($link) : ($GLOBALS['__faraja_mysql_last_error'] ?? '');
    }
}

if (!function_exists('mysql_close')) {
    function mysql_close($linkIdentifier = null) {
        $link = $linkIdentifier ?: ($GLOBALS['__faraja_mysql_link'] ?? null);
        return $link instanceof mysqli ? mysqli_close($link) : false;
    }
}

if (!function_exists('get_magic_quotes_gpc')) {
    function get_magic_quotes_gpc() { return false; }
}

if (!function_exists('each')) {
    function each(&$array) {
        $key = key($array);
        if ($key === null) return false;
        $value = current($array);
        next($array);
        return [0 => $key, 1 => $value, 'key' => $key, 'value' => $value];
    }
}