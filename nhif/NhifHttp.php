<?php
declare(strict_types=1);

final class NhifHttp
{
    public function __construct(
        private int $timeout = 45,
        private bool $verifyTls = true
    ) {}

    public function request(
        string $method,
        string $url,
        array|string|null $body = null,
        array $headers = []
    ): array {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Unable to initialize cURL');
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => $this->verifyTls,
            CURLOPT_SSL_VERIFYHOST => $this->verifyTls ? 2 : 0,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = is_array($body)
                ? http_build_query($body)
                : $body;
        }

        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new RuntimeException("NHIF transport error: {$error}");
        }

        $json = json_decode((string)$raw, true);

        return [
            'status' => $status,
            'raw' => (string)$raw,
            'json' => is_array($json) ? $json : null,
        ];
    }
}
