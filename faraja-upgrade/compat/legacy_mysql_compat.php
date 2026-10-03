<?php
declare(strict_types=1);

/**
 * Transitional mysql_* compatibility layer for legacy Faraja pages on PHP 8.5.
 *
 * This is NOT a permanent substitute for converting SQL to prepared MySQLi/PDO.
 * It exists so legacy pages can keep running during staged migration.
 *
 * Load this before old code that calls mysql_connect/mysql_query/etc.
 */

if (!extension_loaded('mysqli')) {
    throw new RuntimeException('The mysqli extension is required.');
}

$GLOBALS['__faraja_mysql_link'] = $GLOBALS['__faraja_mysql_link'] ?? null;
$GLOBALS['__faraja_mysql_last_error'] = '';

if (!function_exists('mysql_connect')) {
    function mysql_connect(
        ?string $server = null,
        ?string $username = null,
        ?string $password = null,
        bool $new_link = false,
        int $client_flags = 0
    ): mysqli|false {
        $host = $server ?: ini_get('mysqli.default_host') ?: 'localhost';
        $user = $username ?? ini_get('mysqli.default_user') ?: '';
        $pass = $password ?? ini_get('mysqli.default_pw') ?: '';

        $link = @mysqli_connect($host, $user, $pass);
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
    function mysql_select_db(string $database_name, mysqli|false|null $link_identifier = null): bool {
        $link = $link_identifier ?: ($GLOBALS['__faraja_mysql_link'] ?? null);
        if (!$link instanceof mysqli) {
            $GLOBALS['__faraja_mysql_last_error'] = 'No MySQL connection.';
            return false;
        }

        $ok = @mysqli_select_db($link, $database_name);
        if (!$ok) {
            $GLOBALS['__faraja_mysql_last_error'] = mysqli_error($link);
        }
        return $ok;
    }
}

if (!function_exists('mysql_query')) {
    function mysql_query(string $query, mysqli|false|null $link_identifier = null): mysqli_result|bool {
        $link = $link_identifier ?: ($GLOBALS['__faraja_mysql_link'] ?? null);
        if (!$link instanceof mysqli) {
            $GLOBALS['__faraja_mysql_last_error'] = 'No MySQL connection.';
            return false;
        }

        $result = @mysqli_query($link, $query);
        if ($result === false) {
            $GLOBALS['__faraja_mysql_last_error'] = mysqli_error($link);
        }
        return $result;
    }
}

if (!function_exists('mysql_fetch_array')) {
    function mysql_fetch_array(mysqli_result $result, int $result_type = MYSQLI_BOTH): array|null|false {
        return mysqli_fetch_array($result, $result_type);
    }
}

if (!function_exists('mysql_fetch_assoc')) {
    function mysql_fetch_assoc(mysqli_result $result): array|null {
        return mysqli_fetch_assoc($result);
    }
}

if (!function_exists('mysql_fetch_row')) {
    function mysql_fetch_row(mysqli_result $result): array|null {
        return mysqli_fetch_row($result);
    }
}

if (!function_exists('mysql_num_rows')) {
    function mysql_num_rows(mysqli_result $result): int {
        return mysqli_num_rows($result);
    }
}

if (!function_exists('mysql_insert_id')) {
    function mysql_insert_id(mysqli|false|null $link_identifier = null): int|string {
        $link = $link_identifier ?: ($GLOBALS['__faraja_mysql_link'] ?? null);
        return $link instanceof mysqli ? mysqli_insert_id($link) : 0;
    }
}

if (!function_exists('mysql_real_escape_string')) {
    function mysql_real_escape_string(string $unescaped_string, mysqli|false|null $link_identifier = null): string {
        $link = $link_identifier ?: ($GLOBALS['__faraja_mysql_link'] ?? null);
        if (!$link instanceof mysqli) {
            return addslashes($unescaped_string);
        }
        return mysqli_real_escape_string($link, $unescaped_string);
    }
}

if (!function_exists('mysql_error')) {
    function mysql_error(mysqli|false|null $link_identifier = null): string {
        $link = $link_identifier ?: ($GLOBALS['__faraja_mysql_link'] ?? null);
        if ($link instanceof mysqli) {
            return mysqli_error($link);
        }
        return (string)($GLOBALS['__faraja_mysql_last_error'] ?? '');
    }
}

if (!function_exists('mysql_close')) {
    function mysql_close(mysqli|false|null $link_identifier = null): bool {
        $link = $link_identifier ?: ($GLOBALS['__faraja_mysql_link'] ?? null);
        if (!$link instanceof mysqli) {
            return false;
        }
        return mysqli_close($link);
    }
}
