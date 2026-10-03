<?php
declare(strict_types=1);

namespace Faraja\Nhif;

use RuntimeException;

final class NhifClient
{
    private array $config;
    private array $tokenCache = [];

    public function __construct(array $config)
    {
        $required = ['facility_code', 'username', 'password', 'member_token_url',
            'member_base_url', 'claims_token_url', 'claims_base_url'];

        foreach ($required as $key) {
            if (!array_key_exists($key, $config)) {
                throw new RuntimeException("Missing NHIF configuration key: {$key}");
            }
        }

        $this->config = $config;
    }

    public function authorizeMember(
        string $cardNo,
        int $visitTypeId,
        ?string $referralNo = null,
        string $remarks = ''
    ): array {
        if (!in_array($visitTypeId, [1, 2, 3, 4], true)) {
            throw new RuntimeException('NHIF VisitTypeID must be 1, 2, 3 or 4.');
        }

        $query = http_build_query([
            'CardNo' => $cardNo,
            'VisitTypeID' => $visitTypeId,
            'ReferralNo' => $referralNo ?? '',
            'Remarks' => $remarks,
        ]);

        return $this->request(
            'member',
            'GET',
            'verification/AuthorizeCard?' . $query
        );
    }

    public function getCardDetails(string $cardNo): array
    {
        return $this->request(
            'member',
            'GET',
            'verification/GetCardDetails?' . http_build_query(['CardNo' => $cardNo])
        );
    }

    public function getTariffs(bool $includeExcludedServices = true): array
    {
        $endpoint = $includeExcludedServices
            ? 'Packages/GetPricePackageWithExcludedServices'
            : 'Packages/GetPricePackage';

        return $this->request(
            'claims',
            'GET',
            $endpoint . '?' . http_build_query([
                'FacilityCode' => $this->config['facility_code'],
            ])
        );
    }

    public function verifyPreApproval(
        string $cardNo,
        string $referenceNo,
        string $itemCode
    ): array {
        $query = http_build_query([
            'CardNo' => $cardNo,
            'ReferenceNo' => $referenceNo,
            'ItemCode' => $itemCode,
        ]);

        return $this->request(
            'member',
            'GET',
            'verification/GetReferenceNoStatus?' . $query
        );
    }

    public function referPatient(array $payload): array
    {
        return $this->request('member', 'POST', 'verification/AddReferral', $payload);
    }

    public function submitFolios(array $folios): array
    {
        $entities = array_is_list($folios) ? $folios : [$folios];

        return $this->request(
            'claims',
            'POST',
            'Claims/SubmitFolios',
            ['entities' => $entities]
        );
    }

    private function request(
        string $scope,
        string $method,
        string $endpoint,
        ?array $payload = null
    ): array {
        $baseUrl = $scope === 'claims'
            ? $this->config['claims_base_url']
            : $this->config['member_base_url'];

        $url = rtrim((string)$baseUrl, '/') . '/' . ltrim($endpoint, '/');
        $token = $this->getToken($scope);

        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $token,
        ];

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Unable to initialize cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => (int)($this->config['connect_timeout'] ?? 15),
            CURLOPT_TIMEOUT => (int)($this->config['request_timeout'] ?? 60),
            CURLOPT_SSL_VERIFYPEER => (bool)($this->config['verify_tls'] ?? true),
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if (strtoupper($method) === 'POST') {
            $body = json_encode($payload ?? [], JSON_THROW_ON_ERROR);
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $raw = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $error !== '') {
            throw new RuntimeException('NHIF connection error: ' . $error);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException("NHIF returned non-JSON response (HTTP {$httpCode}).");
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException(
                'NHIF API error HTTP ' . $httpCode . ': ' . json_encode($decoded)
            );
        }

        return $decoded;
    }

    private function getToken(string $scope): string
    {
        if (isset($this->tokenCache[$scope]['token'], $this->tokenCache[$scope]['expires'])
            && $this->tokenCache[$scope]['expires'] > time() + 30) {
            return $this->tokenCache[$scope]['token'];
        }

        $tokenUrl = $scope === 'claims'
            ? $this->config['claims_token_url']
            : $this->config['member_token_url'];

        $ch = curl_init((string)$tokenUrl);
        if ($ch === false) {
            throw new RuntimeException('Unable to initialize NHIF token request.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'password',
                'username' => (string)$this->config['username'],
                'password' => (string)$this->config['password'],
            ]),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_CONNECTTIMEOUT => (int)($this->config['connect_timeout'] ?? 15),
            CURLOPT_TIMEOUT => (int)($this->config['request_timeout'] ?? 60),
            CURLOPT_SSL_VERIFYPEER => (bool)($this->config['verify_tls'] ?? true),
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $raw = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $error !== '') {
            throw new RuntimeException('NHIF token error: ' . $error);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || empty($decoded['access_token'])) {
            throw new RuntimeException(
                "NHIF token request failed (HTTP {$httpCode})."
            );
        }

        $expiresIn = max(60, (int)($decoded['expires_in'] ?? 300));
        $this->tokenCache[$scope] = [
            'token' => (string)$decoded['access_token'],
            'expires' => time() + $expiresIn,
        ];

        return $this->tokenCache[$scope]['token'];
    }
}
