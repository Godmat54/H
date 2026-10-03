<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/nhif_bootstrap.php';

$db = nhifDb();
$patientId = (int)($_POST['patient_id'] ?? $_GET['patient_id'] ?? 0);
$visitId = (int)($_POST['visit_id'] ?? $_GET['visit_id'] ?? 0);
$message = '';
$success = false;
$resultData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $cardNo = trim((string)($_POST['card_no'] ?? ''));
        $visitTypeId = (int)($_POST['visit_type_id'] ?? 1);
        $referralNo = trim((string)($_POST['referral_no'] ?? ''));
        $remarks = trim((string)($_POST['remarks'] ?? 'Reception verification'));

        if ($patientId < 1 || $visitId < 1 || $cardNo === '') {
            throw new InvalidArgumentException('Patient, visit and NHIF card number are required.');
        }
        if (!in_array($visitTypeId, [1,2,3,4], true)) {
            throw new InvalidArgumentException('Invalid NHIF visit type.');
        }

        $client = new NhifServiceClient(nhifConfig());
        $response = $client->authorizeCard($cardNo, $visitTypeId, $referralNo, $remarks);
        $data = $response['json'] ?? [];
        $resultData = $data;

        $authorizationStatus = (string)($data['AuthorizationStatus'] ?? '');
        $authorizationNo = (string)($data['AuthorizationNo'] ?? '');
        $schemeId = (string)($data['SchemeID'] ?? $data['SchemeId'] ?? '');
        $productCode = (string)($data['ProductCode'] ?? '');
        $nhifRemarks = (string)($data['Remarks'] ?? $remarks);
        $responseJson = nhifJson($data);
        $userId = nhifCurrentUserId();

        $stmt = $db->prepare(
            'INSERT INTO nhif_verifications
            (patient_id, visit_id, card_no, visit_type_id, authorization_status,
             authorization_no, scheme_id, product_code, remarks, response_json, verified_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'iisissssssi',
            $patientId,
            $visitId,
            $cardNo,
            $visitTypeId,
            $authorizationStatus,
            $authorizationNo,
            $schemeId,
            $productCode,
            $nhifRemarks,
            $responseJson,
            $userId
        );
        $stmt->execute();

        $success = strtoupper($authorizationStatus) === 'ACCEPTED';
        $message = $success
            ? 'NHIF member authorized. Authorization No: ' . $authorizationNo
            : 'NHIF did not accept this authorization. Review the returned remarks.';

        nhifAudit('Reception', 'AuthorizeCard', $success, $patientId, $visitId,
            (int)$response['status'], $message);
    } catch (Throwable $e) {
        $message = $e->getMessage();
        nhifAudit('Reception', 'AuthorizeCard', false, $patientId ?: null,
            $visitId ?: null, null, $message);
    }
}
?>
<div class="nhif-facebox" style="min-width:520px;max-width:720px">
    <h3>NHIF Member Verification</h3>
    <?php if ($message !== ''): ?>
        <div style="padding:10px;margin-bottom:10px;border:1px solid #ccc">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="patient_id" value="<?= $patientId ?>">
        <input type="hidden" name="visit_id" value="<?= $visitId ?>">

        <p><label>NHIF Card Number<br>
            <input type="text" name="card_no" required style="width:100%">
        </label></p>

        <p><label>Visit Type<br>
            <select name="visit_type_id" style="width:100%">
                <option value="1">Normal Visit</option>
                <option value="2">Emergency</option>
                <option value="3">Referral</option>
                <option value="4">Follow-up Visit</option>
            </select>
        </label></p>

        <p><label>Referral Number (when applicable)<br>
            <input type="text" name="referral_no" style="width:100%">
        </label></p>

        <p><label>Remarks<br>
            <input type="text" name="remarks" value="Reception verification" style="width:100%">
        </label></p>

        <button type="submit">Verify NHIF Member</button>
    </form>

    <?php if (is_array($resultData)): ?>
        <h4>NHIF Response</h4>
        <pre style="white-space:pre-wrap"><?= htmlspecialchars(json_encode($resultData, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)) ?></pre>
    <?php endif; ?>
</div>
