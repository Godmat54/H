<?php
declare(strict_types=1);

require_once __DIR__ . '/nhif_bootstrap.php';

function nhifIsPaymentMode(string $paymentMode): bool
{
    $mode = strtoupper(trim($paymentMode));
    return in_array($mode, ['NHIF', 'N.H.I.F', 'INSURANCE-NHIF', 'NHIF INSURANCE'], true);
}

function nhifStartWorkflow(int $patientId, int $visitId, string $paymentMode): bool
{
    if (!nhifIsPaymentMode($paymentMode)) {
        return false;
    }

    $db = nhifDb();
    $userId = nhifCurrentUserId();
    $active = 1;
    $status = 'PENDING_VERIFICATION';

    $stmt = $db->prepare(
        'INSERT INTO nhif_visit_workflow
        (patient_id, visit_id, payment_mode, nhif_active, workflow_status, started_by)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            payment_mode=VALUES(payment_mode),
            nhif_active=1,
            workflow_status=IF(workflow_status="COMPLETED", workflow_status, "PENDING_VERIFICATION"),
            updated_at=NOW()'
    );
    $stmt->bind_param(
        'iisisi',
        $patientId,
        $visitId,
        $paymentMode,
        $active,
        $status,
        $userId
    );
    $stmt->execute();

    nhifAudit(
        'Reception',
        'StartNHIFWorkflow',
        true,
        $patientId,
        $visitId,
        null,
        'NHIF workflow started from patient payment mode.'
    );

    return true;
}

