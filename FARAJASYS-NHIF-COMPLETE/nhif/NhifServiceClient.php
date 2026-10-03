<?php
declare(strict_types=1);

require_once __DIR__ . '/NhifHttp.php';

final class NhifServiceClient
{
    private NhifHttp $http;
    private ?string $cachedToken = null;

    public function __construct(private array $config)
    {
        $this->http = new NhifHttp(
            (int)($config['timeout'] ?? 45),
            (bool)($config['verify_tls'] ?? true)
        );
    }

    private function token(): string
    {
        if ($this->cachedToken !== null) {
            return $this->cachedToken;
        }

        $r = $this->http->request('POST', $this->config['service_token_url'], [
            'grant_type' => 'password',
            'username' => $this->config['username'],
            'password' => $this->config['password'],
        ], ['Content-Type: application/x-www-form-urlencoded']);

        $token = $r['json']['access_token'] ?? null;
        if (!$token) {
            throw new RuntimeException('NHIF service token failed: ' . $r['raw']);
        }

        return $this->cachedToken = (string)$token;
    }

    private function authHeaders(bool $json = false): array
    {
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->token(),
        ];
        if ($json) {
            $headers[] = 'Content-Type: application/json';
        }
        return $headers;
    }

    public function authorizeCard(
        string $cardNo,
        int $visitTypeId = 1,
        string $referralNo = '',
        string $remarks = ''
    ): array {
        $query = http_build_query([
            'CardNo' => $cardNo,
            'VisitTypeID' => $visitTypeId,
            'ReferralNo' => $referralNo,
            'Remarks' => $remarks,
        ]);

        return $this->http->request(
            'GET',
            $this->config['service_base_url'] . 'AuthorizeCard?' . $query,
            null,
            $this->authHeaders()
        );
    }

    public function getCardDetails(string $cardNo): array
    {
        $url = $this->config['service_base_url'] . 'GetCardDetails?' .
            http_build_query(['CardNo' => $cardNo]);

        return $this->http->request('GET', $url, null, $this->authHeaders());
    }

    public function verifyPreApproval(
        string $cardNo,
        string $referenceNo,
        string $itemCode
    ): array {
        $url = $this->config['service_base_url'] . 'GetReferenceNoStatus?' .
            http_build_query([
                'CardNo' => $cardNo,
                'ReferenceNo' => $referenceNo,
                'ItemCode' => $itemCode,
            ]);

        return $this->http->request('GET', $url, null, $this->authHeaders());
    }

    public function addReferral(array $payload): array
    {
        return $this->http->request(
            'POST',
            $this->config['service_base_url'] . 'AddReferral',
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $this->authHeaders(true)
        );
    }
}
