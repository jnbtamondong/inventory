-- Additive only. Legacy patients rows remain encounter snapshots and keep their IDs.
CREATE TABLE IF NOT EXISTS nis_patient_identities (
 identity_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 hospital_number VARCHAR(30) NOT NULL UNIQUE,
 demographics LONGTEXT NOT NULL,
 created_by INT NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS nis_registrations (
 registration_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 identity_id BIGINT UNSIGNED NOT NULL,
 patient_id INT NOT NULL UNIQUE,
 request_key CHAR(64) NOT NULL UNIQUE,
 registered_by INT NOT NULL,
 arrival_at DATETIME NOT NULL,
 chief_complaint TEXT NOT NULL,
 referred_by VARCHAR(150) NOT NULL DEFAULT '',
 medicolegal TINYINT NOT NULL DEFAULT 0,
 ip TINYINT NOT NULL DEFAULT 0,
 coordinated TINYINT NOT NULL DEFAULT 0,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_registration_identity(identity_id),
 CONSTRAINT fk_registration_identity FOREIGN KEY(identity_id) REFERENCES nis_patient_identities(identity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS nis_registration_audit (
 audit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 patient_id INT NOT NULL,
 identity_id BIGINT UNSIGNED NOT NULL,
 user_id INT NOT NULL,
 event VARCHAR(50) NOT NULL,
 details LONGTEXT NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_registration_audit_patient(patient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS nis_registration_requests (
 request_key CHAR(64) NOT NULL PRIMARY KEY,
 user_id INT NOT NULL,
 patient_id INT NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
