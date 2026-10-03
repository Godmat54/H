# FARAJASYS NHIF / eClaims Integration - PHP 8.1.25

Target runtime: PHP 8.1.25.

This package follows the NHIF integration flow supplied for the hospital system and keeps the integration server-side so the existing Faraja template and Facebox popup forms can remain in use.

## Covered departments

- Admin / Accounts
- Reception
- Doctor
- Pharmacy
- Laboratory (Lab folder)
- Dental
- Eyes

## Reception

- NHIF member authorization.
- Normal, Emergency, Referral and Follow-up visit types.
- Stores AuthorizationStatus, AuthorizationNo, SchemeID and ProductCode.
- NHIF card-details lookup.
- Facebox-ready pages.

## Doctor

- Uses the patient's NHIF authorization.
- Restricted-service pre-approval verification.
- NHIF patient referral.
- Diagnosis/referral information linked to the visit.
- Facebox-ready pages.

## Pharmacy, Laboratory, Dental and Eyes

Each department uses synchronized NHIF tariff data and the same patient authorization already created at Reception.

The package provides:
- NHIF ItemCode / ItemName lookup.
- NHIF UnitPrice.
- Scheme information.
- Restricted-service indicator.
- Approval-reference verification.
- Audit logging by department.
- nhif_service_mappings for mapping local service codes to NHIF ItemCode.

## Admin / Accounts

- NHIF/eClaims dashboard.
- Tariff and excluded-service synchronization.
- Claim folio construction.
- FolioDisease and FolioItem rows.
- Patient PDF Base64 attachment.
- SubmitFolios.
- Local claim status list.
- Monthly getSubmittedClaims reconciliation.
- Audit log.

## Installation

1. Back up the existing Faraja project and database.
2. Install/use PHP 8.1.25 with curl, json and mysqli enabled.
3. Configure the database values shown in .env.example.
4. Securely configure NHIF_USERNAME, NHIF_PASSWORD and NHIF_FACILITY_CODE.
5. Run: php install.php
6. Add the Facebox/menu snippets in integration/FACEBOX_LINKS.html.
7. Replace generic patient_id, visit_id and session-user mappings with the exact variables already used by the existing Faraja system.
8. Synchronize NHIF tariffs from Admin.
9. Map existing local services/medicines/tests/procedures to NHIF ItemCode using nhif_service_mappings.
10. Test with NHIF-approved UAT/test credentials before production.

## Existing template and Facebox

The integration pages deliberately do not introduce a replacement hospital theme. Open them using the existing rel="facebox" links so forms continue to appear in the current popup style.

## PHP 8.1.25 migration of old pages

The original legacy pages should be checked for removed/deprecated PHP constructs before switching the hospital server:

- mysql_* calls should be converted to MySQLi/PDO or a controlled compatibility layer.
- old-style constructors.
- each(), create_function(), ereg*.
- unquoted array indexes.
- curly-brace string/array offsets.
- direct SQL concatenation from request data.
- deprecated/undefined variable assumptions.

Do not redesign the HTML/CSS template just to modernize PHP. Convert the shared database/runtime layer first and keep existing Facebox JavaScript/CSS.

## Security

- NHIF API calls remain server-side.
- TLS certificate verification remains enabled.
- NHIF passwords are not placed in JavaScript or source-control examples.
- Local inserts/updates use prepared statements.
- API operations are written to nhif_audit_log.
- Apply the existing Faraja role/session permission checks to the supplied pages.

## Production note

Before go-live, confirm the current NHIF facility credentials, facility code, production endpoint/schema pack, diagnosis-code requirements, serial/folio rules, UAT access and any VPN/IP-whitelisting requirements directly with NHIF.