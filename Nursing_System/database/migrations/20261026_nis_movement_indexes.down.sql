-- Run only for indexes installed by the corresponding up migration.
DROP INDEX idx_nis_movement_patient_status ON patient_movements;
DROP INDEX idx_nis_movement_source_date ON patient_movements;
DROP INDEX idx_nis_movement_destination_date ON patient_movements;
DROP INDEX idx_nis_current_bed ON patients;
