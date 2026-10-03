<?php
declare(strict_types=1);

/**
 * Copy this file OUTSIDE the public web root and fill values supplied by NHIF.
 * Never commit real credentials to Git.
 */
return [
    'facility_code' => getenv('NHIF_FACILITY_CODE') ?: '',
    'username' => getenv('NHIF_USERNAME') ?: '',
    'password' => getenv('NHIF_PASSWORD') ?: '',

    // Keep URLs configurable because NHIF may change endpoints.
    'member_token_url' => getenv('NHIF_MEMBER_TOKEN_URL')
        ?: 'https://verification.nhif.or.tz/nhifservice/Token',
    'member_base_url' => getenv('NHIF_MEMBER_BASE_URL')
        ?: 'https://verification.nhif.or.tz/nhifservice/breeze/',

    'claims_token_url' => getenv('NHIF_CLAIMS_TOKEN_URL')
        ?: 'https://verification.nhif.or.tz/claimsserver/Token',
    'claims_base_url' => getenv('NHIF_CLAIMS_BASE_URL')
        ?: 'https://verification.nhif.or.tz/claimsserver/api/v1/',

    'connect_timeout' => 15,
    'request_timeout' => 60,
    'verify_tls' => true,
];
