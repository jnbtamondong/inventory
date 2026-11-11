ALTER TABLE triage
    ADD COLUMN medicolegal TINYINT(1) NOT NULL DEFAULT 0 AFTER assessment,
    ADD COLUMN ip TINYINT(1) NOT NULL DEFAULT 0 AFTER medicolegal,
    ADD COLUMN referred_by VARCHAR(150) NULL AFTER ip,
    ADD COLUMN coordinated TINYINT(1) NOT NULL DEFAULT 0 AFTER referred_by,
    ADD COLUMN vital_pr VARCHAR(20) NULL AFTER vital_signs,
    ADD COLUMN vital_height VARCHAR(20) NULL AFTER vital_pr,
    ADD COLUMN vital_weight VARCHAR(20) NULL AFTER vital_height,
    ADD COLUMN vital_spo2 VARCHAR(20) NULL AFTER vital_weight,
    ADD COLUMN vital_cbg VARCHAR(20) NULL AFTER vital_spo2;
