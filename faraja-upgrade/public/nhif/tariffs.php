<?php
declare(strict_types=1);

use Faraja\Nhif\NhifClient;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $config = require dirname(__DIR__, 2) . '/config/nhif.php';
    $client = new NhifClient($config);
    $result = $client->getTariffs(true);

    echo json_encode(['success' => true, 'data' => $result], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
