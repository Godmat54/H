<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/nhif_bootstrap.php';

$patientId = (int)($_POST['patient_id'] ?? $_GET['patient_id'] ?? 0);
$visitId = (int)($_POST['visit_id'] ?? $_GET['visit_id'] ?? 0);
$message = '';
$data = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $payload = [
            'CardNo' => trim((string)($_POST['card_no'] ?? '')),
            'AuthorizationNo' => trim((string)($_POST['authorization_no'] ?? '')),
            'PatientFullName' => trim((string)($_POST['patient_full_name'] ?? '')),
            'PhysicianMobileNo' => trim((string)($_POST['physician_mobile_no'] ?? '')),
            'Gender' => trim((string)($_POST['gender'] ?? '')),
            'PhysicianName' => trim((string)($_POST['physician_name'] ?? '')),
            'PhysicianQualificationID' => (int)($_POST['physician_qualification_id'] ?? 0),
            'ServiceIssuingFacilityCode' => trim((string)($_POST['destination_facility_code'] ?? '')),
            'ReferringDiagnosis' => trim((string)($_POST['diagnosis'] ?? '')),
            'ReasonsForReferral' => trim((string)($_POST['reason'] ?? '')),
        ];

        foreach (['CardNo','AuthorizationNo','PatientFullName','PhysicianName',
                  'ServiceIssuingFacilityCode','ReferringDiagnosis','ReasonsForReferral'] as $required) {
            if ((string)$payload[$required] === '') {
                throw new InvalidArgumentException($required . ' is required.');
            }
        }

        $client = new NhifServiceClient(nhifConfig());
        $response = $client->addReferral($payload);
        $data = $response['json'] ?? [];
        $referralNo = (string)($data['ReferralNo'] ?? '');

        $db = nhifDb();
        $json = nhifJson($data);
        $userId = nhifCurrentUserId();
        $stmt = $db->prepare(
            'INSERT INTO nhif_referrals
            (patient_id, visit_id, card_no, authorization_no, referral_no,
             destination_facility_code, diagnosis, reason, response_json, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'iisssssssi',
            $patientId, $visitId, $payload['CardNo'], $payload['AuthorizationNo'],
            $referralNo, $payload['ServiceIssuingFacilityCode'],
            $payload['ReferringDiagnosis'], $payload['ReasonsForReferral'], $json, $userId
        );
        $stmt->execute();

        $message = $referralNo !== ''
            ? 'Referral created. Referral No: ' . $referralNo
            : 'Referral request sent. Review NHIF response below.';
        nhifAudit('Doctor', 'AddReferral', $response['status'] >= 200 && $response['status'] < 300,
            $patientId, $visitId, (int)$response['status'], $message);
    } catch (Throwable $e) {
        $message = $e->getMessage();
        nhifAudit('Doctor', 'AddReferral', false, $patientId ?: null, $visitId ?: null, null, $message);
    }
}
?>
<div style="min-width:560px;max-width:760px">
<h3>NHIF Patient Referral</h3>
<?php if ($message): ?><p><?= htmlspecialchars($message) ?></p><?php endif; ?>
<form method="post">
<input type="hidden" name="patient_id" value="<?= $patientId ?>">
<input type="hidden" name="visit_id" value="<?= $visitId ?>">
<p><label>Card No.<br><input name="card_no" required style="width:100%"></label></p>
<p><label>Authorization No.<br><input name="authorization_no" required style="width:100%"></label></p>
<p><label>Patient Full Name<br><input name="patient_full_name" required style="width:100%"></label></p>
<p><label>Gender<br><input name="gender" style="width:100%"></label></p>
<p><label>Physician Name<br><input name="physician_name" required style="width:100%"></label></p>
<p><label>Physician Mobile No.<br><input name="physician_mobile_no" style="width:100%"></label></p>
<p><label>Physician Qualification ID<br><input type="number" min="1" name="physician_qualification_id" required style="width:100%"></label></p>
<p><label>Destination Facility Code<br><input name="destination_facility_code" required style="width:100%"></label></p>
<p><label>Referring Diagnosis<br><input name="diagnosis" required style="width:100%"></label></p>
<p><label>Reason for Referral<br><textarea name="reason" required style="width:100%"></textarea></label></p>
<button type="submit">Create NHIF Referral</button>
</form>
<?php if ($data): ?><pre><?= htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)) ?></pre><?php endif; ?>
</div>
