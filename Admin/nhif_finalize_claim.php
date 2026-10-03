<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/NhifWorkflow.php';
require_once dirname(__DIR__) . '/includes/ClaimBuilder.php';

$db = nhifDb();
$visitId = (int)($_GET['visit_id'] ?? $_POST['visit_id'] ?? 0);
$message = '';
$error = '';
$workflow = null;
$verification = null;
$profile = null;
$diagnoses = [];
$services = [];
$existingClaim = null;
$nhifResponse = null;

if ($visitId > 0) {
    try {
        $workflow = nhifRequireActiveVisit($visitId);
        $verification = nhifLatestVerification($visitId);
        $profile = nhifVisitProfile($visitId);
        $diagnoses = nhifVisitDiagnoses($visitId);
        $services = nhifVisitServices($visitId);

        $stmt = $db->prepare(
            'SELECT id, folio_id, folio_no, status, submitted_at, reconciled_at
             FROM nhif_claims
             WHERE visit_id=?
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->bind_param('i', $visitId);
        $stmt->execute();
        $existingClaim = $stmt->get_result()->fetch_assoc() ?: null;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['nhif_action'] ?? '') === 'submit_final_claim') {

    try {
        $workflow = nhifRequireActiveVisit($visitId);
        $verification = nhifLatestVerification($visitId);
        $profile = nhifVisitProfile($visitId);
        $diagnoses = nhifVisitDiagnoses($visitId);
        $services = nhifVisitServices($visitId);

        if (!$verification
            || strtoupper((string)$verification['authorization_status']) !== 'ACCEPTED'
            || trim((string)$verification['authorization_no']) === '') {
            throw new RuntimeException('NHIF member authorization must be ACCEPTED before claim submission.');
        }

        if (!$profile) {
            throw new RuntimeException('NHIF patient/visit profile is missing.');
        }

        if (!$diagnoses) {
            throw new RuntimeException('At least one diagnosis is required before eClaim submission.');
        }

        if (!$services) {
            throw new RuntimeException('No NHIF services/items have been recorded for this visit.');
        }

        $existing = $db->prepare(
            "SELECT id, status FROM nhif_claims
             WHERE visit_id=? AND status IN ('SUBMITTED','RECONCILED')
             ORDER BY id DESC LIMIT 1"
        );
        $existing->bind_param('i', $visitId);
        $existing->execute();
        if ($existing->get_result()->fetch_assoc()) {
            throw new RuntimeException(
                'This visit already has a submitted/reconciled claim. Duplicate submission is blocked.'
            );
        }

        foreach ($services as $service) {
            if ((int)$service['is_restricted'] === 1
                && strtoupper((string)$service['approval_status']) !== 'VALID') {
                throw new RuntimeException(
                    'Restricted service ' . $service['nhif_item_code']
                    . ' does not have a VALID NHIF approval.'
                );
            }
        }

        $folioNo = (int)($_POST['folio_no'] ?? 0);
        $serialNo = trim((string)($_POST['serial_no'] ?? ''));
        if ($folioNo < 1) {
            throw new InvalidArgumentException('Folio Number is required.');
        }

        $patientFile = null;
        if (!empty($_FILES['patient_pdf']['tmp_name'])
            && is_uploaded_file($_FILES['patient_pdf']['tmp_name'])) {

            $type = (string)($_FILES['patient_pdf']['type'] ?? '');
            if ($type !== 'application/pdf') {
                throw new InvalidArgumentException('Patient File attachment must be a PDF.');
            }

            $bytes = file_get_contents($_FILES['patient_pdf']['tmp_name']);
            if ($bytes === false) {
                throw new RuntimeException('Unable to read the patient PDF.');
            }
            $patientFile = base64_encode($bytes);
        }

        $folioId = nhifUuidV4();
        $config = nhifConfig();
        $createdBy = (string)(nhifCurrentUserId() ?? 'hospital_user');

        $folio = [
            'FolioID' => $folioId,
            'FacilityCode' => (string)$config['facility_code'],
            'ClaimYear' => (int)date('Y'),
            'ClaimMonth' => (int)date('n'),
            'FolioNo' => $folioNo,
            'SerialNo' => $serialNo,
            'CardNo' => (string)$verification['card_no'],
            'FirstName' => (string)($profile['first_name'] ?? ''),
            'LastName' => (string)($profile['last_name'] ?? ''),
            'Gender' => (string)($profile['gender'] ?? ''),
            'DateOfBirth' => (string)($profile['date_of_birth'] ?? ''),
            'TelephoneNo' => (string)($profile['telephone_no'] ?? ''),
            'PatientFileNo' => (string)($profile['patient_file_no'] ?? ''),
            'PatientFile' => $patientFile,
            'AuthorizationNo' => (string)$verification['authorization_no'],
            'AttendanceDate' => (string)($profile['attendance_date'] ?? date('Y-m-d')),
            'PatientTypeCode' => (string)($profile['patient_type_code'] ?? 'OUT'),
            'DateAdmitted' => $profile['date_admitted'] ?: null,
            'DateDischarged' => $profile['date_discharged'] ?: null,
            'PractitionerNo' => (string)($profile['practitioner_no'] ?? ''),
            'CreatedBy' => $createdBy,
            'DateCreated' => date('c'),
        ];

        if ($folio['PractitionerNo'] === '') {
            foreach ($diagnoses as $diagnosis) {
                if (trim((string)$diagnosis['practitioner_no']) !== '') {
                    $folio['PractitionerNo'] = (string)$diagnosis['practitioner_no'];
                    break;
                }
            }
        }

        if ($folio['PractitionerNo'] === '') {
            throw new RuntimeException('Practitioner registration number is required.');
        }

        $claimDiseases = array_map(
            static fn(array $row): array => [
                'DiseaseCode' => (string)$row['disease_code'],
                'CreatedBy' => $createdBy,
            ],
            $diagnoses
        );

        $claimItems = array_map(
            static fn(array $row): array => [
                'ItemCode' => (string)$row['nhif_item_code'],
                'ItemQuantity' => (float)$row['quantity'],
                'UnitPrice' => (float)$row['unit_price'],
                'ApprovalRefNo' => $row['approval_ref_no'] ?: null,
                'CreatedBy' => $createdBy,
            ],
            $services
        );

        $folio = nhifBuildFolio($folio, $claimDiseases, $claimItems);
        $payload = nhifJson($folio);

        $status = 'DRAFT';
        $patientId = (int)$workflow['patient_id'];
        $claimYear = (int)$folio['ClaimYear'];
        $claimMonth = (int)$folio['ClaimMonth'];
        $cardNo = (string)$verification['card_no'];
        $authorizationNo = (string)$verification['authorization_no'];
        $userId = nhifCurrentUserId();

        $stmt = $db->prepare(
            'INSERT INTO nhif_claims
            (visit_id, patient_id, folio_id, folio_no, serial_no, card_no,
             authorization_no, claim_year, claim_month, payload_json, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'iisisssiissi',
            $visitId,
            $patientId,
            $folioId,
            $folioNo,
            $serialNo,
            $cardNo,
            $authorizationNo,
            $claimYear,
            $claimMonth,
            $payload,
            $status,
            $userId
        );
        $stmt->execute();
        $claimId = (int)$db->insert_id;

        foreach ($diagnoses as $row) {
            $diseaseCode = (string)$row['disease_code'];
            $notes = (string)($row['notes'] ?? '');
            $insert = $db->prepare(
                'INSERT INTO nhif_claim_diseases
                (claim_id, disease_code, remarks)
                VALUES (?, ?, ?)'
            );
            $insert->bind_param('iss', $claimId, $diseaseCode, $notes);
            $insert->execute();
        }

        foreach ($services as $row) {
            $itemCode = (string)$row['nhif_item_code'];
            $qty = (float)$row['quantity'];
            $unitPrice = (float)$row['unit_price'];
            $amount = (float)$row['amount_claimed'];
            $approvalRef = $row['approval_ref_no'] ?: null;
            $sourceDepartment = (string)$row['department'];
            $sourceRecordId = $row['source_record_id'] !== null
                ? (int)$row['source_record_id']
                : null;

            $insert = $db->prepare(
                'INSERT INTO nhif_claim_items
                (claim_id, item_code, item_quantity, unit_price, amount_claimed,
                 approval_ref_no, source_department, source_record_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insert->bind_param(
                'isdddssi',
                $claimId,
                $itemCode,
                $qty,
                $unitPrice,
                $amount,
                $approvalRef,
                $sourceDepartment,
                $sourceRecordId
            );
            $insert->execute();
        }

        $client = new NhifClaimsClient($config);
        $response = $client->submitFolios([$folio]);
        $nhifResponse = $response['json'] ?? ['raw' => $response['raw']];
        $http = (int)$response['status'];
        $responseJson = nhifJson($nhifResponse);
        $finalStatus = ($http >= 200 && $http < 300) ? 'SUBMITTED' : 'ERROR';

        $update = $db->prepare(
            'UPDATE nhif_claims
             SET response_json=?, http_status=?, status=?, submitted_at=NOW()
             WHERE id=?'
        );
        $update->bind_param(
            'sisi',
            $responseJson,
            $http,
            $finalStatus,
            $claimId
        );
        $update->execute();

        if ($finalStatus === 'SUBMITTED') {
            nhifSetWorkflowStatus($visitId, 'CLAIM_SUBMITTED');
            $message = 'NHIF eClaim submitted successfully. Folio ID: ' . $folioId;
        } else {
            nhifSetWorkflowStatus($visitId, 'CLAIM_ERROR');
            $error = 'NHIF returned an error while submitting the claim.';
        }

        nhifAudit(
            'Admin',
            'SubmitFinalEClaim',
            $finalStatus === 'SUBMITTED',
            $patientId,
            $visitId,
            $http,
            $message !== '' ? $message : $error
        );

        $existingClaim = [
            'id' => $claimId,
            'folio_id' => $folioId,
            'folio_no' => $folioNo,
            'status' => $finalStatus,
            'submitted_at' => date('Y-m-d H:i:s'),
            'reconciled_at' => null,
        ];
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<div style="max-width:1100px">
    <h2>NHIF Final eClaim</h2>

    <?php if ($message !== ''): ?>
        <div style="padding:10px;border:1px solid #aaa;margin-bottom:12px">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div style="padding:10px;border:1px solid #a00;margin-bottom:12px">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="get">
        <label>Visit ID
            <input type="number" name="visit_id" value="<?= $visitId ?>" required>
        </label>
        <button type="submit">Load NHIF Visit</button>
    </form>

    <?php if ($workflow && $verification): ?>
        <h3>Claim Readiness</h3>
        <table border="1" cellpadding="6" cellspacing="0" style="width:100%">
            <tr><th>Workflow</th><td><?= htmlspecialchars((string)$workflow['workflow_status']) ?></td></tr>
            <tr><th>Authorization</th><td><?= htmlspecialchars((string)$verification['authorization_status']) ?> / <?= htmlspecialchars((string)$verification['authorization_no']) ?></td></tr>
            <tr><th>Card</th><td><?= htmlspecialchars((string)$verification['card_no']) ?></td></tr>
            <tr><th>Diagnosis Count</th><td><?= count($diagnoses) ?></td></tr>
            <tr><th>Service Count</th><td><?= count($services) ?></td></tr>
            <tr><th>Existing Claim</th><td><?= htmlspecialchars((string)($existingClaim['status'] ?? 'None')) ?></td></tr>
        </table>

        <h3>Patient Details</h3>
        <table border="1" cellpadding="6" cellspacing="0" style="width:100%">
            <tr>
                <th>Name</th>
                <td><?= htmlspecialchars(trim((string)($profile['first_name'] ?? '') . ' ' . (string)($profile['last_name'] ?? ''))) ?></td>
                <th>File No.</th>
                <td><?= htmlspecialchars((string)($profile['patient_file_no'] ?? '')) ?></td>
            </tr>
            <tr>
                <th>Type</th>
                <td><?= htmlspecialchars((string)($profile['patient_type_code'] ?? '')) ?></td>
                <th>Practitioner No.</th>
                <td><?= htmlspecialchars((string)($profile['practitioner_no'] ?? '')) ?></td>
            </tr>
        </table>

        <h3>Diagnoses</h3>
        <table border="1" cellpadding="5" cellspacing="0" style="width:100%">
            <tr><th>Department</th><th>Disease Code</th><th>Notes</th></tr>
            <?php foreach ($diagnoses as $row): ?>
                <tr>
                    <td><?= htmlspecialchars((string)$row['department']) ?></td>
                    <td><?= htmlspecialchars((string)$row['disease_code']) ?></td>
                    <td><?= htmlspecialchars((string)$row['notes']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <h3>Claimable Services</h3>
        <table border="1" cellpadding="5" cellspacing="0" style="width:100%">
            <tr>
                <th>Department</th><th>Item Code</th><th>Item</th>
                <th>Qty</th><th>Unit Price</th><th>Amount</th><th>Approval</th>
            </tr>
            <?php $grandTotal = 0.0; ?>
            <?php foreach ($services as $row): ?>
                <?php $grandTotal += (float)$row['amount_claimed']; ?>
                <tr>
                    <td><?= htmlspecialchars((string)$row['department']) ?></td>
                    <td><?= htmlspecialchars((string)$row['nhif_item_code']) ?></td>
                    <td><?= htmlspecialchars((string)$row['item_name']) ?></td>
                    <td><?= htmlspecialchars((string)$row['quantity']) ?></td>
                    <td><?= number_format((float)$row['unit_price'], 2) ?></td>
                    <td><?= number_format((float)$row['amount_claimed'], 2) ?></td>
                    <td><?= htmlspecialchars((string)$row['approval_ref_no']) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <th colspan="5" style="text-align:right">Total Claim</th>
                <th><?= number_format($grandTotal, 2) ?></th>
                <th></th>
            </tr>
        </table>

        <h3>Submit Final eClaim</h3>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="nhif_action" value="submit_final_claim">
            <input type="hidden" name="visit_id" value="<?= $visitId ?>">

            <p>
                <label>Folio Number
                    <input type="number" min="1" name="folio_no" required>
                </label>
            </p>
            <p>
                <label>Serial Number
                    <input type="text" name="serial_no">
                </label>
            </p>
            <p>
                <label>Patient Treatment/Summary PDF
                    <input type="file" name="patient_pdf" accept="application/pdf">
                </label>
            </p>
            <button type="submit">Validate & Submit Final NHIF eClaim</button>
        </form>
    <?php endif; ?>

    <?php if ($nhifResponse !== null): ?>
        <h3>NHIF Response</h3>
        <pre style="white-space:pre-wrap"><?= htmlspecialchars(
            json_encode($nhifResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        ) ?></pre>
    <?php endif; ?>
</div>
