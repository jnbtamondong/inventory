-- Additive link to existing notifications; requests and decisions remain in existing tables.
CREATE TABLE IF NOT EXISTS nursing_service_request_notifications (
 request_id INT NOT NULL,
 recipient_id INT NOT NULL,
 request_version INT NOT NULL,
 notification_id INT NOT NULL,
 PRIMARY KEY(request_id,recipient_id,request_version),
 UNIQUE KEY request_notice_id(notification_id)
) ENGINE=InnoDB;
