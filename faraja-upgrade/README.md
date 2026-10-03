# Faraja PHP 8.5 + NHIF/eClaims upgrade layer

This branch contains the completed reusable modernization layer created for the
Faraja hospital project.

## Included

- PHP 8.5 Composer baseline
- Centralized NHIF token/API client
- NHIF beneficiary authorization
- Card details support
- NHIF tariff and excluded-service download
- Restricted/pre-approved service verification
- NHIF referral submission
- eClaims folio builder
- eClaims submission
- SQL schema for authorizations, tariffs, excluded services, approvals, referrals,
  claims, diseases, claim items and API logs
- Department integration guidance for Reception, Doctor, Pharmacy and Admin
- Facebox-preservation guidance

## Important source limitation

The original Faraja source archive was uploaded to ChatGPT as a binary ZIP.
The available GitHub connector cannot unpack that chat attachment, so the existing
Admin/Reception/Doctor/Pharmacy PHP files have NOT yet been mechanically converted
file-by-file in this branch.

Do not replace a production installation with this folder alone. Merge this layer
into the original system after the original source files are available in a code
workspace or repository.

## Installation outline

1. Run on a copy of the Faraja system/database.
2. Install PHP 8.5 with cURL, JSON and MySQLi.
3. Run `composer install`.
4. Copy `config/nhif.example.php` to a protected `config/nhif.php`.
5. Supply the actual NHIF facility credentials and facility code.
6. Run `database/001_nhif_eclaims.sql`.
7. Map Faraja patient/visit IDs to the new NHIF tables.
8. Integrate the endpoints into the existing Facebox pages.
9. Test against NHIF-approved integration/test access before production submission.

## NHIF source documentation

Implementation is based on the supplied NHIF API documentation for beneficiary
verification, facility tariffs, claim submission, referrals and pre-approved
service verification.

Production endpoint/credential confirmation remains the responsibility of the
facility and NHIF because those values can be changed or provisioned per facility.
