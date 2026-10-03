<?php
/*
 * EXAMPLE ONLY - add these lines to the EXISTING Reception save/register page
 * immediately AFTER the patient and visit records are successfully inserted.
 *
 * Do not replace the current template or form.
 */

// Existing application values:
$patient_id = $patient_id ?? $new_patient_id ?? 0;
$visit_id = $visit_id ?? $new_visit_id ?? 0;
$payment_mode = $payment_mode ?? ($_POST['payment_mode'] ?? $_POST['mode_of_payment'] ?? '');

// Start NHIF only when Payment Mode is NHIF.
// The included file opens the current Facebox NHIF verification form.
require __DIR__ . '/nhif_after_registration.php';
