<?php
declare(strict_types=1);

return [
    'service_token_url' => getenv('NHIF_SERVICE_TOKEN_URL')
        ?: 'https://verification.nhif.or.tz/nhifservice/Token',
    'service_base_url' => getenv('NHIF_SERVICE_BASE_URL')
        ?: 'https://verification.nhif.or.tz/nhifservice/breeze/verification/',
    'claims_token_url' => getenv('NHIF_CLAIMS_TOKEN_URL')
        ?: 'https://verification.nhif.or.tz/claimsserver/Token',
    'claims_base_url' => getenv('NHIF_CLAIMS_BASE_URL')
        ?: 'https://verification.nhif.or.tz/claimsserver/api/v1/',
    'username' => getenv('NHIF_USERNAME') ?: '',
    'password' => getenv('NHIF_PASSWORD') ?: '',
    'facility_code' => getenv('NHIF_FACILITY_CODE') ?: '',
    'timeout' => 45,
    'verify_tls' => true,
];
