<?php
declare(strict_types=1);

use Faraja\Nhif\ClaimBuilder;
use Faraja\Nhif\NhifClient;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('POST required.');
    }

    $input = json_decode(file_get_contents('php://input') ?: '', true, 512, JSON_THROW_ON_ERROR);
    $folio = (array)($input['folio'] ?? []);
    $diseases = (array)($input['diseases'] ?? []);
    $items = (array)($input['items'] ?? []);

    if (!empty($input['patient_pdf_path'])) {
        $folio['PatientFile'] = ClaimBuilder::encodePatientPdf((string)$input['patient_pdf_path']);
    }

    $claim = ClaimBuilder::build($folio, $diseases, $items);
    $config = require dirname(__DIR__, 2) . '/config/nhif.php';
    $client = new NhifClient($config);
    $result = $client->submitFolios([$claim]);

    echo json_encode(['success' => true, 'data' => $result], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
