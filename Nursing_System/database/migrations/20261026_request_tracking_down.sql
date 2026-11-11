-- Export this mapping before rollback if notification deep links must be retained.
-- Request records, approval history and notification content are preserved.
DROP TABLE IF EXISTS nursing_service_request_notifications;
