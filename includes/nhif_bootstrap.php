<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Africa/Dar_es_Salaam');

require_once dirname(__DIR__) . '/nhif/NhifHttp.php';
require_once dirname(__DIR__) . '/nhif/NhifServiceClient.php';
require_once dirname(__DIR__) . '/nhif/NhifClaimsClient.php';

function nhifConfig(): array
{
    static $config;
    return $config ??= require dirname(__DIR__) . '/config/nhif.php';
}

function nhifDb(): mysqli
{
    static $db;
    return $db ??= require dirname(__DIR__) . '/config/database.php';
}

function nhifCurrentUserId(): ?int
{
    foreach (['user_id', 'id', 'userid'] as $key) {
        if (isset($_SESSION[$key]) && is_numeric($_SESSION[$key])) {
            return (int)$_SESSION[$key];
        }
    }
    return null;
}

function nhifJson(mixed $value): string
{
    return json_encode(
        $value,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
}

function nhifAudit(
    string $department,
    string $action,
    bool $success,
    ?int $patientId = null,
    ?int $visitId = null,
    ?int $httpStatus = null,
    ?string $message = null
): void {
    $db = nhifDb();
    $userId = nhifCurrentUserId();
    $ok = $success ? 1 : 0;

    $stmt = $db->prepare(
        'INSERT INTO nhif_audit_log
        (user_id, department, patient_id, visit_id, action_name, http_status, success, message)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param(
        'isiisiis',
        $userId,
        $department,
        $patientId,
        $visitId,
        $action,
        $httpStatus,
        $ok,
        $message
    );
    $stmt->execute();
}
