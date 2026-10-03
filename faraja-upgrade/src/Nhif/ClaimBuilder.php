<?php
declare(strict_types=1);

namespace Faraja\Nhif;

use InvalidArgumentException;

final class ClaimBuilder
{
    public static function build(array $claim, array $diseases, array $items): array
    {
        foreach ([
            'FolioID', 'FacilityCode', 'ClaimYear', 'ClaimMonth', 'FolioNo',
            'CardNo', 'AuthorizationNo', 'AttendanceDate', 'PatientTypeCode',
            'PractitionerNo'
        ] as $required) {
            if (!isset($claim[$required]) || $claim[$required] === '') {
                throw new InvalidArgumentException("Missing NHIF folio field: {$required}");
            }
        }

        if (!in_array($claim['PatientTypeCode'], ['OUT', 'IN'], true)) {
            throw new InvalidArgumentException('PatientTypeCode must be OUT or IN.');
        }

        $claim['FolioDiseases'] = array_values(array_map(
            static fn(array $d): array => [
                'FolioDiseaseID' => $d['FolioDiseaseID'] ?? self::uuidV4(),
                'FolioID' => $claim['FolioID'],
                'DiseaseCode' => (string)($d['DiseaseCode'] ?? ''),
                'Remarks' => $d['Remarks'] ?? null,
                'CreatedBy' => $d['CreatedBy'] ?? ($claim['CreatedBy'] ?? 'System'),
                'DateCreated' => $d['DateCreated'] ?? date(DATE_ATOM),
            ],
            $diseases
        ));

        $claim['FolioItems'] = array_values(array_map(
            static function (array $item) use ($claim): array {
                $quantity = (float)($item['ItemQuantity'] ?? 0);
                $unitPrice = (float)($item['UnitPrice'] ?? 0);

                if ($quantity <= 0 || $unitPrice < 0) {
                    throw new InvalidArgumentException('Invalid NHIF claim quantity or unit price.');
                }

                return [
                    'FolioItemID' => $item['FolioItemID'] ?? self::uuidV4(),
                    'FolioID' => $claim['FolioID'],
                    'ItemCode' => (string)($item['ItemCode'] ?? ''),
                    'OtherDetails' => $item['OtherDetails'] ?? null,
                    'ItemQuantity' => $quantity,
                    'UnitPrice' => $unitPrice,
                    'AmountClaimed' => round($quantity * $unitPrice, 2),
                    'ApprovalRefNo' => $item['ApprovalRefNo'] ?? null,
                    'CreatedBy' => $item['CreatedBy'] ?? ($claim['CreatedBy'] ?? 'System'),
                    'DateCreated' => $item['DateCreated'] ?? date(DATE_ATOM),
                ];
            },
            $items
        ));

        return $claim;
    }

    public static function encodePatientPdf(string $pdfPath): string
    {
        if (!is_file($pdfPath) || !is_readable($pdfPath)) {
            throw new InvalidArgumentException('Patient treatment PDF cannot be read.');
        }

        $contents = file_get_contents($pdfPath);
        if ($contents === false) {
            throw new InvalidArgumentException('Unable to read patient treatment PDF.');
        }

        return base64_encode($contents);
    }

    public static function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
