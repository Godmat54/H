<?php
declare(strict_types=1);

use Faraja\Nhif\NhifClient;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('POST required.');
    }

    // Integrate this file with the existing Faraja session/role middleware.
    $cardNo = trim((string)($_POST['card_no'] ?? ''));
    $visitType = (int)($_POST['visit_type_id'] ?? 1);
    $referralNo = trim((string)($_POST['referral_no'] ?? ''));
    $remarks = trim((string)($_POST['remarks'] ?? ''));

    if ($cardNo === '') {
        throw new InvalidArgumentException('NHIF card number is required.');
    }

    $config = require dirname(__DIR__, 2) . '/config/nhif.php';
    $client = new NhifClient($config);
    $result = $client->authorizeMember(
        $cardNo,
        $visitType,
        $referralNo !== '' ? $referralNo : null,
        $remarks
    );

    echo json_encode(['success' => true, 'data' => $result], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
