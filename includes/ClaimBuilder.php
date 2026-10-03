<?php
declare(strict_types=1);

function nhifUuidV4(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function nhifPatientFileBase64(?string $pdfPath): ?string
{
    if (!$pdfPath || !is_file($pdfPath)) {
        return null;
    }
    $data = file_get_contents($pdfPath);
    return $data === false ? null : base64_encode($data);
}

function nhifBuildFolio(array $folio, array $diseases, array $items): array
{
    $folioId = $folio['FolioID'] ?? nhifUuidV4();
    $folio['FolioID'] = $folioId;

    $folio['FolioDiseases'] = array_map(
        static fn(array $d): array => [
            'FolioDiseaseID' => $d['FolioDiseaseID'] ?? nhifUuidV4(),
            'FolioID' => $folioId,
            'DiseaseCode' => (string)($d['DiseaseCode'] ?? ''),
            'CreatedBy' => (string)($d['CreatedBy'] ?? ($folio['CreatedBy'] ?? 'hospital_user')),
            'DateCreated' => (string)($d['DateCreated'] ?? date('c')),
        ],
        $diseases
    );

    $folio['FolioItems'] = array_map(
        static function (array $i) use ($folioId, $folio): array {
            $qty = (float)($i['ItemQuantity'] ?? 0);
            $price = (float)($i['UnitPrice'] ?? 0);
            return [
                'FolioItemID' => $i['FolioItemID'] ?? nhifUuidV4(),
                'FolioID' => $folioId,
                'ItemCode' => (string)($i['ItemCode'] ?? ''),
                'ItemQuantity' => $qty,
                'UnitPrice' => $price,
                'AmountClaimed' => round($qty * $price, 2),
                'ApprovalRefNo' => $i['ApprovalRefNo'] ?? null,
                'CreatedBy' => (string)($i['CreatedBy'] ?? ($folio['CreatedBy'] ?? 'hospital_user')),
                'DateCreated' => (string)($i['DateCreated'] ?? date('c')),
            ];
        },
        $items
    );

    return $folio;
}
