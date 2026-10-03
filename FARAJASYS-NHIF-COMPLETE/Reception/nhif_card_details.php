<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/nhif_bootstrap.php';

$message = '';
$data = null;
$cardNo = trim((string)($_POST['card_no'] ?? $_GET['card_no'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $cardNo !== '') {
    try {
        $client = new NhifServiceClient(nhifConfig());
        $response = $client->getCardDetails($cardNo);
        $data = $response['json'] ?? ['raw' => $response['raw']];
        $message = 'NHIF card details retrieved.';
        nhifAudit('Reception', 'GetCardDetails',
            $response['status'] >= 200 && $response['status'] < 300,
            null, null, (int)$response['status'], $message);
    } catch (Throwable $e) {
        $message = $e->getMessage();
        nhifAudit('Reception', 'GetCardDetails', false, null, null, null, $message);
    }
}
?>
<div style="min-width:500px;max-width:760px">
<h3>NHIF Card Details</h3>
<?php if ($message): ?><p><?= htmlspecialchars($message) ?></p><?php endif; ?>
<form method="post">
<label>NHIF Card Number<br>
<input name="card_no" value="<?= htmlspecialchars($cardNo) ?>" required style="width:100%">
</label>
<p><button type="submit">Get Card Details</button></p>
</form>
<?php if ($data !== null): ?>
<pre style="white-space:pre-wrap"><?= htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)) ?></pre>
<?php endif; ?>
</div>
