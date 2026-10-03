<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/nhif_bootstrap.php';

$department = defined('NHIF_DEPARTMENT') ? (string)NHIF_DEPARTMENT : 'Department';
$patientId = (int)($_POST['patient_id'] ?? $_GET['patient_id'] ?? 0);
$visitId = (int)($_POST['visit_id'] ?? $_GET['visit_id'] ?? 0);
$message = '';
$approvalData = null;
$rows = [];

$db = nhifDb();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['nhif_action'] ?? '') === 'preapproval') {
    try {
        $cardNo = trim((string)($_POST['card_no'] ?? ''));
        $referenceNo = trim((string)($_POST['reference_no'] ?? ''));
        $itemCode = trim((string)($_POST['item_code'] ?? ''));

        if ($cardNo === '' || $referenceNo === '' || $itemCode === '') {
            throw new InvalidArgumentException(
                'Card number, approval reference and NHIF item code are required.'
            );
        }

        $client = new NhifServiceClient(nhifConfig());
        $response = $client->verifyPreApproval($cardNo, $referenceNo, $itemCode);
        $approvalData = $response['json'] ?? [];
        $status = strtoupper((string)($approvalData['Status'] ?? ''));

        $json = nhifJson($approvalData);
        $userId = nhifCurrentUserId();
        $stmt = $db->prepare(
            'INSERT INTO nhif_preapprovals
            (patient_id, visit_id, card_no, reference_no, item_code, status, response_json, checked_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
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

        $valid = $status === 'VALID';
        $message = $valid
            ? 'NHIF pre-approval is VALID.'
            : 'NHIF pre-approval is not VALID. Do not mark the restricted service as approved.';

        nhifAudit(
            $department,
            'GetReferenceNoStatus',
            $valid,
            $patientId ?: null,
            $visitId ?: null,
            (int)$response['status'],
            $message
        );
    } catch (Throwable $e) {
        $message = $e->getMessage();
        nhifAudit(
            $department,
            'GetReferenceNoStatus',
            false,
            $patientId ?: null,
            $visitId ?: null,
            null,
            $message
        );
    }
}

$q = trim((string)($_GET['q'] ?? $_POST['q'] ?? ''));
if ($q !== '') {
    $like = '%' . $q . '%';
    $stmt = $db->prepare(
        'SELECT item_code, item_name, package_id, scheme_id, unit_price,
                is_restricted, excluded_products, synced_at
         FROM nhif_tariffs
         WHERE item_code LIKE ? OR item_name LIKE ?
         ORDER BY item_name
         LIMIT 100'
    );
    $stmt->bind_param('ss', $like, $like);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$verification = null;
if ($visitId > 0) {
    $stmt = $db->prepare(
        'SELECT card_no, authorization_status, authorization_no, scheme_id,
                product_code, remarks, verified_at
         FROM nhif_verifications
         WHERE visit_id=?
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->bind_param('i', $visitId);
    $stmt->execute();
    $verification = $stmt->get_result()->fetch_assoc() ?: null;
}
?>
<div class="nhif-facebox-panel" style="min-width:680px;max-width:920px">
    <h3><?= htmlspecialchars($department) ?> - NHIF Services</h3>

    <?php if ($verification): ?>
        <div style="padding:10px;border:1px solid #ccc;margin-bottom:12px">
            <strong>NHIF Visit:</strong>
            <?= htmlspecialchars((string)$verification['authorization_status']) ?>
            &nbsp; | &nbsp;
            <strong>Authorization:</strong>
            <?= htmlspecialchars((string)$verification['authorization_no']) ?>
            &nbsp; | &nbsp;
            <strong>Scheme:</strong>
            <?= htmlspecialchars((string)$verification['scheme_id']) ?>
            &nbsp; | &nbsp;
            <strong>Product:</strong>
            <?= htmlspecialchars((string)$verification['product_code']) ?>
        </div>
    <?php elseif ($visitId > 0): ?>
        <div style="padding:10px;border:1px solid #ccc;margin-bottom:12px">
            No NHIF authorization has been stored for this visit.
        </div>
    <?php endif; ?>

    <?php if ($message !== ''): ?>
        <div style="padding:10px;border:1px solid #ccc;margin-bottom:12px">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <h4>Search NHIF Tariff / Service</h4>
    <form method="get">
        <input type="hidden" name="patient_id" value="<?= $patientId ?>">
        <input type="hidden" name="visit_id" value="<?= $visitId ?>">
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>"
               placeholder="NHIF Item Code or service name" style="width:70%">
        <button type="submit">Search</button>
    </form>

    <?php if ($q !== ''): ?>
        <table border="1" cellpadding="5" cellspacing="0"
               style="width:100%;margin-top:10px">
            <tr>
                <th>Item Code</th>
                <th>Service / Item</th>
                <th>Scheme</th>
                <th>NHIF Price</th>
                <th>Restricted</th>
            </tr>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= htmlspecialchars((string)$row['item_code']) ?></td>
                    <td><?= htmlspecialchars((string)$row['item_name']) ?></td>
                    <td><?= htmlspecialchars((string)$row['scheme_id']) ?></td>
                    <td><?= number_format((float)$row['unit_price'], 2) ?></td>
                    <td>
                        <?= (int)$row['is_restricted'] === 1
                            ? 'YES - Approval Required'
                            : 'No' ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <h4 style="margin-top:18px">Verify Restricted-Service Approval</h4>
    <form method="post">
        <input type="hidden" name="nhif_action" value="preapproval">
        <input type="hidden" name="patient_id" value="<?= $patientId ?>">
        <input type="hidden" name="visit_id" value="<?= $visitId ?>">
        <p>
            <label>NHIF Card Number<br>
                <input type="text" name="card_no"
                       value="<?= htmlspecialchars((string)($verification['card_no'] ?? '')) ?>"
                       required style="width:100%">
            </label>
        </p>
        <p>
            <label>Approval Reference No.<br>
                <input type="text" name="reference_no" required style="width:100%">
            </label>
        </p>
        <p>
            <label>NHIF Item Code<br>
                <input type="text" name="item_code" required style="width:100%">
            </label>
        </p>
        <button type="submit">Verify NHIF Approval</button>
    </form>

    <?php if ($approvalData !== null): ?>
        <pre style="white-space:pre-wrap"><?= htmlspecialchars(
            json_encode($approvalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        ) ?></pre>
    <?php endif; ?>
</div>
