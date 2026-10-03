<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/nhif_bootstrap.php';

$message = '';
$data = null;

function nhifCollectFolioIds(mixed $node, array &$ids): void
{
    if (!is_array($node)) {
        return;
    }

    foreach (['FolioID','FolioId','folioId','folio_id'] as $key) {
        if (isset($node[$key]) && is_string($node[$key]) && $node[$key] !== '') {
            $ids[] = $node[$key];
        }
    }

    foreach ($node as $value) {
        if (is_array($value)) {
            nhifCollectFolioIds($value, $ids);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $year = (int)($_POST['year'] ?? date('Y'));
        $month = (int)($_POST['month'] ?? date('n'));
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException('Month must be 1-12.');
        }

        $client = new NhifClaimsClient(nhifConfig());
        $response = $client->getSubmittedClaims($year, $month);
        $data = $response['json'] ?? ['raw' => $response['raw']];

        if ($response['status'] >= 200 && $response['status'] < 300 && is_array($response['json'])) {
            $ids = [];
            nhifCollectFolioIds($response['json'], $ids);
            $ids = array_values(array_unique($ids));
            $db = nhifDb();
            $matched = 0;

            $up = $db->prepare(
                "UPDATE nhif_claims
                 SET reconciled_at=NOW(), status='RECONCILED'
                 WHERE folio_id=?"
            );
            foreach ($ids as $folioId) {
                $up->bind_param('s', $folioId);
                $up->execute();
                $matched += $up->affected_rows;
            }

            $message = "Reconciliation received from NHIF. {$matched} local folio(s) matched.";
            nhifAudit('Accounts', 'getSubmittedClaims', true, null, null,
                (int)$response['status'], $message);
        } else {
            throw new RuntimeException('NHIF reconciliation failed: ' . $response['raw']);
        }
    } catch (Throwable $e) {
        $message = $e->getMessage();
        nhifAudit('Accounts', 'getSubmittedClaims', false, null, null, null, $message);
    }
}
?>
<h2>NHIF Claims Reconciliation</h2>
<?php if ($message): ?><p><?= htmlspecialchars($message) ?></p><?php endif; ?>
<form method="post">
<label>Year <input type="number" name="year" value="<?= date('Y') ?>"></label>
<label>Month <input type="number" min="1" max="12" name="month" value="<?= date('n') ?>"></label>
<button type="submit">Reconcile Submitted Claims</button>
</form>
<?php if ($data !== null): ?>
<pre><?= htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)) ?></pre>
<?php endif; ?>
