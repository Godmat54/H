SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS nhif_verifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id BIGINT UNSIGNED NOT NULL,
    visit_id BIGINT UNSIGNED NOT NULL,
    card_no VARCHAR(50) NOT NULL,
    visit_type_id TINYINT NOT NULL,
    authorization_status VARCHAR(30) NULL,
    authorization_no VARCHAR(80) NULL,
    scheme_id VARCHAR(30) NULL,
    product_code VARCHAR(30) NULL,
    remarks TEXT NULL,
    response_json LONGTEXT NULL,
    verified_by BIGINT UNSIGNED NULL,
    verified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nv_patient (patient_id),
    INDEX idx_nv_visit (visit_id),
    INDEX idx_nv_card (card_no),
    INDEX idx_nv_authorization (authorization_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS nhif_tariffs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_code VARCHAR(50) NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    package_id INT NULL,
    scheme_id INT NULL,
    unit_price DECIMAL(18,2) NOT NULL DEFAULT 0,
    is_restricted TINYINT(1) NOT NULL DEFAULT 0,
    excluded_products TEXT NULL,
    raw_json LONGTEXT NULL,
    synced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tariff (item_code, package_id, scheme_id),
    INDEX idx_tariff_name (item_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS nhif_service_mappings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department VARCHAR(50) NOT NULL,
    local_service_code VARCHAR(100) NOT NULL,
    local_service_name VARCHAR(255) NOT NULL,
    nhif_item_code VARCHAR(50) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_department_local_code (department, local_service_code),
    INDEX idx_mapping_nhif_item (nhif_item_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS nhif_preapprovals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id BIGINT UNSIGNED NOT NULL,
    visit_id BIGINT UNSIGNED NOT NULL,
    card_no VARCHAR(50) NOT NULL,
    reference_no VARCHAR(100) NOT NULL,
    item_code VARCHAR(50) NOT NULL,
    status VARCHAR(30) NULL,
    response_json LONGTEXT NULL,
    checked_by BIGINT UNSIGNED NULL,
    checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_np_visit (visit_id),
    INDEX idx_np_reference (reference_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS nhif_referrals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id BIGINT UNSIGNED NOT NULL,
    visit_id BIGINT UNSIGNED NOT NULL,
    card_no VARCHAR(50) NOT NULL,
    authorization_no VARCHAR(80) NOT NULL,
    referral_no VARCHAR(100) NULL,
    destination_facility_code VARCHAR(50) NOT NULL,
    diagnosis TEXT NOT NULL,
    reason TEXT NOT NULL,
    response_json LONGTEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nr_visit (visit_id),
    INDEX idx_nr_referral (referral_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS nhif_claims (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    visit_id BIGINT UNSIGNED NOT NULL,
    patient_id BIGINT UNSIGNED NULL,
    folio_id CHAR(36) NOT NULL,
    folio_no INT NULL,
    serial_no VARCHAR(100) NULL,
    card_no VARCHAR(50) NOT NULL,
    authorization_no VARCHAR(80) NULL,
    claim_year INT NOT NULL,
    claim_month INT NOT NULL,
    payload_json LONGTEXT NOT NULL,
    response_json LONGTEXT NULL,
    http_status INT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'DRAFT',
    submitted_at DATETIME NULL,
    reconciled_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_folio (folio_id),
    INDEX idx_nc_visit (visit_id),
    INDEX idx_nc_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS nhif_claim_diseases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    claim_id BIGINT UNSIGNED NOT NULL,
    disease_code VARCHAR(50) NOT NULL,
    remarks VARCHAR(255) NULL,
    FOREIGN KEY (claim_id) REFERENCES nhif_claims(id) ON DELETE CASCADE,
    INDEX idx_ncd_claim (claim_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS nhif_claim_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    claim_id BIGINT UNSIGNED NOT NULL,
    item_code VARCHAR(50) NOT NULL,
    item_quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
    unit_price DECIMAL(18,2) NOT NULL DEFAULT 0,
    amount_claimed DECIMAL(18,2) NOT NULL DEFAULT 0,
    approval_ref_no VARCHAR(100) NULL,
    source_department VARCHAR(50) NULL,
    source_record_id BIGINT UNSIGNED NULL,
    FOREIGN KEY (claim_id) REFERENCES nhif_claims(id) ON DELETE CASCADE,
    INDEX idx_nci_claim (claim_id),
    INDEX idx_nci_item (item_code),
    INDEX idx_nci_department (source_department)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS nhif_audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    department VARCHAR(50) NOT NULL,
    patient_id BIGINT UNSIGNED NULL,
    visit_id BIGINT UNSIGNED NULL,
    action_name VARCHAR(100) NOT NULL,
    http_status INT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    message TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nal_visit (visit_id),
    INDEX idx_nal_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS nhif_visit_workflow (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id BIGINT UNSIGNED NOT NULL,
    visit_id BIGINT UNSIGNED NOT NULL,
    payment_mode VARCHAR(50) NOT NULL,
    nhif_active TINYINT(1) NOT NULL DEFAULT 0,
    workflow_status VARCHAR(30) NOT NULL DEFAULT 'PENDING_VERIFICATION',
    started_by BIGINT UNSIGNED NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_nhif_workflow_visit (visit_id),
    INDEX idx_nw_patient (patient_id),
    INDEX idx_nw_status (workflow_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS nhif_visit_profile (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id BIGINT UNSIGNED NOT NULL,
    visit_id BIGINT UNSIGNED NOT NULL,
    first_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NULL,
    gender VARCHAR(30) NULL,
    date_of_birth DATE NULL,
    telephone_no VARCHAR(30) NULL,
    patient_file_no VARCHAR(100) NULL,
    patient_type_code VARCHAR(10) NOT NULL DEFAULT 'OUT',
    attendance_date DATE NULL,
    date_admitted DATE NULL,
    date_discharged DATE NULL,
    practitioner_no VARCHAR(100) NULL,
    practitioner_name VARCHAR(200) NULL,
    updated_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_nhif_profile_visit (visit_id),
    INDEX idx_nvp_patient (patient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS nhif_visit_diagnoses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id BIGINT UNSIGNED NOT NULL,
    visit_id BIGINT UNSIGNED NOT NULL,
    department VARCHAR(50) NOT NULL,
    disease_code VARCHAR(50) NOT NULL,
    notes VARCHAR(500) NULL,
    practitioner_no VARCHAR(100) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nvd_visit (visit_id),
    INDEX idx_nvd_disease (disease_code),
    UNIQUE KEY uq_nhif_visit_disease (visit_id, disease_code, department)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS nhif_department_services (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id BIGINT UNSIGNED NOT NULL,
    visit_id BIGINT UNSIGNED NOT NULL,
    department VARCHAR(50) NOT NULL,
    local_service_code VARCHAR(100) NULL,
    local_service_name VARCHAR(255) NULL,
    nhif_item_code VARCHAR(50) NOT NULL,
    item_name VARCHAR(255) NULL,
    quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
    unit_price DECIMAL(18,2) NOT NULL DEFAULT 0,
    amount_claimed DECIMAL(18,2) NOT NULL DEFAULT 0,
    is_restricted TINYINT(1) NOT NULL DEFAULT 0,
    approval_ref_no VARCHAR(100) NULL,
    approval_status VARCHAR(30) NULL,
    practitioner_no VARCHAR(100) NULL,
    notes VARCHAR(500) NULL,
    source_record_id BIGINT UNSIGNED NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'RECORDED',
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nds_visit (visit_id),
    INDEX idx_nds_department (department),
    INDEX idx_nds_item (nhif_item_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
