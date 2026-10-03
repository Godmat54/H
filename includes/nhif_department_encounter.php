<?php
declare(strict_types=1);

require_once __DIR__ . '/NhifWorkflow.php';

$department = defined('NHIF_DEPARTMENT') ? (string)NHIF_DEPARTMENT : 'Department';
$allowDiagnosis = defined('NHIF_ALLOW_DIAGNOSIS') ? (bool)NHIF_ALLOW_DIAGNOSIS : false;

$patientId = (int)($_GET['patient_id'] ?? $_POST['patient_id'] ?? 0);
$visitId = (int)($_GET['visit_id'] ?? $_POST['visit_id'] ?? 0);

$message = '';
$approvalData = null;
$tariffRows = [];
$workflow = null;
$verification = null;
$profile = null;
$diagnoses = [];
$services = [];

try {
    if ($patientId < 1 || $visitId < 1) {
        throw new InvalidArgumentException('Patient ID and Visit ID are required.');
    }

    $workflow = nhifRequireActiveVisit($visitId);
    $verification = nhifLatestVerification($visitId);
    $profile = nhifVisitProfile($visitId);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string)($_POST['nhif_action'] ?? '');

        if ($action === 'save_profile') {
            nhifSaveVisitProfile($patientId, $visitId, $_POST);
            $message = 'NHIF patient/visit details saved.';
        }

        if ($action === 'add_diagnosis') {
            if (!$allowDiagnosis) {
                throw new RuntimeException('Diagnosis entry is not enabled for this department.');
            }
            $diseaseCode = strtoupper(trim((string)($_POST['disease_code'] ?? '')));
            if ($diseaseCode === '') {
                throw new InvalidArgumentException('Diagnosis/Disease code is required.');
            }
            nhifAddDiagnosis(
                $patientId,
                $visitId,
                $department,
                $diseaseCode,
                trim((string)($_POST['diagnosis_notes'] ?? '')),
                trim((string)($_POST['practitioner_no'] ?? ''))
            );
            $message = 'Diagnosis added to the NHIF visit.';
        }

        if ($action === 'verify_approval') {
            if (!$verification
                || strtoupper((string)$verification['authorization_status']) !== 'ACCEPTED') {
                throw new RuntimeException('The NHIF visit must be authorized first.');
            }

            $referenceNo = trim((string)($_POST['approval_ref_no'] ?? ''));
            $itemCode = trim((string)($_POST['approval_item_code'] ?? ''));
            if ($referenceNo === '' || $itemCode === '') {
                throw new InvalidArgumentException('Approval reference and NHIF Item Code are required.');
            }

            $client = new NhifServiceClient(nhifConfig());
            $response = $client->verifyPreApproval(
                (string)$verification['card_no'],
                $referenceNo,
                $itemCode
            );
            $approvalData = $response['json'] ?? [];
            $status = strtoupper((string)($approvalData['Status'] ?? ''));

            $db = nhifDb();
            $json = nhifJson($approvalData);
            $userId = nhifCurrentUserId();
            $stmt = $db->prepare(
                'INSERT INTO nhif_preapprovals
                (patient_id, visit_id, card_no, reference_no, item_code,
                 status, response_json, checked_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $cardNo = (string)$verification['card_no'];
            $stmt->bind_param(
                'iisssssi',
                $patientId,
                $visitId,
                $cardNo,
                $referenceNo,
                $itemCode,
                $status,
                $json,
                $userId
            );
            $stmt->execute();

            $message = $status === 'VALID'
                ? 'NHIF pre-approval is VALID.'
                : 'NHIF pre-approval is not VALID.';
        }

        if ($action === 'add_service') {
            if (!$verification
                || strtoupper((string)$verification['authorization_status']) !== 'ACCEPTED') {
                throw new RuntimeException('The NHIF visit must be authorized before services are recorded.');
            }

            $itemCode = trim((string)($_POST['nhif_item_code'] ?? ''));
            if ($itemCode === '') {
                throw new InvalidArgumentException('NHIF Item Code is required.');
            }

            $db = nhifDb();
            $stmt = $db->prepare(
                'SELECT item_code, item_name, unit_price, is_restricted
                 FROM nhif_tariffs
                 WHERE item_code=?
                 ORDER BY synced_at DESC
                 LIMIT 1'
            );
            $stmt->bind_param('s', $itemCode);
            $stmt->execute();
            $tariff = $stmt->get_result()->fetch_assoc();

            if (!$tariff) {
                throw new RuntimeException('NHIF Item Code is not in the synchronized tariff table.');
            }

            $restricted = (int)$tariff['is_restricted'];
            $approvalRef = trim((string)($_POST['approval_ref_no'] ?? ''));
            $approvalStatus = null;

            if ($restricted === 1) {
                if ($approvalRef === '') {
                    throw new RuntimeException('This is a restricted service. Approval reference is required.');
                }

                $check = $db->prepare(
                    'SELECT status FROM nhif_preapprovals
                     WHERE visit_id=? AND item_code=? AND reference_no=?
                     ORDER BY id DESC LIMIT 1'
                );
                $check->bind_param('iss', $visitId, $itemCode, $approvalRef);
                $check->execute();
                $approval = $check->get_result()->fetch_assoc();
                $approvalStatus = strtoupper((string)($approval['status'] ?? ''));

                if ($approvalStatus !== 'VALID') {
                    throw new RuntimeException(
                        'Restricted service cannot be added until the approval reference is VALID.'
                    );
                }
            }

            nhifAddService(
                $patientId,
                $visitId,
                $department,
                [
                    'local_service_code' => $_POST['local_service_code'] ?? '',
                    'local_service_name' => $_POST['local_service_name'] ?? '',
                    'nhif_item_code' => $itemCode,
                    'item_name' => $tariff['item_name'],
                    'quantity' => $_POST['quantity'] ?? 1,
                    'unit_price' => $tariff['unit_price'],
                    'is_restricted' => $restricted,
                    'approval_ref_no' => $approvalRef,
                    'approval_status' => $approvalStatus,
                    'practitioner_no' => $_POST['service_practitioner_no'] ?? '',
                    'notes' => $_POST['service_notes'] ?? '',
                    'source_record_id' => $_POST['source_record_id'] ?? null,
                ]
            );

            $message = 'NHIF service added to this patient visit.';
        }

        $profile = nhifVisitProfile($visitId);
    }

    $q = trim((string)($_GET['q'] ?? $_POST['q'] ?? ''));
    if ($q !== '') {
        $db = nhifDb();
        $like = '%' . $q . '%';
        $stmt = $db->prepare(
            'SELECT item_code, item_name, scheme_id, unit_price, is_restricted
             FROM nhif_tariffs
             WHERE item_code LIKE ? OR item_name LIKE ?
             ORDER BY item_name
             LIMIT 60'
        );
        $stmt->bind_param('ss', $like, $like);
        $stmt->execute();
        $tariffRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    $diagnoses = nhifVisitDiagnoses($visitId);
    $services = nhifVisitServices($visitId);
} catch (Throwable $e) {
    $message = $e->getMessage();
}
?>
<div class="nhif-facebox-panel" style="min-width:760px;max-width:1050px">
    <h3><?= htmlspecialchars($department) ?> - NHIF Patient Visit</h3>

    <?php if ($message !== ''): ?>
        <div style="padding:10px;border:1px solid #bbb;margin-bottom:12px">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($workflow): ?>
        <div style="padding:10px;background:#f7f7f7;border:1px solid #ddd">
            <strong>NHIF Workflow:</strong>
            <?= htmlspecialchars((string)$workflow['workflow_status']) ?>
            <?php if ($verification): ?>
                &nbsp; | &nbsp;
                <strong>Authorization:</strong>
                <?= htmlspecialchars((string)$verification['authorization_status']) ?>
                <?= htmlspecialchars((string)$verification['authorization_no']) ?>
                &nbsp; | &nbsp;
                <strong>Card:</strong>
                <?= htmlspecialchars((string)$verification['card_no']) ?>
                &nbsp; | &nbsp;
                <strong>Scheme:</strong>
                <?= htmlspecialchars((string)$verification['scheme_id']) ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <h4>Patient / Visit NHIF Details</h4>
    <form method="post">
        <input type="hidden" name="nhif_action" value="save_profile">
        <input type="hidden" name="patient_id" value="<?= $patientId ?>">
        <input type="hidden" name="visit_id" value="<?= $visitId ?>">

        <table style="width:100%">
            <tr>
                <td>First Name</td>
                <td><input name="first_name" value="<?= htmlspecialchars((string)($profile['first_name'] ?? '')) ?>"></td>
                <td>Last Name</td>
                <td><input name="last_name" value="<?= htmlspecialchars((string)($profile['last_name'] ?? '')) ?>"></td>
            </tr>
            <tr>
                <td>Gender</td>
                <td><input name="gender" value="<?= htmlspecialchars((string)($profile['gender'] ?? '')) ?>"></td>
                <td>Date of Birth</td>
                <td><input type="date" name="date_of_birth" value="<?= htmlspecialchars((string)($profile['date_of_birth'] ?? '')) ?>"></td>
            </tr>
            <tr>
                <td>Telephone</td>
                <td><input name="telephone_no" value="<?= htmlspecialchars((string)($profile['telephone_no'] ?? '')) ?>"></td>
                <td>Hospital File No.</td>
                <td><input name="patient_file_no" value="<?= htmlspecialchars((string)($profile['patient_file_no'] ?? '')) ?>"></td>
            </tr>
            <tr>
                <td>Patient Type</td>
                <td>
                    <select name="patient_type_code">
                        <option value="OUT" <?= (($profile['patient_type_code'] ?? 'OUT') === 'OUT') ? 'selected' : '' ?>>OUT</option>
                        <option value="IN" <?= (($profile['patient_type_code'] ?? '') === 'IN') ? 'selected' : '' ?>>IN</option>
                    </select>
                </td>
                <td>Attendance Date</td>
                <td><input type="date" name="attendance_date" value="<?= htmlspecialchars((string)($profile['attendance_date'] ?? date('Y-m-d'))) ?>"></td>
            </tr>
            <tr>
                <td>Date Admitted</td>
                <td><input type="date" name="date_admitted" value="<?= htmlspecialchars((string)($profile['date_admitted'] ?? '')) ?>"></td>
                <td>Date Discharged</td>
                <td><input type="date" name="date_discharged" value="<?= htmlspecialchars((string)($profile['date_discharged'] ?? '')) ?>"></td>
            </tr>
            <tr>
                <td>Practitioner No.</td>
                <td><input name="practitioner_no" value="<?= htmlspecialchars((string)($profile['practitioner_no'] ?? '')) ?>"></td>
                <td>Practitioner Name</td>
                <td><input name="practitioner_name" value="<?= htmlspecialchars((string)($profile['practitioner_name'] ?? '')) ?>"></td>
            </tr>
        </table>
        <button type="submit">Save NHIF Patient Details</button>
    </form>

    <?php if ($allowDiagnosis): ?>
        <h4>Diagnosis</h4>
        <form method="post">
            <input type="hidden" name="nhif_action" value="add_diagnosis">
            <input type="hidden" name="patient_id" value="<?= $patientId ?>">
            <input type="hidden" name="visit_id" value="<?= $visitId ?>">
            <input name="disease_code" placeholder="Diagnosis / Disease Code" required>
            <input name="practitioner_no" placeholder="Practitioner No.">
            <input name="diagnosis_notes" placeholder="Notes">
            <button type="submit">Add Diagnosis</button>
        </form>
    <?php endif; ?>

    <h4>Find NHIF Service / Item</h4>
    <form method="get">
        <input type="hidden" name="patient_id" value="<?= $patientId ?>">
        <input type="hidden" name="visit_id" value="<?= $visitId ?>">
        <input name="q" value="<?= htmlspecialchars((string)($_GET['q'] ?? '')) ?>"
               placeholder="Item code or service name" style="width:65%">
        <button type="submit">Search Tariff</button>
    </form>

    <?php if ($tariffRows): ?>
        <table border="1" cellpadding="5" cellspacing="0" style="width:100%;margin-top:8px">
            <tr><th>Item Code</th><th>Item</th><th>Scheme</th><th>Price</th><th>Restricted</th></tr>
            <?php foreach ($tariffRows as $row): ?>
                <tr>
                    <td><?= htmlspecialchars((string)$row['item_code']) ?></td>
                    <td><?= htmlspecialchars((string)$row['item_name']) ?></td>
                    <td><?= htmlspecialchars((string)$row['scheme_id']) ?></td>
                    <td><?= number_format((float)$row['unit_price'], 2) ?></td>
                    <td><?= (int)$row['is_restricted'] === 1 ? 'YES' : 'No' ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <h4>Restricted-Service Pre-Approval</h4>
    <form method="post">
        <input type="hidden" name="nhif_action" value="verify_approval">
        <input type="hidden" name="patient_id" value="<?= $patientId ?>">
        <input type="hidden" name="visit_id" value="<?= $visitId ?>">
        <input name="approval_item_code" placeholder="NHIF Item Code" required>
        <input name="approval_ref_no" placeholder="Approval Reference No." required>
        <button type="submit">Verify Approval</button>
    </form>

    <h4>Add NHIF Service Used</h4>
    <form method="post">
        <input type="hidden" name="nhif_action" value="add_service">
        <input type="hidden" name="patient_id" value="<?= $patientId ?>">
        <input type="hidden" name="visit_id" value="<?= $visitId ?>">

        <table style="width:100%">
            <tr>
                <td>Local Service Code</td>
                <td><input name="local_service_code"></td>
                <td>Local Service Name</td>
                <td><input name="local_service_name"></td>
            </tr>
            <tr>
                <td>NHIF Item Code</td>
                <td><input name="nhif_item_code" required></td>
                <td>Quantity</td>
                <td><input type="number" step="0.01" min="0.01" name="quantity" value="1" required></td>
            </tr>
            <tr>
                <td>Approval Ref No.</td>
                <td><input name="approval_ref_no"></td>
                <td>Practitioner No.</td>
                <td><input name="service_practitioner_no"></td>
            </tr>
            <tr>
                <td>Source Record ID</td>
                <td><input type="number" name="source_record_id"></td>
                <td>Notes</td>
                <td><input name="service_notes"></td>
            </tr>
        </table>
        <button type="submit">Add Service to NHIF Visit</button>
    </form>

    <h4>Diagnoses in this NHIF Visit</h4>
    <table border="1" cellpadding="5" cellspacing="0" style="width:100%">
        <tr><th>Department</th><th>Disease Code</th><th>Practitioner</th><th>Notes</th></tr>
        <?php foreach ($diagnoses as $row): ?>
            <tr>
                <td><?= htmlspecialchars((string)$row['department']) ?></td>
                <td><?= htmlspecialchars((string)$row['disease_code']) ?></td>
                <td><?= htmlspecialchars((string)$row['practitioner_no']) ?></td>
                <td><?= htmlspecialchars((string)$row['notes']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <h4>NHIF Services Recorded in this Visit</h4>
    <table border="1" cellpadding="5" cellspacing="0" style="width:100%">
        <tr>
            <th>Department</th><th>Item Code</th><th>Item</th>
            <th>Qty</th><th>Unit Price</th><th>Amount</th><th>Approval</th>
        </tr>
        <?php foreach ($services as $row): ?>
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
    </table>

    <?php if ($approvalData !== null): ?>
        <h4>NHIF Approval Response</h4>
        <pre style="white-space:pre-wrap"><?= htmlspecialchars(
            json_encode($approvalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        ) ?></pre>
    <?php endif; ?>
</div>
