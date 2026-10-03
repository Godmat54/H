<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/nhif_bootstrap.php';

$patientId = (int)($_POST['patient_id'] ?? $_GET['patient_id'] ?? 0);
$visitId = (int)($_POST['visit_id'] ?? $_GET['visit_id'] ?? 0);
$message = '';
$data = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $cardNo = trim((string)($_POST['card_no'] ?? ''));
        $referenceNo = trim((string)($_POST['reference_no'] ?? ''));
        $itemCode = trim((string)($_POST['item_code'] ?? ''));

        if ($cardNo === '' || $referenceNo === '' || $itemCode === '') {
            throw new InvalidArgumentException('Card number, reference number and item code are required.');
        }

        $client = new NhifServiceClient(nhifConfig());
        $response = $client->verifyPreApproval($cardNo, $referenceNo, $itemCode);
        $data = $response['json'] ?? [];
        $status = strtoupper((string)($data['Status'] ?? ''));

        $db = nhifDb();
        $json = nhifJson($data);
        $userId = nhifCurrentUserId();
        $stmt = $db->prepare(
            'INSERT INTO nhif_preapprovals
            (patient_id, visit_id, card_no, reference_no, item_code, status, response_json, checked_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'iisssssi',
            $patientId, $visitId, $cardNo, $referenceNo, $itemCode, $status, $json, $userId
        );
        $stmt->execute();

        $valid = $status === 'VALID';
        $message = $valid ? 'Pre-approval is VALID.' : 'Pre-approval is not VALID.';
        nhifAudit('Doctor', 'GetReferenceNoStatus', $valid, $patientId, $visitId,
            (int)$response['status'], $message);
    } catch (Throwable $e) {
        $message = $e->getMessage();
        nhifAudit('Doctor', 'GetReferenceNoStatus', false, $patientId ?: null,
            $visitId ?: null, null, $message);
    }
}
?>
<div style="min-width:500px">
<h3>NHIF Pre-Approval Verification</h3>
<?php if ($message): ?><p><?= htmlspecialchars($message) ?></p><?php endif; ?>
<form method="post">
<input type="hidden" name="patient_id" value="<?= $patientId ?>">
<input type="hidden" name="visit_id" value="<?= $visitId ?>">
<p><label>Card Number<br><input name="card_no" required style="width:100%"></label></p>
<p><label>Approval Reference No.<br><input name="reference_no" required style="width:100%"></label></p>
<p><label>NHIF Item Code<br><input name="item_code" required style="width:100%"></label></p>
<button type="submit">Verify Approval</button>
</form>
<?php if ($data): ?><pre><?= htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)) ?></pre><?php endif; ?>
</div>
