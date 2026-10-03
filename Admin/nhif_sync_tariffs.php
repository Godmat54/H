<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/nhif_bootstrap.php';

$message = '';
$count = 0;

function nhifCollectTariffRows(mixed $node, array &$rows): void
{
    if (!is_array($node)) {
        return;
    }

    $itemCode = $node['ItemCode'] ?? $node['itemCode'] ?? $node['item_code'] ?? null;
    if ($itemCode !== null) {
        $rows[] = $node;
    }

    foreach ($node as $value) {
        if (is_array($value)) {
            nhifCollectTariffRows($value, $rows);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $client = new NhifClaimsClient(nhifConfig());
        $response = $client->getPricePackage(true);

        if ($response['status'] < 200 || $response['status'] >= 300 || !is_array($response['json'])) {
            throw new RuntimeException('NHIF tariff download failed: ' . $response['raw']);
        }

        $rows = [];
        nhifCollectTariffRows($response['json'], $rows);
        $db = nhifDb();

        $stmt = $db->prepare(
            'INSERT INTO nhif_tariffs
            (item_code, item_name, package_id, scheme_id, unit_price,
             is_restricted, excluded_products, raw_json, synced_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                item_name=VALUES(item_name),
                unit_price=VALUES(unit_price),
                is_restricted=VALUES(is_restricted),
                excluded_products=VALUES(excluded_products),
                raw_json=VALUES(raw_json),
                synced_at=NOW()'
        );

        foreach ($rows as $row) {
            $itemCode = (string)($row['ItemCode'] ?? $row['itemCode'] ?? $row['item_code'] ?? '');
            if ($itemCode === '') {
                continue;
            }

            $itemName = (string)($row['ItemName'] ?? $row['itemName'] ?? $row['Description'] ?? $itemCode);
            $packageId = (int)($row['PackageID'] ?? $row['PackageId'] ?? $row['package_id'] ?? 0);
            $schemeId = (int)($row['SchemeID'] ?? $row['SchemeId'] ?? $row['scheme_id'] ?? 0);
            $unitPrice = (float)($row['UnitPrice'] ?? $row['Price'] ?? $row['unit_price'] ?? 0);
            $isRestricted = !empty($row['IsRestricted']) || !empty($row['is_restricted']) ? 1 : 0;

            $excluded = $row['ExcludedServices']
                ?? $row['ExcludedProducts']
                ?? $row['excluded_services']
                ?? null;
            $excludedJson = $excluded === null ? null : nhifJson($excluded);
            $rawJson = nhifJson($row);

            $stmt->bind_param(
                'ssiidiss',
                $itemCode,
                $itemName,
                $packageId,
                $schemeId,
                $unitPrice,
                $isRestricted,
                $excludedJson,
                $rawJson
            );
            $stmt->execute();
            $count++;
        }

        $message = "NHIF tariff synchronization completed. {$count} tariff rows processed.";
        nhifAudit('Admin', 'GetPricePackageWithExcludedServices', true, null, null,
            (int)$response['status'], $message);
    } catch (Throwable $e) {
        $message = $e->getMessage();
        nhifAudit('Admin', 'GetPricePackageWithExcludedServices', false, null, null, null, $message);
    }
}
?>
<h2>NHIF Tariff Synchronization</h2>
<?php if ($message): ?><p><?= htmlspecialchars($message) ?></p><?php endif; ?>
<p>This downloads the facility price package and excluded-service information from NHIF.</p>
<form method="post">
    <button type="submit">Synchronize NHIF Tariffs</button>
</form>
