-- Additive ER display metadata. Clinical status remains in patients/movements.
CREATE TABLE IF NOT EXISTS er_public_tracking (
 tracking_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 patient_id INT NOT NULL UNIQUE,
 encounter_id VARCHAR(30) NOT NULL UNIQUE,
 public_number VARCHAR(20) NOT NULL UNIQUE,
 public_visible TINYINT NOT NULL DEFAULT 1,
 privacy_reason VARCHAR(500) NOT NULL DEFAULT '',
 triage_started_at DATETIME(6) NULL,
 discharge_started_at DATETIME(6) NULL,
 enrolled_by INT NOT NULL,
 enrolled_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS er_tracker_events (
 event_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tracking_id BIGINT UNSIGNED NOT NULL,
 stage VARCHAR(32) NOT NULL,
 clinical_code VARCHAR(40) NOT NULL,
 source VARCHAR(50) NOT NULL,
 actor_id INT NOT NULL,
 reason VARCHAR(500) NOT NULL DEFAULT '',
 effective_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 INDEX idx_er_event(tracking_id,event_id),
 CONSTRAINT fk_er_event_tracking FOREIGN KEY(tracking_id) REFERENCES er_public_tracking(tracking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS er_display_settings (
 settings_id TINYINT NOT NULL PRIMARY KEY,
 completed_seconds INT NOT NULL DEFAULT 600,
 updated_by INT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO er_display_settings(settings_id) VALUES(1);
CREATE TABLE IF NOT EXISTS er_display_devices (
 device_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 token_hash CHAR(64) NOT NULL UNIQUE,
 label VARCHAR(80) NOT NULL,
 issued_by INT NOT NULL,
 issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 expires_at DATETIME NOT NULL,
 revoked_at DATETIME NULL,
 last_poll_at DATETIME(6) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
