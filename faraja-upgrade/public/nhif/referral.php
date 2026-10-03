<?php
declare(strict_types=1);

use Faraja\Nhif\NhifClient;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('POST required.');
    }

    $payload = [
        'CardNo' => trim((string)($_POST['card_no'] ?? '')),
        'AuthorizationNo' => trim((string)($_POST['authorization_no'] ?? '')),
        'PatientFullName' => trim((string)($_POST['patient_full_name'] ?? '')),
        'PhysicianMobileNo' => trim((string)($_POST['physician_mobile_no'] ?? '')),
        'Gender' => trim((string)($_POST['gender'] ?? '')),
        'PhysicianName' => trim((string)($_POST['physician_name'] ?? '')),
        'PhysicianQualificationID' => (int)($_POST['physician_qualification_id'] ?? 0),
        'ServiceIssuingFacilityCode' => trim((string)($_POST['service_issuing_facility_code'] ?? '')),
        'ReferringDiagnosis' => trim((string)($_POST['referring_diagnosis'] ?? '')),
        'ReasonsForReferral' => trim((string)($_POST['reasons_for_referral'] ?? '')),
    ];

    foreach (['CardNo','AuthorizationNo','PatientFullName','PhysicianName',
              'ServiceIssuingFacilityCode','ReferringDiagnosis','ReasonsForReferral'] as $key) {
        if ($payload[$key] === '') {
            throw new InvalidArgumentException("{$key} is required.");
        }
    }

    if ($payload['PhysicianQualificationID'] < 1 || $payload['PhysicianQualificationID'] > 7) {
        throw new InvalidArgumentException('PhysicianQualificationID must be from 1 to 7.');
    }

    $config = require dirname(__DIR__, 2) . '/config/nhif.php';
    $client = new NhifClient($config);
    $result = $client->referPatient($payload);

    echo json_encode(['success' => true, 'data' => $result], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
