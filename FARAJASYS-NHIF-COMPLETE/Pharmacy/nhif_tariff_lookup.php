<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/nhif_bootstrap.php';

$db = nhifDb();
$q = trim((string)($_GET['q'] ?? ''));
$rows = [];

if ($q !== '') {
    $like = '%' . $q . '%';
    $stmt = $db->prepare(
        'SELECT item_code, item_name, package_id, scheme_id, unit_price,
                is_restricted, excluded_products, synced_at
         FROM nhif_tariffs
         WHERE item_code LIKE ? OR item_name LIKE ?
         ORDER BY item_name LIMIT 100'
    );
    $stmt->bind_param('ss', $like, $like);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<div style="min-width:650px;max-width:900px">
<h3>NHIF Tariff / Medicine Lookup</h3>
<form method="get">
<input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Item code or name" style="width:75%">
<button type="submit">Search</button>
</form>
<?php if ($q !== ''): ?>
<table border="1" cellpadding="5" cellspacing="0" style="width:100%;margin-top:10px">
<tr><th>Code</th><th>Item</th><th>Scheme</th><th>NHIF Price</th><th>Restricted</th></tr>
<?php foreach ($rows as $row): ?>
<tr>
<td><?= htmlspecialchars((string)$row['item_code']) ?></td>
<td><?= htmlspecialchars((string)$row['item_name']) ?></td>
<td><?= htmlspecialchars((string)$row['scheme_id']) ?></td>
<td><?= number_format((float)$row['unit_price'], 2) ?></td>
<td><?= (int)$row['is_restricted'] === 1 ? 'YES - Verify approval' : 'No' ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</div>
