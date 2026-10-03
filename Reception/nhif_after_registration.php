<?php
declare(strict_types=1);

/**
 * DROP-IN RECEPTION HOOK
 *
 * Include this immediately AFTER the existing patient registration/visit record
 * has been saved, when the existing code already knows:
 *   $patient_id
 *   $visit_id
 *   $payment_mode
 *
 * This file does not change the current Faraja HTML template.
 */

require_once dirname(__DIR__) . '/includes/NhifWorkflow.php';

$farajaPatientId = isset($patient_id) ? (int)$patient_id : (int)($_POST['patient_id'] ?? 0);
$farajaVisitId = isset($visit_id) ? (int)$visit_id : (int)($_POST['visit_id'] ?? 0);
$farajaPaymentMode = isset($payment_mode)
    ? (string)$payment_mode
    : (string)($_POST['payment_mode'] ?? $_POST['mode_of_payment'] ?? '');

if ($farajaPatientId > 0 && $farajaVisitId > 0
    && nhifStartWorkflow($farajaPatientId, $farajaVisitId, $farajaPaymentMode)) {

    $nhifUrl = 'nhif_verify.php?patient_id=' . rawurlencode((string)$farajaPatientId)
        . '&visit_id=' . rawurlencode((string)$farajaVisitId);

    /*
     * If Facebox is loaded in the current Reception template, automatically open
     * NHIF verification after successful registration.
     * Otherwise the browser follows the NHIF verification URL normally.
     */
    echo '<a id="faraja-nhif-auto-open" rel="facebox" href="'
        . htmlspecialchars($nhifUrl, ENT_QUOTES, 'UTF-8')
        . '" style="display:none">NHIF Verification</a>';

    echo '<script>
    (function () {
        var link = document.getElementById("faraja-nhif-auto-open");
        if (!link) return;

        if (window.jQuery && jQuery.fn && jQuery.fn.facebox) {
            jQuery(link).facebox();
            setTimeout(function () { jQuery(link).trigger("click"); }, 50);
        } else {
            window.location.href = link.href;
        }
    })();
    </script>';
}
