# FARAJASYS NHIF / eClaims Complete Integration Project

This project is built from the uploaded NHIF API Integration Guide | PHP 8.1.

## Runtime

- PHP 8.1 or newer; the code is written for current PHP 8.x.
- cURL, JSON and MySQLi.
- MySQL or MariaDB.
- Existing Faraja Facebox/JQuery assets when embedded in the original hospital template.

## Included workflow

Reception:
- NHIF member authorization.
- Normal, Emergency, Referral and Follow-up visit types.
- Store AuthorizationStatus and AuthorizationNo.
- Card-details lookup.
- Facebox-ready pages.

Doctor:
- Restricted-service pre-approval verification.
- NHIF patient referral.
- Diagnosis/referral information captured in the hospital application.
- Facebox-ready pages.

Pharmacy:
- Search synchronized NHIF ItemCode and ItemName.
- Display NHIF UnitPrice.
- Show restricted-service indicator.
- Designed for the existing dispensing workflow.

Admin / Accounts:
- NHIF/eClaims dashboard.
- Tariff and excluded-service synchronization.
- Build claim folio.
- FolioDisease and FolioItem creation.
- PDF patient-file Base64 attachment.
- SubmitFolios.
- Local claim list/status.
- Monthly getSubmittedClaims reconciliation.
- Audit log.

## Installation

1. Back up the existing Faraja database and project.
2. Use PHP 8.1+ with curl, json and mysqli.
3. Configure the database using the variables shown in .env.example.
4. Securely configure NHIF_USERNAME, NHIF_PASSWORD and NHIF_FACILITY_CODE.
5. Never put production NHIF passwords in JavaScript, Git or printed reports.
6. Run: php install.php
7. Add the links in integration/FACEBOX_LINKS.html to the existing Reception, Doctor, Pharmacy and Admin menus.
8. Replace generic patient_id, visit_id and session-user mappings with the exact variables/keys from the existing Faraja project.
9. Synchronize the current NHIF tariffs.
10. Use NHIF test/UAT credentials before production.

## Existing-template integration

The integration pages intentionally do not load a replacement hospital theme.
When opened using the existing Faraja rel="facebox" links, they render inside the
current Facebox popup so the original project styling/navigation can remain.

Example link:
<a rel="facebox" href="../Reception/nhif_verify.php?patient_id=PATIENT_ID&visit_id=VISIT_ID">NHIF Verify</a>

## Claims structure

The claim page constructs FolioID, FacilityCode, ClaimYear, ClaimMonth, FolioNo,
SerialNo, CardNo, patient demographics, AuthorizationNo, AttendanceDate,
PatientTypeCode, admission/discharge fields, PractitionerNo, PatientFile as a
Base64 PDF, FolioDiseases and FolioItems with ItemCode, ItemQuantity, UnitPrice,
AmountClaimed and ApprovalRefNo.

## Security

- All NHIF calls are server-side.
- TLS peer/host verification remains enabled.
- Credentials are loaded from environment configuration.
- Local SQL writes use prepared statements.
- API actions are written to nhif_audit_log.
- Do not log passwords or bearer tokens.
- Add the existing Faraja role/permission checks around supplied department pages.

## Important production note

The uploaded implementation guide states that it is not an NHIF-issued credential
pack. Before live deployment, obtain current facility credentials, facility code,
production approval, current endpoint requirements, official sample claim JSON
and response examples, diagnosis coding requirements, serial/folio numbering
rules and any UAT/VPN/IP-whitelisting requirements directly from NHIF.

The guide also states that its public source documentation is marked 2021.
Treat this package as the hospital-system implementation foundation and align it
with the latest NHIF facility pack before production use.
