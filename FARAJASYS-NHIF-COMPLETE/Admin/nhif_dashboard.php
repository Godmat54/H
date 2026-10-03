<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/nhif_bootstrap.php';

$db = nhifDb();

function nhifCount(mysqli $db, string $sql): int
{
    $r = $db->query($sql);
    return (int)($r->fetch_row()[0] ?? 0);
}

$stats = [
    'Verifications Today' => nhifCount($db, "SELECT COUNT(*) FROM nhif_verifications WHERE DATE(verified_at)=CURDATE()"),
    'Tariff Items' => nhifCount($db, "SELECT COUNT(*) FROM nhif_tariffs"),
    'Draft Claims' => nhifCount($db, "SELECT COUNT(*) FROM nhif_claims WHERE status='DRAFT'"),
    'Submitted Claims' => nhifCount($db, "SELECT COUNT(*) FROM nhif_claims WHERE status='SUBMITTED'"),
    'Reconciled Claims' => nhifCount($db, "SELECT COUNT(*) FROM nhif_claims WHERE status='RECONCILED'"),
];

$logs = $db->query(
    'SELECT department, action_name, success, message, created_at
     FROM nhif_audit_log ORDER BY id DESC LIMIT 25'
)->fetch_all(MYSQLI_ASSOC);
?>
<h2>NHIF / eClaims Dashboard</h2>
<div style="display:flex;gap:10px;flex-wrap:wrap">
<?php foreach ($stats as $label => $value): ?>
<div style="border:1px solid #ccc;padding:15px;min-width:150px">
<strong><?= htmlspecialchars($label) ?></strong><br>
<span style="font-size:24px"><?= $value ?></span>
</div>
<?php endforeach; ?>
</div>

<p style="margin-top:20px">
<a href="nhif_sync_tariffs.php">Synchronize Tariffs</a> |
<a href="nhif_claim.php">Create/Submit Claim</a> |
<a href="nhif_reconcile.php">Reconcile Claims</a>
</p>

<h3>Recent NHIF Audit Activity</h3>
<table border="1" cellpadding="5" cellspacing="0" style="width:100%">
<tr><th>Date</th><th>Department</th><th>Action</th><th>Status</th><th>Message</th></tr>
<?php foreach ($logs as $log): ?>
<tr>
<td><?= htmlspecialchars((string)$log['created_at']) ?></td>
<td><?= htmlspecialchars((string)$log['department']) ?></td>
<td><?= htmlspecialchars((string)$log['action_name']) ?></td>
<td><?= (int)$log['success'] === 1 ? 'OK' : 'FAILED' ?></td>
<td><?= htmlspecialchars((string)$log['message']) ?></td>
</tr>
<?php endforeach; ?>
</table>