function nhifWorkflow(int $visitId): ?array
{
    $db = nhifDb();
    $stmt = $db->prepare(
        'SELECT * FROM nhif_visit_workflow WHERE visit_id=? LIMIT 1'
    );
    $stmt->bind_param('i', $visitId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function nhifRequireActiveVisit(int $visitId): array
{
    $row = nhifWorkflow($visitId);
    if (!$row || (int)$row['nhif_active'] !== 1) {
        throw new RuntimeException('This visit is not active for NHIF.');
    }
    return $row;
}

function nhifSetWorkflowStatus(int $visitId, string $status): void
{
    $db = nhifDb();
    $stmt = $db->prepare(
        'UPDATE nhif_visit_workflow
         SET workflow_status=?, updated_at=NOW()
         WHERE visit_id=?'
    );
    $stmt->bind_param('si', $status, $visitId);
    $stmt->execute();
}

function nhifLatestVerification(int $visitId): ?array
{
    $db = nhifDb();
    $stmt = $db->prepare(
        'SELECT * FROM nhif_verifications
         WHERE visit_id=?
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->bind_param('i', $visitId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function nhifSaveVisitProfile(int $patientId, int $visitId, array $data): void
{
    $db = nhifDb();
    $userId = nhifCurrentUserId();

    $firstName = trim((string)($data['first_name'] ?? ''));
    $lastName = trim((string)($data['last_name'] ?? ''));
    $gender = trim((string)($data['gender'] ?? ''));
    $dob = trim((string)($data['date_of_birth'] ?? '')) ?: null;
    $telephone = trim((string)($data['telephone_no'] ?? ''));
    $fileNo = trim((string)($data['patient_file_no'] ?? ''));
    $patientType = strtoupper(trim((string)($data['patient_type_code'] ?? 'OUT')));
    $attendanceDate = trim((string)($data['attendance_date'] ?? date('Y-m-d'))) ?: null;
    $admitted = trim((string)($data['date_admitted'] ?? '')) ?: null;
    $discharged = trim((string)($data['date_discharged'] ?? '')) ?: null;
    $practitionerNo = trim((string)($data['practitioner_no'] ?? ''));
    $practitionerName = trim((string)($data['practitioner_name'] ?? ''));

    if (!in_array($patientType, ['OUT', 'IN'], true)) {
        $patientType = 'OUT';
    }

    $stmt = $db->prepare(
        'INSERT INTO nhif_visit_profile
        (patient_id, visit_id, first_name, last_name, gender, date_of_birth,
         telephone_no, patient_file_no, patient_type_code, attendance_date,
         date_admitted, date_discharged, practitioner_no, practitioner_name, updated_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            first_name=VALUES(first_name),
            last_name=VALUES(last_name),
            gender=VALUES(gender),
            date_of_birth=VALUES(date_of_birth),
            telephone_no=VALUES(telephone_no),
            patient_file_no=VALUES(patient_file_no),
            patient_type_code=VALUES(patient_type_code),
            attendance_date=VALUES(attendance_date),
            date_admitted=VALUES(date_admitted),
            date_discharged=VALUES(date_discharged),
            practitioner_no=VALUES(practitioner_no),
            practitioner_name=VALUES(practitioner_name),
            updated_by=VALUES(updated_by),
            updated_at=NOW()'
    );
    $stmt->bind_param(
        'iissssssssssssi',
        $patientId,
        $visitId,
        $firstName,
        $lastName,
        $gender,
        $dob,
        $telephone,
        $fileNo,
        $patientType,
        $attendanceDate,
        $admitted,
        $discharged,
        $practitionerNo,
        $practitionerName,
        $userId
    );
    $stmt->execute();
}

function nhifVisitProfile(int $visitId): ?array
{
    $db = nhifDb();
    $stmt = $db->prepare(
        'SELECT * FROM nhif_visit_profile WHERE visit_id=? LIMIT 1'
    );
    $stmt->bind_param('i', $visitId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function nhifAddDiagnosis(
    int $patientId,
    int $visitId,
    string $department,
    string $diseaseCode,
    string $notes = '',
    string $practitionerNo = ''
): void {
    $db = nhifDb();
    $userId = nhifCurrentUserId();

    $stmt = $db->prepare(
        'INSERT INTO nhif_visit_diagnoses
        (patient_id, visit_id, department, disease_code, notes, practitioner_no, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            notes=VALUES(notes),
            practitioner_no=VALUES(practitioner_no)'
    );
    $stmt->bind_param(
        'iissssi',
        $patientId,
        $visitId,
        $department,
        $diseaseCode,
        $notes,
        $practitionerNo,
        $userId
    );
    $stmt->execute();
}

function nhifAddService(
    int $patientId,
    int $visitId,
    string $department,
    array $service
): int {
    $db = nhifDb();
    $userId = nhifCurrentUserId();

    $localCode = trim((string)($service['local_service_code'] ?? ''));
    $localName = trim((string)($service['local_service_name'] ?? ''));
    $itemCode = trim((string)($service['nhif_item_code'] ?? ''));
    $itemName = trim((string)($service['item_name'] ?? ''));
    $quantity = (float)($service['quantity'] ?? 1);
    $unitPrice = (float)($service['unit_price'] ?? 0);
    $amount = round($quantity * $unitPrice, 2);
    $restricted = !empty($service['is_restricted']) ? 1 : 0;
    $approvalRef = trim((string)($service['approval_ref_no'] ?? '')) ?: null;
    $approvalStatus = trim((string)($service['approval_status'] ?? '')) ?: null;
    $practitionerNo = trim((string)($service['practitioner_no'] ?? ''));
    $notes = trim((string)($service['notes'] ?? ''));
    $sourceRecordId = isset($service['source_record_id']) && is_numeric($service['source_record_id'])
        ? (int)$service['source_record_id']
        : null;

    if ($itemCode === '') {
        throw new InvalidArgumentException('NHIF Item Code is required.');
    }
    if ($quantity <= 0) {
        throw new InvalidArgumentException('Quantity must be greater than zero.');
    }
    if ($restricted === 1 && strtoupper((string)$approvalStatus) !== 'VALID') {
        throw new RuntimeException('Restricted NHIF service requires a VALID approval reference.');
    }

    $stmt = $db->prepare(
        'INSERT INTO nhif_department_services
        (patient_id, visit_id, department, local_service_code, local_service_name,
         nhif_item_code, item_name, quantity, unit_price, amount_claimed,
         is_restricted, approval_ref_no, approval_status, practitioner_no,
         notes, source_record_id, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param(
        'iisssssdddissssii',
        $patientId,
        $visitId,
        $department,
        $localCode,
        $localName,
        $itemCode,
        $itemName,
        $quantity,
        $unitPrice,
        $amount,
        $restricted,
        $approvalRef,
        $approvalStatus,
        $practitionerNo,
        $notes,
        $sourceRecordId,
        $userId
    );
    $stmt->execute();

    nhifAudit(
        $department,
        'RecordNHIFService',
        true,
        $patientId,
        $visitId,
        null,
        $itemCode . ' x ' . $quantity
    );

    return (int)$db->insert_id;
}

function nhifVisitDiagnoses(int $visitId): array
{
    $db = nhifDb();
    $stmt = $db->prepare(
        'SELECT * FROM nhif_visit_diagnoses
         WHERE visit_id=?
         ORDER BY id'
    );
    $stmt->bind_param('i', $visitId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function nhifVisitServices(int $visitId): array
{
    $db = nhifDb();
    $stmt = $db->prepare(
        'SELECT * FROM nhif_department_services
         WHERE visit_id=? AND status="RECORDED"
         ORDER BY id'
    );
    $stmt->bind_param('i', $visitId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
