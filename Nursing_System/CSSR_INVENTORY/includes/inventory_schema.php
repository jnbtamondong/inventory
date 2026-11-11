<?php
declare(strict_types=1);

function column_exists(string $table, string $column): bool
{
    return (bool) db_value(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$table, $column]
    );
}

function add_column_if_missing(string $table, string $column, string $definition): void
{
    if (!column_exists($table, $column)) {
        db_statement("ALTER TABLE {$table} ADD {$column} {$definition}");
    }
}

function add_check_constraint_if_missing(string $table, string $constraint, string $expression): void
{
    $exists = (int) db_value(
        'SELECT COUNT(*) FROM information_schema.table_constraints
         WHERE constraint_schema=DATABASE() AND table_name=? AND constraint_name=? AND constraint_type="CHECK"',
        [$table, $constraint]
    );
    if ($exists === 0) {
        db_statement("ALTER TABLE {$table} ADD CONSTRAINT {$constraint} CHECK ({$expression})");
    }
}

function ensure_cssr_inventory_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        db_statement("CREATE TABLE IF NOT EXISTS storage_transfers (
            id INT AUTO_INCREMENT PRIMARY KEY, request_key CHAR(32) NOT NULL UNIQUE, reference_no VARCHAR(120) NOT NULL UNIQUE,
            item_id INT NOT NULL, source_lot_id INT NOT NULL, destination_lot_id INT NOT NULL,
            quantity INT NOT NULL, transfer_date DATE NOT NULL, remarks TEXT NULL,
            created_by INT NOT NULL, encoded_by VARCHAR(150) NOT NULL, created_at DATETIME NOT NULL,
            INDEX idx_storage_transfer_item_date(item_id,transfer_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        db_statement("CREATE TABLE IF NOT EXISTS inbound_documents (
            id INT AUTO_INCREMENT PRIMARY KEY, request_key CHAR(32) NOT NULL UNIQUE,
            inbound_no VARCHAR(120) NULL UNIQUE, status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
            payload MEDIUMTEXT NOT NULL, received_date DATE NULL, supplier VARCHAR(150) NOT NULL DEFAULT '',
            item_count INT NOT NULL DEFAULT 0, total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
            created_by INT NOT NULL, encoded_by VARCHAR(150) NOT NULL, version INT NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, completed_at DATETIME NULL,
            INDEX idx_inbound_document_drafts(status,created_by,updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // Append the new outcome without renaming historical cancellations or changing enum indexes.
        $requestStatusType = db_value("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='item_requests' AND COLUMN_NAME='status'");
        if (is_string($requestStatusType) && str_starts_with(strtolower($requestStatusType), 'enum(') && !str_contains($requestStatusType, "'Rejected'")) {
            $requestStatusType = substr($requestStatusType, 0, -1) . ",'Rejected')";
            db_statement("ALTER TABLE item_requests MODIFY status $requestStatusType NOT NULL DEFAULT 'Pending'");
        }
        add_column_if_missing('items', 'item_name', 'VARCHAR(255) NULL AFTER item_code');
        add_column_if_missing('items', 'procurement_type', 'VARCHAR(80) NULL AFTER bin_location');
        $descriptionCapacity = db_value("SELECT CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='items' AND COLUMN_NAME='item_description'");
        if ($descriptionCapacity !== false && (int) $descriptionCapacity < 1000) {
            db_statement('ALTER TABLE items MODIFY item_description VARCHAR(1000) NOT NULL');
        }
        db_statement(
            'CREATE TABLE IF NOT EXISTS item_lots (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_id INT NOT NULL,
                lot_number VARCHAR(120) NOT NULL,
                expiry_date DATE NULL,
                lot_image VARCHAR(255) NULL,
                supplier VARCHAR(150) NOT NULL DEFAULT "",
                storage_location VARCHAR(120) NOT NULL DEFAULT "",
                unit_price DECIMAL(12,2) NULL DEFAULT 0,
                quantity_received INT NOT NULL DEFAULT 0,
                quantity_issued INT NOT NULL DEFAULT 0,
                quantity_returned INT NOT NULL DEFAULT 0,
                quantity_available INT NOT NULL DEFAULT 0,
                status VARCHAR(30) NOT NULL DEFAULT "AVAILABLE",
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (item_id) REFERENCES items(id),
                UNIQUE KEY uq_item_lot_identity (item_id, lot_number, expiry_date, supplier, storage_location, unit_price),
                INDEX idx_item_lots_fefo (item_id, status, expiry_date, created_at)
            ) ENGINE=InnoDB'
        );
        db_statement(
            'CREATE TABLE IF NOT EXISTS item_images (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_id INT NOT NULL,
                image_name VARCHAR(255) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
                INDEX idx_item_images_order (item_id, sort_order, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        db_statement(
            'CREATE TABLE IF NOT EXISTS item_lot_images (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_lot_id INT NOT NULL,
                image_name VARCHAR(255) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (item_lot_id) REFERENCES item_lots(id) ON DELETE CASCADE,
                INDEX idx_item_lot_images_order (item_lot_id, sort_order, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        db_statement('INSERT INTO item_lot_images (item_lot_id,image_name,sort_order)
                      SELECT l.id,l.lot_image,0 FROM item_lots l
                      WHERE l.lot_image IS NOT NULL AND l.lot_image<>""
                        AND NOT EXISTS (SELECT 1 FROM item_lot_images x WHERE x.item_lot_id=l.id AND x.image_name=l.lot_image)');
        db_statement('INSERT INTO item_images (item_id, image_name, sort_order)
                      SELECT i.id, i.item_image, 0 FROM items i
                      WHERE i.item_image IS NOT NULL AND i.item_image<>""
                        AND NOT EXISTS (SELECT 1 FROM item_images x WHERE x.item_id=i.id AND x.image_name=i.item_image)');
        db_statement(
            'CREATE TABLE IF NOT EXISTS item_price_list (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_name VARCHAR(255) NOT NULL,
                item_description TEXT NULL,
                lot_number VARCHAR(120) NULL,
                expiration_text VARCHAR(80) NULL,
                unit_type VARCHAR(50) NULL,
                classification VARCHAR(120) NULL,
                unit_price DECIMAL(14,2) NOT NULL DEFAULT 0,
                markup_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
                total_price DECIMAL(14,2) NOT NULL DEFAULT 0,
                selling_price DECIMAL(14,2) NOT NULL DEFAULT 0,
                source_as_of DATE NULL,
                source_hash CHAR(64) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_item_price_source_hash (source_hash),
                INDEX idx_item_price_name (item_name),
                INDEX idx_item_price_lot (lot_number)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        add_column_if_missing('stock_in', 'item_lot_id', 'INT NULL AFTER item_id');
        add_column_if_missing('stock_in', 'inbound_no', 'VARCHAR(120) NULL AFTER item_lot_id');
        add_column_if_missing('stock_out', 'item_lot_id', 'INT NULL AFTER item_id');
        add_column_if_missing('item_request_details', 'approved_item_lot_id', 'INT NULL AFTER item_id');
        add_column_if_missing('stock_out', 'quantity_returned', 'INT NOT NULL DEFAULT 0 AFTER quantity_issued');
        add_column_if_missing('item_lots', 'supplier', 'VARCHAR(150) NOT NULL DEFAULT "" AFTER expiry_date');
        add_column_if_missing('item_lots', 'brand', 'VARCHAR(150) NOT NULL DEFAULT "" AFTER supplier');
        add_column_if_missing('item_lots', 'lot_image', 'VARCHAR(255) NULL AFTER expiry_date');
        add_column_if_missing('item_lots', 'storage_location', 'VARCHAR(120) NOT NULL DEFAULT "" AFTER supplier');
        add_column_if_missing('item_lots', 'unit_price', 'DECIMAL(12,2) NULL DEFAULT 0 AFTER storage_location');
        if (db_value("SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='item_lots' AND COLUMN_NAME='unit_price'") === 'NO') {
            db_statement('ALTER TABLE item_lots MODIFY unit_price DECIMAL(12,2) NULL DEFAULT 0');
        }
        add_column_if_missing('stock_in', 'unit_price', 'DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER quantity_received');
        add_column_if_missing('items', 'barcode', 'VARCHAR(120) NULL AFTER item_code');
        add_column_if_missing('stock_in', 'pr_number', 'VARCHAR(120) NULL AFTER reference_no');
        add_column_if_missing('stock_in', 'procurement_request_item_id', 'INT NULL AFTER pr_number');
        add_column_if_missing('stock_in', 'po_number', 'VARCHAR(120) NULL AFTER pr_number');
        add_column_if_missing('stock_in', 'delivery_receipt_no', 'VARCHAR(120) NULL AFTER po_number');
        add_column_if_missing('stock_in', 'invoice_number', 'VARCHAR(120) NULL AFTER delivery_receipt_no');
        add_column_if_missing('stock_in', 'official_receipt_no', 'VARCHAR(120) NULL AFTER invoice_number');
        add_column_if_missing('stock_in', 'procurement_type', 'VARCHAR(80) NOT NULL DEFAULT "REGULAR" AFTER invoice_number');
        add_column_if_missing('stock_in', 'receiving_status', 'VARCHAR(40) NOT NULL DEFAULT "AVAILABLE" AFTER procurement_type');
        add_column_if_missing('stock_in', 'record_status', 'VARCHAR(20) NOT NULL DEFAULT "POSTED" AFTER receiving_status');
        add_column_if_missing('stock_in', 'voided_by', 'VARCHAR(150) NULL AFTER record_status');
        add_column_if_missing('stock_in', 'voided_at', 'DATETIME NULL AFTER voided_by');
        add_column_if_missing('stock_in', 'void_reason', 'TEXT NULL AFTER voided_at');
        add_column_if_missing('stock_in', 'delivery_completion', 'VARCHAR(30) NOT NULL DEFAULT "COMPLETE" AFTER receiving_status');
        add_column_if_missing('stock_in', 'expected_quantity', 'INT NOT NULL DEFAULT 0 AFTER quantity_received');
        add_column_if_missing('stock_in', 'remaining_quantity', 'INT NOT NULL DEFAULT 0 AFTER expected_quantity');
        add_column_if_missing('stock_in', 'accepted_quantity', 'INT NOT NULL DEFAULT 0 AFTER remaining_quantity');
        add_column_if_missing('stock_in', 'quarantined_quantity', 'INT NOT NULL DEFAULT 0 AFTER accepted_quantity');
        add_column_if_missing('stock_in', 'rejected_quantity', 'INT NOT NULL DEFAULT 0 AFTER quarantined_quantity');
        add_column_if_missing('stock_in', 'partial_reason', 'TEXT NULL AFTER rejected_quantity');
        db_statement('ALTER TABLE stock_in MODIFY receiving_status VARCHAR(40) NOT NULL DEFAULT "ADVANCE DELIVERY"');
        add_column_if_missing('item_lots', 'quantity_quarantined', 'INT NOT NULL DEFAULT 0 AFTER quantity_available');
        add_column_if_missing('item_lots', 'inspection_status', 'VARCHAR(40) NOT NULL DEFAULT "CLEARED" AFTER quantity_quarantined');
        add_column_if_missing('item_lots', 'recall_status', 'VARCHAR(30) NOT NULL DEFAULT "CLEAR" AFTER inspection_status');
        try { db_statement('ALTER TABLE items ADD UNIQUE KEY uq_items_barcode (barcode)'); } catch (Throwable $e) { /* already present */ }
        db_statement('UPDATE stock_in SET inbound_no=reference_no WHERE (inbound_no IS NULL OR inbound_no="") AND reference_no IS NOT NULL');
        try {
            $stockInInboundIndex = db_value(
                'SELECT COUNT(*)
                 FROM information_schema.statistics
                 WHERE table_schema=DATABASE() AND table_name="stock_in" AND index_name="idx_stock_in_inbound_no"'
            );
            if ((int) $stockInInboundIndex === 0) {
                db_statement('ALTER TABLE stock_in ADD INDEX idx_stock_in_inbound_no (inbound_no)');
            }
        } catch (Throwable $e) {
            log_error($e, 'STOCK_IN_INBOUND_INDEX');
        }
        add_column_if_missing('item_lots', 'quantity_received', 'INT NOT NULL DEFAULT 0 AFTER storage_location');
        add_column_if_missing('item_lots', 'quantity_issued', 'INT NOT NULL DEFAULT 0 AFTER quantity_received');
        add_column_if_missing('item_lots', 'quantity_returned', 'INT NOT NULL DEFAULT 0 AFTER quantity_issued');
        db_statement('ALTER TABLE item_lots MODIFY status VARCHAR(30) NOT NULL DEFAULT "AVAILABLE"');
        foreach ([
            ['item_lots','chk_item_lots_qty_received','quantity_received >= 0'],
            ['item_lots','chk_item_lots_qty_issued','quantity_issued >= 0'],
            ['item_lots','chk_item_lots_qty_returned','quantity_returned >= 0'],
            ['item_lots','chk_item_lots_qty_available','quantity_available >= 0'],
            ['items','chk_items_current_stock','current_stock >= 0'],
            ['stock_in','chk_stock_in_qty_received','quantity_received > 0'],
            ['stock_out','chk_stock_out_qty_issued','quantity_issued > 0'],
            ['stock_out','chk_stock_out_qty_returned','quantity_returned >= 0 AND quantity_returned <= quantity_issued'],
        ] as [$table, $constraint, $expression]) {
            try {
                add_check_constraint_if_missing($table, $constraint, $expression);
            } catch (Throwable $e) {
                log_error($e, 'INVENTORY_CHECK_CONSTRAINT_' . $constraint);
            }
        }
        try {
            $lotIdentityColumns = db_value(
                'SELECT GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR ",")
                 FROM information_schema.statistics
                 WHERE table_schema=DATABASE() AND table_name="item_lots" AND index_name="uq_item_lot_identity"'
            );
            if ($lotIdentityColumns !== 'item_id,lot_number,expiry_date,brand,supplier,storage_location,unit_price') {
                db_statement('ALTER TABLE item_lots DROP INDEX uq_item_lot_identity');
                db_statement('ALTER TABLE item_lots ADD UNIQUE KEY uq_item_lot_identity (item_id, lot_number, expiry_date, brand, supplier, storage_location, unit_price)');
            }
        } catch (Throwable $e) {
            log_error($e, 'ITEM_LOT_IDENTITY_INDEX');
        }

        db_statement(
            'CREATE TABLE IF NOT EXISTS cssr_available_stock (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_id INT NOT NULL,
                item_name VARCHAR(255) NOT NULL,
                item_description TEXT NULL,
                lot_number VARCHAR(120) NULL,
                expiry_date DATE NULL,
                quantity INT NOT NULL DEFAULT 0,
                unit_type VARCHAR(50) NULL,
                classification VARCHAR(120) NULL,
                procurement_type VARCHAR(150) NULL,
                source_location_id INT NULL,
                source_location_code VARCHAR(120) NULL,
                status VARCHAR(30) NOT NULL DEFAULT "AVAILABLE",
                created_by VARCHAR(150) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (item_id) REFERENCES items(id),
                INDEX idx_cssr_available_item (item_id, lot_number, expiry_date, unit_type),
                INDEX idx_cssr_available_status (status)
            ) ENGINE=InnoDB'
        );

        db_statement(
            'CREATE TABLE IF NOT EXISTS stock_withdrawals (
                id INT AUTO_INCREMENT PRIMARY KEY,
                withdrawal_number VARCHAR(120) NOT NULL UNIQUE,
                withdrawal_date_time DATETIME NOT NULL,
                item_id INT NOT NULL,
                item_name VARCHAR(255) NOT NULL,
                item_description TEXT NULL,
                lot_number VARCHAR(120) NULL,
                expiry_date DATE NULL,
                quantity_taken INT NOT NULL,
                unit_type VARCHAR(50) NULL,
                from_rack_number VARCHAR(80) NULL,
                from_shelf_number VARCHAR(80) NULL,
                from_bin_number VARCHAR(80) NULL,
                from_location_code VARCHAR(120) NULL,
                purpose VARCHAR(120) NOT NULL,
                taken_by VARCHAR(150) NOT NULL,
                remarks TEXT NULL,
                added_to_cssr_stock TINYINT(1) NOT NULL DEFAULT 0,
                cssr_stock_id INT NULL,
                posted_date_time DATETIME NULL,
                posted_by VARCHAR(150) NULL,
                status VARCHAR(30) NOT NULL DEFAULT "POSTED",
                cancelled_at DATETIME NULL,
                cancelled_by VARCHAR(150) NULL,
                cancel_reason TEXT NULL,
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (item_id) REFERENCES items(id),
                FOREIGN KEY (cssr_stock_id) REFERENCES cssr_available_stock(id) ON DELETE SET NULL,
                INDEX idx_withdrawal_item (item_id),
                INDEX idx_withdrawal_status (status),
                INDEX idx_withdrawal_posted (posted_date_time)
            ) ENGINE=InnoDB'
        );

        db_statement(
            'CREATE TABLE IF NOT EXISTS storage_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                request_no VARCHAR(120) NOT NULL UNIQUE,
                from_location VARCHAR(120) NOT NULL,
                to_location VARCHAR(120) NOT NULL DEFAULT "CSSR",
                requested_by VARCHAR(150) NOT NULL,
                request_date DATE NOT NULL,
                purpose VARCHAR(150) NULL,
                remarks TEXT NULL,
                status VARCHAR(30) NOT NULL DEFAULT "Pending",
                released_by VARCHAR(150) NULL,
                released_at DATETIME NULL,
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_storage_requests_status (status),
                INDEX idx_storage_requests_from (from_location)
            ) ENGINE=InnoDB'
        );

        db_statement(
            'CREATE TABLE IF NOT EXISTS storage_request_details (
                id INT AUTO_INCREMENT PRIMARY KEY,
                request_id INT NOT NULL,
                item_id INT NOT NULL,
                requested_qty INT NOT NULL,
                released_qty INT NOT NULL DEFAULT 0,
                release_lot_id INT NULL,
                lot_number VARCHAR(120) NULL,
                expiry_date DATE NULL,
                remarks TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (request_id) REFERENCES storage_requests(id) ON DELETE CASCADE,
                FOREIGN KEY (item_id) REFERENCES items(id),
                FOREIGN KEY (release_lot_id) REFERENCES item_lots(id) ON DELETE SET NULL,
                INDEX idx_storage_request_details_request (request_id),
                INDEX idx_storage_request_details_item (item_id)
            ) ENGINE=InnoDB'
        );

        db_statement(
            'CREATE TABLE IF NOT EXISTS department_returns (
                id INT AUTO_INCREMENT PRIMARY KEY,
                return_no VARCHAR(120) NOT NULL UNIQUE,
                department_id INT NULL,
                department_name VARCHAR(150) NOT NULL,
                returned_by VARCHAR(150) NOT NULL,
                return_date DATE NOT NULL,
                reason TEXT NULL,
                remarks TEXT NULL,
                status VARCHAR(30) NOT NULL DEFAULT "Pending",
                received_by VARCHAR(150) NULL,
                received_at DATETIME NULL,
                deleted_by VARCHAR(150) NULL,
                deleted_at DATETIME NULL,
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_department_returns_status (status),
                INDEX idx_department_returns_department (department_id),
                INDEX idx_department_returns_date (return_date)
            ) ENGINE=InnoDB'
        );

        db_statement(
            'CREATE TABLE IF NOT EXISTS department_return_details (
                id INT AUTO_INCREMENT PRIMARY KEY,
                return_id INT NOT NULL,
                item_id INT NOT NULL,
                lot_number VARCHAR(120) NULL,
                expiry_date DATE NULL,
                quantity_returned INT NOT NULL,
                quantity_received INT NOT NULL DEFAULT 0,
                remarks TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (return_id) REFERENCES department_returns(id) ON DELETE CASCADE,
                FOREIGN KEY (item_id) REFERENCES items(id),
                INDEX idx_department_return_details_return (return_id),
                INDEX idx_department_return_details_item (item_id)
            ) ENGINE=InnoDB'
        );

        db_statement(
            'CREATE TABLE IF NOT EXISTS stock_location_movements (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_id INT NOT NULL,
                movement_type VARCHAR(120) NOT NULL,
                reference_no VARCHAR(120) NOT NULL,
                lot_number VARCHAR(120) NULL,
                expiry_date DATE NULL,
                quantity INT NOT NULL,
                from_location_id INT NULL,
                from_location_code VARCHAR(120) NULL,
                to_location_id INT NULL,
                to_location_code VARCHAR(120) NULL,
                processed_by VARCHAR(150) NOT NULL,
                processed_at DATETIME NOT NULL,
                remarks TEXT NULL,
                status VARCHAR(30) NOT NULL DEFAULT "POSTED",
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (item_id) REFERENCES items(id),
                INDEX idx_location_movement_item (item_id),
                INDEX idx_location_movement_type (movement_type),
                INDEX idx_location_movement_ref (reference_no)
            ) ENGINE=InnoDB'
        );

        db_statement(
            'CREATE TABLE IF NOT EXISTS stock_adjustments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                adjustment_no VARCHAR(120) NOT NULL UNIQUE,
                location_name VARCHAR(120) NOT NULL,
                stock_type VARCHAR(30) NOT NULL,
                source_record_id INT NOT NULL,
                item_id INT NOT NULL,
                item_name VARCHAR(255) NOT NULL,
                lot_number VARCHAR(120) NULL,
                expiry_date DATE NULL,
                system_quantity INT NOT NULL,
                actual_quantity INT NOT NULL,
                variance_quantity INT NOT NULL,
                reason TEXT NOT NULL,
                adjusted_by VARCHAR(150) NOT NULL,
                adjusted_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (item_id) REFERENCES items(id),
                INDEX idx_stock_adjustment_location (location_name),
                INDEX idx_stock_adjustment_item (item_id),
                INDEX idx_stock_adjustment_date (adjusted_at)
            ) ENGINE=InnoDB'
        );

        db_statement(
            'CREATE TABLE IF NOT EXISTS stock_cards (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_id INT NOT NULL,
                transaction_date DATETIME NOT NULL,
                reference_no VARCHAR(120) NOT NULL,
                transaction_type VARCHAR(120) NOT NULL,
                lot_number VARCHAR(120) NULL,
                expiry_date DATE NULL,
                received_qty INT NOT NULL DEFAULT 0,
                issued_qty INT NOT NULL DEFAULT 0,
                balance_qty INT NOT NULL DEFAULT 0,
                from_location VARCHAR(120) NULL,
                to_location VARCHAR(120) NULL,
                encoded_by VARCHAR(150) NULL,
                remarks TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (item_id) REFERENCES items(id),
                INDEX idx_stock_card_item_date (item_id, transaction_date)
            ) ENGINE=InnoDB'
        );

        db_statement('CREATE TABLE IF NOT EXISTS receiving_inspections (
            id INT AUTO_INCREMENT PRIMARY KEY, item_lot_id INT NOT NULL, stock_in_id INT NULL,
            inspection_no VARCHAR(120) NOT NULL UNIQUE, delivered_qty INT NOT NULL,
            accepted_qty INT NOT NULL DEFAULT 0, quarantined_qty INT NOT NULL DEFAULT 0,
            rejected_qty INT NOT NULL DEFAULT 0, packaging_condition VARCHAR(80) NULL,
            findings TEXT NULL, decision VARCHAR(40) NOT NULL DEFAULT "FOR INSPECTION",
            inspected_by VARCHAR(150) NULL, inspected_at DATETIME NULL,
            approved_by VARCHAR(150) NULL, approved_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (item_lot_id) REFERENCES item_lots(id), INDEX idx_receiving_decision (decision)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        db_statement('CREATE TABLE IF NOT EXISTS stock_reservations (
            id INT AUTO_INCREMENT PRIMARY KEY, request_id INT NOT NULL, request_detail_id INT NOT NULL,
            item_lot_id INT NOT NULL, quantity INT NOT NULL, status VARCHAR(30) NOT NULL DEFAULT "ACTIVE",
            reserved_by VARCHAR(150) NOT NULL, reserved_at DATETIME NOT NULL,
            released_at DATETIME NULL, expires_at DATETIME NULL,
            UNIQUE KEY uq_active_request_detail (request_detail_id), INDEX idx_reservation_lot (item_lot_id,status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        db_statement('CREATE TABLE IF NOT EXISTS cycle_count_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY, count_no VARCHAR(120) NOT NULL UNIQUE,
            location_name VARCHAR(120) NOT NULL, count_date DATE NOT NULL, status VARCHAR(30) NOT NULL DEFAULT "DRAFT",
            assigned_to VARCHAR(150) NULL, created_by VARCHAR(150) NOT NULL, approved_by VARCHAR(150) NULL,
            approved_at DATETIME NULL, remarks TEXT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        db_statement('CREATE TABLE IF NOT EXISTS cycle_count_details (
            id INT AUTO_INCREMENT PRIMARY KEY, session_id INT NOT NULL, item_lot_id INT NOT NULL,
            system_qty INT NOT NULL, first_count INT NULL, second_count INT NULL, approved_qty INT NULL,
            variance_qty INT NULL, variance_reason TEXT NULL, status VARCHAR(30) NOT NULL DEFAULT "PENDING",
            FOREIGN KEY (session_id) REFERENCES cycle_count_sessions(id) ON DELETE CASCADE,
            FOREIGN KEY (item_lot_id) REFERENCES item_lots(id), UNIQUE KEY uq_count_lot (session_id,item_lot_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        db_statement('CREATE TABLE IF NOT EXISTS product_recalls (
            id INT AUTO_INCREMENT PRIMARY KEY, recall_no VARCHAR(120) NOT NULL UNIQUE, item_lot_id INT NOT NULL,
            reason TEXT NOT NULL, action_required TEXT NULL, status VARCHAR(30) NOT NULL DEFAULT "ACTIVE",
            initiated_by VARCHAR(150) NOT NULL, initiated_at DATETIME NOT NULL,
            closed_by VARCHAR(150) NULL, closed_at DATETIME NULL, closure_notes TEXT NULL,
            FOREIGN KEY (item_lot_id) REFERENCES item_lots(id), INDEX idx_recall_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        db_statement('CREATE TABLE IF NOT EXISTS inventory_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY, notification_type VARCHAR(60) NOT NULL,
            severity VARCHAR(20) NOT NULL DEFAULT "INFO", title VARCHAR(180) NOT NULL, message TEXT NOT NULL,
            entity_type VARCHAR(60) NULL, entity_id INT NULL, status VARCHAR(20) NOT NULL DEFAULT "UNREAD",
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, read_by VARCHAR(150) NULL, read_at DATETIME NULL,
            INDEX idx_notification_status (status,created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        db_statement('CREATE TABLE IF NOT EXISTS chat_messages (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            sender_user_id INT NOT NULL,
            receiver_user_id INT NOT NULL,
            message_text VARCHAR(1000) NOT NULL,
            sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            read_at DATETIME NULL,
            INDEX idx_chat_conversation (sender_user_id,receiver_user_id,id),
            INDEX idx_chat_unread (receiver_user_id,read_at,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        add_column_if_missing('chat_messages', 'attachment_path', 'VARCHAR(255) NULL');
        add_column_if_missing('chat_messages', 'attachment_name', 'VARCHAR(255) NULL');
        add_column_if_missing('chat_messages', 'attachment_mime', 'VARCHAR(80) NULL');

        db_statement('ALTER TABLE bin_cards MODIFY transaction_type VARCHAR(80) NOT NULL');

        $cssrAvailableColumns = [
            'item_id' => 'INT NOT NULL',
            'item_name' => 'VARCHAR(255) NOT NULL',
            'item_description' => 'TEXT NULL',
            'lot_number' => 'VARCHAR(120) NULL',
            'expiry_date' => 'DATE NULL',
            'quantity' => 'INT NOT NULL DEFAULT 0',
            'unit_type' => 'VARCHAR(50) NULL',
            'classification' => 'VARCHAR(120) NULL',
            'procurement_type' => 'VARCHAR(150) NULL',
            'source_location_id' => 'INT NULL',
            'source_location_code' => 'VARCHAR(120) NULL',
            'status' => 'VARCHAR(30) NOT NULL DEFAULT "AVAILABLE"',
            'created_by' => 'VARCHAR(150) NULL',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ];
        foreach ($cssrAvailableColumns as $column => $definition) {
            add_column_if_missing('cssr_available_stock', $column, $definition);
        }

        $withdrawalColumns = [
            'withdrawal_number' => 'VARCHAR(120) NOT NULL',
            'withdrawal_date_time' => 'DATETIME NOT NULL',
            'item_id' => 'INT NOT NULL',
            'item_name' => 'VARCHAR(255) NOT NULL',
            'item_description' => 'TEXT NULL',
            'lot_number' => 'VARCHAR(120) NULL',
            'expiry_date' => 'DATE NULL',
            'quantity_taken' => 'INT NOT NULL',
            'unit_type' => 'VARCHAR(50) NULL',
            'from_rack_number' => 'VARCHAR(80) NULL',
            'from_shelf_number' => 'VARCHAR(80) NULL',
            'from_bin_number' => 'VARCHAR(80) NULL',
            'from_location_code' => 'VARCHAR(120) NULL',
            'purpose' => 'VARCHAR(120) NOT NULL',
            'taken_by' => 'VARCHAR(150) NOT NULL',
            'remarks' => 'TEXT NULL',
            'added_to_cssr_stock' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'cssr_stock_id' => 'INT NULL',
            'posted_date_time' => 'DATETIME NULL',
            'posted_by' => 'VARCHAR(150) NULL',
            'status' => 'VARCHAR(30) NOT NULL DEFAULT "POSTED"',
            'cancelled_at' => 'DATETIME NULL',
            'cancelled_by' => 'VARCHAR(150) NULL',
            'cancel_reason' => 'TEXT NULL',
            'created_by' => 'INT NULL',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
        ];
        foreach ($withdrawalColumns as $column => $definition) {
            add_column_if_missing('stock_withdrawals', $column, $definition);
        }
    } catch (Throwable $e) {
        log_error($e, 'INVENTORY_SCHEMA');
    }
}
