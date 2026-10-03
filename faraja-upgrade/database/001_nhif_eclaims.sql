-- Faraja Hospital System - NHIF/eClaims extension
-- Designed for MySQL 8 / MariaDB 10.6+ and PHP 8.5.
-- Link patient_id/visit_id to the existing Faraja tables during the final source migration.

CREATE TABLE IF NOT EXISTS nhif_authorizations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id BIGINT UNSIGNED NULL,
    visit_id BIGINT UNSIGNED NULL,
    card_no VARCHAR(32) NOT NULL,
    visit_type_id TINYINT UNSIGNED NOT NULL,
    referral_no VARCHAR(64) NULL,
    authorization_status VARCHAR(20) NOT NULL,
    authorization_no VARCHAR(64) NULL,
    card_status VARCHAR(30) NULL,
    full_name VARCHAR(200) NULL,
    gender VARCHAR(20) NULL,
    date_of_birth DATE NULL,
    scheme_id VARCHAR(30) NULL,
    product_code VARCHAR(30) NULL,
    remarks TEXT NULL,
    raw_response JSON NULL,
    verified_by BIGINT UNSIGNED NULL,
    verified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nhif_auth_card (card_no),
    INDEX idx_nhif_auth_no (authorization_no),
    INDEX idx_nhif_auth_visit (visit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS nhif_tariffs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    facility_code VARCHAR(30) NOT NULL,
    item_code VARCHAR(50) NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    package_id INT NOT NULL,
    scheme_id INT NULL,
    unit_price DECIMAL(18,2) NOT NULL DEFAULT 0,
    is_restricted TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_nhif_tariff (facility_code, item_code, package_id, scheme_id),
    INDEX idx_nhif_item_code (item_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS nhif_excluded_services (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    facility_code VARCHAR(30) NOT NULL,
    item_code VARCHAR(50) NOT NULL,
    scheme_id INT NOT NULL,
    excluded_for_products VARCHAR(255) NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_nhif_excluded (facility_code, item_code, scheme_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS nhif_preapprovals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id BIGINT UNSIGNED NULL,
    visit_id BIGINT UNSIGNED NULL,
    card_no VARCHAR(32) NOT NULL,
    item_code VARCHAR(50) NOT NULL,
    reference_no VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL,
    raw_response JSON NULL,
    checked_by BIGINT UNSIGNED NULL,
    checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nhif_preapproval_visit (visit_id),
    INDEX idx_nhif_preapproval_ref (reference_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS nhif_referrals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id BIGINT UNSIGNED NULL,
    visit_id BIGINT UNSIGNED NULL,
    card_no VARCHAR(32) NOT NULL,
    authorization_no VARCHAR(64) NOT NULL,
    patient_full_name VARCHAR(200) NOT NULL,
    gender VARCHAR(20) NULL,
    physician_name VARCHAR(200) NOT NULL,
    physician_mobile_no VARCHAR(30) NULL,
    physician_qualification_id TINYINT UNSIGNED NOT NULL,
    service_issuing_facility_code VARCHAR(30) NOT NULL,
    referring_diagnosis VARCHAR(255) NOT NULL,
    reasons_for_referral TEXT NOT NULL,
    referral_no VARCHAR(64) NULL,
    raw_response JSON NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nhif_ref_visit (visit_id),
    INDEX idx_nhif_ref_no (referral_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS nhif_claims (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    folio_id CHAR(36) NOT NULL,
    visit_id BIGINT UNSIGNED NULL,
    patient_id BIGINT UNSIGNED NULL,
    facility_code VARCHAR(30) NOT NULL,
    claim_year SMALLINT UNSIGNED NOT NULL,
    claim_month TINYINT UNSIGNED NOT NULL,
    folio_no BIGINT UNSIGNED NOT NULL,
    serial_no VARCHAR(100) NULL,
    card_no VARCHAR(32) NOT NULL,
    first_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NULL,
    gender VARCHAR(20) NULL,
    date_of_birth DATE NULL,
    telephone_no VARCHAR(30) NULL,
    patient_file_no VARCHAR(100) NULL,
    patient_pdf_path VARCHAR(500) NULL,
    authorization_no VARCHAR(64) NOT NULL,
    attendance_date DATE NOT NULL,
    patient_type_code ENUM('OUT','IN') NOT NULL DEFAULT 'OUT',
    date_admitted DATE NULL,
    date_discharged DATE NULL,
    practitioner_no VARCHAR(100) NOT NULL,
    practitioner_name VARCHAR(200) NULL,
    status ENUM('DRAFT','READY','SUBMITTED','ACCEPTED','REJECTED','ERROR') NOT NULL DEFAULT 'DRAFT',
    nhif_response JSON NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    submitted_at DATETIME NULL,
    UNIQUE KEY uq_nhif_folio_id (folio_id),
    UNIQUE KEY uq_nhif_folio_no (facility_code, claim_year, claim_month, folio_no),
    INDEX idx_nhif_claim_visit (visit_id),
    INDEX idx_nhif_claim_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS nhif_claim_diseases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    claim_id BIGINT UNSIGNED NOT NULL,
    disease_code VARCHAR(30) NOT NULL,
    remarks VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_nhif_disease_claim FOREIGN KEY (claim_id)
        REFERENCES nhif_claims(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS nhif_claim_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    claim_id BIGINT UNSIGNED NOT NULL,
    item_code VARCHAR(50) NOT NULL,
    other_details VARCHAR(500) NULL,
    item_quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
    unit_price DECIMAL(18,2) NOT NULL,
    amount_claimed DECIMAL(18,2) NOT NULL,
    approval_ref_no VARCHAR(100) NULL,
    source_department VARCHAR(50) NULL,
    source_record_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_nhif_item_claim FOREIGN KEY (claim_id)
        REFERENCES nhif_claims(id) ON DELETE CASCADE,
    INDEX idx_nhif_claim_item_code (item_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS nhif_api_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department VARCHAR(50) NOT NULL,
    action_name VARCHAR(100) NOT NULL,
    patient_id BIGINT UNSIGNED NULL,
    visit_id BIGINT UNSIGNED NULL,
    request_reference VARCHAR(100) NULL,
    http_status SMALLINT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    message TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nhif_log_visit (visit_id),
    INDEX idx_nhif_log_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
