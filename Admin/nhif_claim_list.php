<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/nhif_bootstrap.php';

$db = nhifDb();
$status = strtoupper(trim((string)($_GET['status'] ?? '')));
$params = [];
$sql = 'SELECT id, folio_id, folio_no, serial_no, card_no, claim_year, claim_month,
               status, http_status, submitted_at, reconciled_at, created_at
        FROM nhif_claims';

if ($status !== '') {
    $sql .= ' WHERE status=?';
}
$sql .= ' ORDER BY id DESC LIMIT 500';

$stmt = $db->prepare($sql);
if ($status !== '') {
    $stmt->bind_param('s', $status);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<h2>NHIF Local Claims</h2>
<p>
<a href="?">All</a> |
<a href="?status=DRAFT">Draft</a> |
<a href="?status=SUBMITTED">Submitted</a> |
<a href="?status=RECONCILED">Reconciled</a> |
<a href="?status=ERROR">Errors</a>
</p>
<table border="1" cellpadding="5" cellspacing="0" style="width:100%">
<tr><th>ID</th><th>Folio ID</th><th>Folio No.</th><th>Card</th><th>Period</th><th>Status</th><th>HTTP</th><th>Submitted</th><th>Reconciled</th></tr>
<?php foreach ($rows as $row): ?>
<tr>
<td><?= (int)$row['id'] ?></td>
<td><?= htmlspecialchars((string)$row['folio_id']) ?></td>
<td><?= htmlspecialchars((string)$row['folio_no']) ?></td>
<td><?= htmlspecialchars((string)$row['card_no']) ?></td>
<td><?= (int)$row['claim_month'] ?>/<?= (int)$row['claim_year'] ?></td>
<td><?= htmlspecialchars((string)$row['status']) ?></td>
<td><?= htmlspecialchars((string)$row['http_status']) ?></td>
<td><?= htmlspecialchars((string)$row['submitted_at']) ?></td>
<td><?= htmlspecialchars((string)$row['reconciled_at']) ?></td>
</tr>
<?php endforeach; ?>
</table>
