# Faraja Hospital NHIF integration map

Target runtime: PHP 8.5+

The existing Faraja HTML/CSS/template and Facebox popup behavior should remain unchanged.
Only backend/database calls and the pages required for NHIF/eClaims should be replaced or added.

## Reception

Add an NHIF button/link beside the existing patient registration/visit controls.

Suggested Facebox link:

```html
<a href="../nhif/verify_member_form.php?patient_id=PATIENT_ID"
   rel="facebox"
   class="btn btn-info">
   NHIF Verify
</a>
```

Reception workflow:

1. Enter/confirm NHIF card number.
2. Select visit type: Normal, Emergency, Referral, or Follow-up.
3. For Referral/Follow-up, capture ReferralNo.
4. Call `public/nhif/authorize.php`.
5. Save the complete response to `nhif_authorizations`.
6. Only mark the visit NHIF-authorized when `AuthorizationStatus` is ACCEPTED.
7. Carry `AuthorizationNo`, `SchemeID`, and `ProductCode` through the visit.

## Doctor

The doctor patient page should display:

- Card number
- Authorization status/number
- Scheme/Product
- NHIF diagnosis code
- Practitioner registration number
- NHIF-covered services ordered
- Restricted-service approval reference where required

Use `public/nhif/preapproval.php` before ordering a restricted tariff item.

Use `public/nhif/referral.php` to create an NHIF referral while keeping the existing
Facebox add/edit presentation.

## Pharmacy

When dispensing to an NHIF patient:

1. Match the medicine/service to `nhif_tariffs.ItemCode`.
2. Confirm the patient's SchemeID/ProductCode is eligible.
3. Reject an excluded service for that product.
4. If `is_restricted=1`, require a VALID preapproval reference.
5. Save quantity, NHIF UnitPrice, amount and approval reference into
   `nhif_claim_items`.
6. Do not allow local selling price to replace the NHIF tariff in the eClaim.

The pharmacy UI can remain unchanged; only add NHIF status/item-code information
and validation to the existing Facebox forms.

## Admin

Admin should contain a new NHIF/eClaims menu with:

- NHIF settings/status (credentials must not be displayed after saving)
- Download/synchronize tariffs
- Excluded services
- Authorization log
- Referrals
- Draft claims
- Ready claims
- Submitted claims
- Rejected/error claims
- API audit log

The final existing-system integration should map the old Faraja database IDs to
the nullable `patient_id` and `visit_id` columns supplied by the migration.

## eClaims

The claim must be assembled from the entire visit, not just one department:

- Reception: beneficiary/authorization and attendance data
- Doctor: diagnoses and practitioner details
- Laboratory/radiology/other departments: issued NHIF service items
- Pharmacy: medicine items and quantities
- Admin/accounts: folio/serial number and final submission

A treatment PDF is encoded as Base64 into NHIF `PatientFile`.

Use `ClaimBuilder` to calculate item totals server-side and
`NhifClient::submitFolios()` for submission.

## Security

- Do not commit NHIF usernames/passwords.
- Keep the real `config/nhif.php` outside the public web root.
- Use HTTPS with certificate verification enabled.
- Add existing Faraja authentication/role checks to every endpoint.
- Use prepared statements for all local SQL.
- Log API operations, but never log passwords or bearer tokens.
- Back up the existing Faraja database before running the migration.

## PHP 8.5 conversion checklist for the original source

When the original PHP files are available as individual files/repository content,
replace/remove all incompatible legacy constructs including:

- `mysql_connect`, `mysql_query`, `mysql_fetch_*`, `mysql_real_escape_string`
- old-style constructors
- `each()`
- `create_function()`
- removed `ereg*` functions
- curly-brace string/array offsets
- unquoted array indexes
- implicit nullable/deprecated signatures
- direct SQL concatenation from `$_GET`/`$_POST`

Convert the shared DB layer first so the visual template does not need to be
redesigned. Preserve Facebox JavaScript/CSS and existing `rel="facebox"` links.
