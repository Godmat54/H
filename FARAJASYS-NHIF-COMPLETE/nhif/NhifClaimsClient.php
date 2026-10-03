<?php
declare(strict_types=1);

require_once __DIR__ . '/NhifHttp.php';

final class NhifClaimsClient
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

        $r = $this->http->request('POST', $this->config['claims_token_url'], [
            'grant_type' => 'password',
            'username' => $this->config['username'],
            'password' => $this->config['password'],
        ], ['Content-Type: application/x-www-form-urlencoded']);

        $token = $r['json']['access_token'] ?? null;
        if (!$token) {
            throw new RuntimeException('NHIF claims token failed: ' . $r['raw']);
        }

        return $this->cachedToken = (string)$token;
    }

    private function headers(bool $json = false): array
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

    public function getPricePackage(bool $withExcludedServices = true): array
    {
        $path = $withExcludedServices
            ? 'Packages/GetPricePackageWithExcludedServices'
            : 'Packages/GetPricePackage';

        $url = $this->config['claims_base_url'] . $path . '?' .
            http_build_query(['FacilityCode' => $this->config['facility_code']]);

        return $this->http->request('GET', $url, null, $this->headers());
    }

    public function submitFolios(array $folios): array
    {
        return $this->http->request(
            'POST',
            $this->config['claims_base_url'] . 'Claims/SubmitFolios',
            json_encode(array_values($folios), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $this->headers(true)
        );
    }

    public function getSubmittedClaims(int $year, int $month): array
    {
        $url = $this->config['claims_base_url'] . 'claims/getSubmittedClaims?' .
            http_build_query([
                'FacilityCode' => $this->config['facility_code'],
                'ClaimYear' => $year,
                'ClaimMonth' => $month,
            ]);

        return $this->http->request('GET', $url, null, $this->headers());
    }
}
