-- Optional performance migration. Back up the database first; run once after
-- inspecting SHOW INDEX. No patient/department IDs or clinical values change.
CREATE INDEX idx_nis_movement_patient_status ON patient_movements(patient_id,status);
CREATE INDEX idx_nis_movement_source_date ON patient_movements(source_department,created_at,id);
CREATE INDEX idx_nis_movement_destination_date ON patient_movements(destination_department,created_at,id);
CREATE INDEX idx_nis_current_bed ON patients(current_bed);
