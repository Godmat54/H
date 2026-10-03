<?php
declare(strict_types=1);
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Faraja NHIF Integration</title>
<style>
body{font-family:Arial,sans-serif;margin:30px;line-height:1.5}
.card{border:1px solid #ddd;padding:18px;margin:12px 0;border-radius:6px}
a{margin-right:12px}
</style>
</head>
<body>
<h1>Faraja Hospital NHIF / eClaims Integration</h1>
<div class="card">
<h3>Reception</h3>
<a href="Reception/nhif_verify.php">Member Verification</a>
<a href="Reception/nhif_card_details.php">Card Details</a>
</div>
<div class="card">
<h3>Doctor</h3>
<a href="Doctor/nhif_preapproval.php">Pre-Approval</a>
<a href="Doctor/nhif_referral.php">Referral</a>
</div>
<div class="card">
<h3>Pharmacy</h3>
<a href="Pharmacy/nhif_tariff_lookup.php">Tariff Lookup</a>
</div>
<div class="card">
<h3>Admin / Accounts</h3>
<a href="Admin/nhif_dashboard.php">Dashboard</a>
<a href="Admin/nhif_sync_tariffs.php">Tariff Sync</a>
<a href="Admin/nhif_claim.php">eClaim</a>
<a href="Admin/nhif_claim_list.php">Claims</a>
<a href="Admin/nhif_reconcile.php">Reconciliation</a>
<a href="Admin/nhif_audit_log.php">Audit Log</a>
</div>
</body>
</html>
