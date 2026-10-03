<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/NhifWorkflow.php';

$patientId = (int)($_GET['patient_id'] ?? $_POST['patient_id'] ?? 0);
$visitId = (int)($_GET['visit_id'] ?? $_POST['visit_id'] ?? 0);
$paymentMode = (string)($_GET['payment_mode'] ?? $_POST['payment_mode'] ?? 'NHIF');

if ($patientId < 1 || $visitId < 1) {
    http_response_code(422);
    exit('Patient ID and Visit ID are required.');
}

if (!nhifStartWorkflow($patientId, $visitId, $paymentMode)) {
    http_response_code(422);
    exit('Payment mode is not NHIF.');
}

header(
    'Location: nhif_verify.php?patient_id=' . rawurlencode((string)$patientId)
    . '&visit_id=' . rawurlencode((string)$visitId)
);
exit;
