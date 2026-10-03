<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/nhif_bootstrap.php';

$db = nhifDb();
$rows = $db->query(
    'SELECT id, user_id, department, patient_id, visit_id, action_name,
            http_status, success, message, created_at
     FROM nhif_audit_log
     ORDER BY id DESC LIMIT 1000'
)->fetch_all(MYSQLI_ASSOC);
?>
<h2>NHIF Audit Log</h2>
<table border="1" cellpadding="5" cellspacing="0" style="width:100%">
<tr><th>Date</th><th>User</th><th>Department</th><th>Patient</th><th>Visit</th><th>Action</th><th>HTTP</th><th>Result</th><th>Message</th></tr>
<?php foreach ($rows as $row): ?>
<tr>
<td><?= htmlspecialchars((string)$row['created_at']) ?></td>
<td><?= htmlspecialchars((string)$row['user_id']) ?></td>
<td><?= htmlspecialchars((string)$row['department']) ?></td>
<td><?= htmlspecialchars((string)$row['patient_id']) ?></td>
<td><?= htmlspecialchars((string)$row['visit_id']) ?></td>
<td><?= htmlspecialchars((string)$row['action_name']) ?></td>
<td><?= htmlspecialchars((string)$row['http_status']) ?></td>
<td><?= (int)$row['success'] === 1 ? 'OK' : 'FAILED' ?></td>
<td><?= htmlspecialchars((string)$row['message']) ?></td>
</tr>
<?php endforeach; ?>
</table>
