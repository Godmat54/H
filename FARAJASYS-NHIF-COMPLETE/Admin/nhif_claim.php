<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/nhif_bootstrap.php';
require_once dirname(__DIR__) . '/includes/ClaimBuilder.php';

$db = nhifDb();
$message = '';
$responseView = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $visitId = (int)($_POST['visit_id'] ?? 0);
        $patientId = (int)($_POST['patient_id'] ?? 0);
        $cardNo = trim((string)($_POST['card_no'] ?? ''));
        $authorizationNo = trim((string)($_POST['authorization_no'] ?? ''));
        $folioNo = (int)($_POST['folio_no'] ?? 0);
        $serialNo = trim((string)($_POST['serial_no'] ?? ''));
        $patientType = strtoupper(trim((string)($_POST['patient_type_code'] ?? 'OUT')));
        $practitionerNo = trim((string)($_POST['practitioner_no'] ?? ''));

        if ($visitId < 1 || $cardNo === '' || $authorizationNo === '' || $folioNo < 1 || $practitionerNo === '') {
            throw new InvalidArgumentException(
                'Visit ID, card number, authorization number, folio number and practitioner number are required.'
            );
        }
        if (!in_array($patientType, ['OUT','IN'], true)) {
            throw new InvalidArgumentException('Patient Type must be OUT or IN.');
        }

        $diseaseCodes = array_values(array_filter(array_map(
            'trim',
            explode(',', (string)($_POST['disease_codes'] ?? ''))
        )));
        if (!$diseaseCodes) {
            throw new InvalidArgumentException('At least one diagnosis/disease code is required.');
        }

        $items = json_decode((string)($_POST['items_json'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($items) || !$items) {
            throw new InvalidArgumentException('At least one claim item is required.');
        }

        $patientFile = null;
        if (!empty($_FILES['patient_pdf']['tmp_name']) && is_uploaded_file($_FILES['patient_pdf']['tmp_name'])) {
            if (($_FILES['patient_pdf']['type'] ?? '') !== 'application/pdf') {
                throw new InvalidArgumentException('Patient attachment must be a PDF.');
            }
            $contents = file_get_contents($_FILES['patient_pdf']['tmp_name']);
            if ($contents === false) {
                throw new RuntimeException('Unable to read patient PDF.');
            }
            $patientFile = base64_encode($contents);
        }

        $config = nhifConfig();
        $folioId = nhifUuidV4();
        $createdBy = (string)(nhifCurrentUserId() ?? 'hospital_user');

        $folio = [
            'FolioID' => $folioId,
            'FacilityCode' => $config['facility_code'],
            'ClaimYear' => (int)date('Y'),
            'ClaimMonth' => (int)date('n'),
            'FolioNo' => $folioNo,
            'SerialNo' => $serialNo,
            'CardNo' => $cardNo,
            'FirstName' => trim((string)($_POST['first_name'] ?? '')),
            'LastName' => trim((string)($_POST['last_name'] ?? '')),
            'Gender' => trim((string)($_POST['gender'] ?? '')),
            'DateOfBirth' => trim((string)($_POST['date_of_birth'] ?? '')),
            'TelephoneNo' => trim((string)($_POST['telephone_no'] ?? '')),
            'PatientFileNo' => trim((string)($_POST['patient_file_no'] ?? '')),
            'PatientFile' => $patientFile,
            'AuthorizationNo' => $authorizationNo,
            'AttendanceDate' => trim((string)($_POST['attendance_date'] ?? date('Y-m-d'))),
            'PatientTypeCode' => $patientType,
            'DateAdmitted' => trim((string)($_POST['date_admitted'] ?? '')) ?: null,
            'DateDischarged' => trim((string)($_POST['date_discharged'] ?? '')) ?: null,
            'PractitionerNo' => $practitionerNo,
            'CreatedBy' => $createdBy,
            'DateCreated' => date('c'),
        ];

        $diseases = array_map(
            static fn(string $code): array => ['DiseaseCode' => $code],
            $diseaseCodes
        );
        $folio = nhifBuildFolio($folio, $diseases, $items);
        $payload = nhifJson($folio);

        $status = 'DRAFT';
        $userId = nhifCurrentUserId();
        $claimYear = (int)$folio['ClaimYear'];
        $claimMonth = (int)$folio['ClaimMonth'];

        $stmt = $db->prepare(
            'INSERT INTO nhif_claims
            (visit_id, patient_id, folio_id, folio_no, serial_no, card_no,
             authorization_no, claim_year, claim_month, payload_json, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'iisiissiiisi',
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
        $claimId = $db->insert_id;

        foreach ($diseaseCodes as $code) {
            $ds = $db->prepare('INSERT INTO nhif_claim_diseases (claim_id, disease_code) VALUES (?, ?)');
            $ds->bind_param('is', $claimId, $code);
            $ds->execute();
        }

        foreach ($folio['FolioItems'] as $item) {
            $code = (string)$item['ItemCode'];
            $qty = (float)$item['ItemQuantity'];
            $price = (float)$item['UnitPrice'];
            $amount = (float)$item['AmountClaimed'];
            $approval = isset($item['ApprovalRefNo']) ? (string)$item['ApprovalRefNo'] : null;
            $is = $db->prepare(
                'INSERT INTO nhif_claim_items
                (claim_id, item_code, item_quantity, unit_price, amount_claimed, approval_ref_no)
                VALUES (?, ?, ?, ?, ?, ?)'
            );
            $is->bind_param('isddds', $claimId, $code, $qty, $price, $amount, $approval);
            $is->execute();
        }

        if (($_POST['action'] ?? 'draft') === 'submit') {
            $client = new NhifClaimsClient($config);
            $nhifResponse = $client->submitFolios([$folio]);
            $responseView = $nhifResponse['json'] ?? $nhifResponse['raw'];
            $responseJson = nhifJson($nhifResponse['json'] ?? ['raw' => $nhifResponse['raw']]);
            $http = (int)$nhifResponse['status'];
            $newStatus = ($http >= 200 && $http < 300) ? 'SUBMITTED' : 'ERROR';

            $up = $db->prepare(
                'UPDATE nhif_claims
                 SET response_json=?, http_status=?, status=?, submitted_at=NOW()
                 WHERE id=?'
            );
            $up->bind_param('sisi', $responseJson, $http, $newStatus, $claimId);
            $up->execute();

            $message = "Claim {$folioId} saved and sent to NHIF. HTTP {$http}.";
            nhifAudit('Accounts', 'SubmitFolios', $newStatus === 'SUBMITTED',
                $patientId ?: null, $visitId, $http, $message);
        } else {
            $message = "Claim {$folioId} saved as DRAFT.";
            nhifAudit('Accounts', 'SaveClaimDraft', true, $patientId ?: null, $visitId, null, $message);
        }
    } catch (Throwable $e) {
        $message = $e->getMessage();
        nhifAudit('Accounts', 'ClaimBuildOrSubmit', false, null, null, null, $message);
    }
}
?>
<h2>NHIF eClaim / Folio</h2>
<?php if ($message): ?><p><?= htmlspecialchars($message) ?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data">
<table>
<tr><td>Visit ID</td><td><input type="number" name="visit_id" required></td></tr>
<tr><td>Patient ID</td><td><input type="number" name="patient_id"></td></tr>
<tr><td>NHIF Card No.</td><td><input name="card_no" required></td></tr>
<tr><td>Authorization No.</td><td><input name="authorization_no" required></td></tr>
<tr><td>Folio No.</td><td><input type="number" name="folio_no" required></td></tr>
<tr><td>Serial No.</td><td><input name="serial_no"></td></tr>
<tr><td>First Name</td><td><input name="first_name"></td></tr>
<tr><td>Last Name</td><td><input name="last_name"></td></tr>
<tr><td>Gender</td><td><input name="gender"></td></tr>
<tr><td>Date of Birth</td><td><input type="date" name="date_of_birth"></td></tr>
<tr><td>Telephone</td><td><input name="telephone_no"></td></tr>
<tr><td>Hospital File No.</td><td><input name="patient_file_no"></td></tr>
<tr><td>Attendance Date</td><td><input type="date" name="attendance_date" value="<?= date('Y-m-d') ?>"></td></tr>
<tr><td>Patient Type</td><td><select name="patient_type_code"><option>OUT</option><option>IN</option></select></td></tr>
<tr><td>Date Admitted</td><td><input type="date" name="date_admitted"></td></tr>
<tr><td>Date Discharged</td><td><input type="date" name="date_discharged"></td></tr>
<tr><td>Practitioner No.</td><td><input name="practitioner_no" required></td></tr>
<tr><td>Disease Codes</td><td><input name="disease_codes" placeholder="CODE1,CODE2" required></td></tr>
<tr><td>Patient PDF</td><td><input type="file" name="patient_pdf" accept="application/pdf"></td></tr>
<tr><td>Claim Items JSON</td><td>
<textarea name="items_json" rows="9" cols="70" required>[
  {"ItemCode":"NHIF_ITEM_CODE","ItemQuantity":1,"UnitPrice":0.00,"ApprovalRefNo":null}
]</textarea>
</td></tr>
</table>
<button type="submit" name="action" value="draft">Save Draft</button>
<button type="submit" name="action" value="submit">Save & Submit to NHIF</button>
</form>
<?php if ($responseView !== null): ?>
<pre><?= htmlspecialchars(is_string($responseView) ? $responseView : json_encode($responseView, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)) ?></pre>
<?php endif; ?>
