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

## Professional NHIF workflow used in this package

1. Reception registers the patient normally.
2. When Payment Mode = NHIF, Reception/nhif_after_registration.php starts an NHIF workflow for that visit and opens the existing Facebox NHIF verification form.
3. Accepted verification stores the Authorization Number and changes the visit to AUTHORIZED.
4. Doctor/nhif_encounter.php maintains NHIF patient details and diagnosis on that same visit.
5. Pharmacy, Lab, Dental and Eyes each use their nhif_encounter.php page to record synchronized NHIF ItemCodes, quantities, NHIF prices and approval references.
6. Restricted items are blocked until their approval reference has been verified as VALID.
7. Admin/nhif_finalize_claim.php automatically assembles the complete visit into the final folio using the patient profile, authorization, diagnoses and all recorded departmental services.
8. Admin submits the eClaim and later reconciles it from the Admin NHIF dashboard.

The package also includes integration/RECEPTION_AFTER_SAVE_EXAMPLE.php showing the small include that should be added after the existing Reception registration save succeeds.

## Important source-merge note

The uploaded FDSYS.zip is mounted in this conversation, but the local Python/container execution service returned a ClientError before it could list or extract the archive. Therefore this branch contains the complete drop-in NHIF/PHP 8.1 integration package and merge hooks, but the original FDSYS PHP files themselves have not been mechanically rewritten line-by-line in this environment. Do not interpret this package as proof that every legacy FDSYS page has already been linted under PHP 8.1.
