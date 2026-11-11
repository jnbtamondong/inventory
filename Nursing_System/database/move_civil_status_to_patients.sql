ALTER TABLE patients
    ADD COLUMN civil_status ENUM('Single','Married','Widowed','Separated','Divorced') NULL AFTER gender;

UPDATE patients p
INNER JOIN triage t ON t.patient_id = p.patient_id
SET p.civil_status = t.civil_status
WHERE p.civil_status IS NULL
    AND t.civil_status IS NOT NULL
    AND t.civil_status <> '';

ALTER TABLE triage
    DROP COLUMN civil_status;
