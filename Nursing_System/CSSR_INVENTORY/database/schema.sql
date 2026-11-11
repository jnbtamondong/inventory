CREATE DATABASE IF NOT EXISTS cssr_inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cssr_inventory;

DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS stock_cards;
DROP TABLE IF EXISTS stock_location_movements;
DROP TABLE IF EXISTS stock_withdrawals;
DROP TABLE IF EXISTS department_return_details;
DROP TABLE IF EXISTS department_returns;
DROP TABLE IF EXISTS cssr_available_stock;
DROP TABLE IF EXISTS bin_cards;
DROP TABLE IF EXISTS stock_out;
DROP TABLE IF EXISTS stock_in;
DROP TABLE IF EXISTS item_request_details;
DROP TABLE IF EXISTS item_requests;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS items;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS departments;

CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_name VARCHAR(150) NOT NULL UNIQUE,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active'
) ENGINE=InnoDB;

CREATE TABLE suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(150) NOT NULL UNIQUE,
    contact_person VARCHAR(150),
    contact_number VARCHAR(80),
    address TEXT,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active'
) ENGINE=InnoDB;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(80) NULL,
    middle_name VARCHAR(80) NULL,
    last_name VARCHAR(80) NULL,
    full_name VARCHAR(150) NOT NULL,
    username VARCHAR(80) NOT NULL UNIQUE,
    email VARCHAR(150) NULL,
    phone_number VARCHAR(50) NULL,
    birthday DATE NULL,
    password VARCHAR(255) NOT NULL,
    department_id INT NULL,
    role ENUM('Admin','Department Head','Department Staff') NOT NULL,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_code VARCHAR(80) NOT NULL UNIQUE,
    item_name VARCHAR(255) NULL,
    item_description VARCHAR(1000) NOT NULL,
    category VARCHAR(120),
    unit VARCHAR(50),
    brand VARCHAR(120),
    supplier VARCHAR(150),
    lot_number VARCHAR(120),
    expiry_date DATE NULL,
    bin_location VARCHAR(120),
    procurement_type VARCHAR(80) NULL,
    minimum_stock INT NOT NULL DEFAULT 0,
    current_stock INT NOT NULL DEFAULT 0,
    item_image VARCHAR(255),
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_item_code (item_code),
    INDEX idx_lot_number (lot_number),
    INDEX idx_category (category),
    INDEX idx_expiry (expiry_date),
    INDEX idx_reorder (current_stock, minimum_stock)
) ENGINE=InnoDB;

CREATE TABLE storage_transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_key CHAR(32) NOT NULL UNIQUE,
    reference_no VARCHAR(120) NOT NULL UNIQUE,
    item_id INT NOT NULL,
    source_lot_id INT NOT NULL,
    destination_lot_id INT NOT NULL,
    quantity INT NOT NULL,
    transfer_date DATE NOT NULL,
    remarks TEXT NULL,
    created_by INT NOT NULL,
    encoded_by VARCHAR(150) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_storage_transfer_item_date(item_id,transfer_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE inbound_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_key CHAR(32) NOT NULL UNIQUE,
    inbound_no VARCHAR(120) NULL UNIQUE,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    payload MEDIUMTEXT NOT NULL,
    received_date DATE NULL,
    supplier VARCHAR(150) NOT NULL DEFAULT '',
    item_count INT NOT NULL DEFAULT 0,
    total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
    created_by INT NOT NULL,
    encoded_by VARCHAR(150) NOT NULL,
    version INT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    INDEX idx_inbound_document_drafts(status,created_by,updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE stock_in (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    item_lot_id INT NULL,
    inbound_no VARCHAR(120) NULL,
    reference_no VARCHAR(120) NOT NULL,
    supplier VARCHAR(150),
    quantity_received INT NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    lot_number VARCHAR(120),
    expiry_date DATE NULL,
    received_date DATE NOT NULL,
    received_by VARCHAR(150) NOT NULL,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id),
    INDEX idx_stock_in_inbound_no (inbound_no)
) ENGINE=InnoDB;

CREATE TABLE stock_out (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    reference_no VARCHAR(120) NOT NULL,
    department VARCHAR(150) NOT NULL,
    quantity_issued INT NOT NULL,
    lot_number VARCHAR(120),
    issued_date DATE NOT NULL,
    issued_by VARCHAR(150) NOT NULL,
    received_by VARCHAR(150) NOT NULL,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id)
) ENGINE=InnoDB;

CREATE TABLE cssr_available_stock (
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
    status VARCHAR(30) NOT NULL DEFAULT 'AVAILABLE',
    created_by VARCHAR(150) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id),
    INDEX idx_cssr_available_item (item_id, lot_number, expiry_date, unit_type),
    INDEX idx_cssr_available_status (status)
) ENGINE=InnoDB;

CREATE TABLE stock_withdrawals (
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
    status VARCHAR(30) NOT NULL DEFAULT 'POSTED',
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
) ENGINE=InnoDB;

CREATE TABLE department_returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    return_no VARCHAR(120) NOT NULL UNIQUE,
    department_id INT NULL,
    department_name VARCHAR(150) NOT NULL,
    returned_by VARCHAR(150) NOT NULL,
    return_date DATE NOT NULL,
    reason TEXT NULL,
    remarks TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Pending',
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
) ENGINE=InnoDB;

CREATE TABLE department_return_details (
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
) ENGINE=InnoDB;

CREATE TABLE stock_location_movements (
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
    status VARCHAR(30) NOT NULL DEFAULT 'POSTED',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id),
    INDEX idx_location_movement_item (item_id),
    INDEX idx_location_movement_type (movement_type),
    INDEX idx_location_movement_ref (reference_no)
) ENGINE=InnoDB;

CREATE TABLE stock_cards (
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
) ENGINE=InnoDB;

CREATE TABLE item_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_no VARCHAR(120) NOT NULL UNIQUE,
    department_id INT NULL,
    department_name VARCHAR(150) NOT NULL,
    requested_by VARCHAR(150) NOT NULL,
    request_date DATE NOT NULL,
    purpose TEXT,
    status ENUM('Pending','Approved','Released','Cancelled','Deleted','Rejected') NOT NULL DEFAULT 'Pending',
    approved_by VARCHAR(150),
    approved_at DATETIME NULL,
    released_by VARCHAR(150),
    released_at DATETIME NULL,
    deleted_by VARCHAR(150),
    deleted_at DATETIME NULL,
    remarks TEXT,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_request_status (status),
    INDEX idx_request_date (request_date)
) ENGINE=InnoDB;

CREATE TABLE item_request_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    item_id INT NOT NULL,
    approved_item_lot_id INT NULL,
    quantity_requested INT NOT NULL,
    quantity_approved INT NOT NULL DEFAULT 0,
    quantity_released INT NOT NULL DEFAULT 0,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES item_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id),
    FOREIGN KEY (approved_item_lot_id) REFERENCES item_lots(id) ON DELETE SET NULL,
    INDEX idx_request_item (request_id, item_id)
) ENGINE=InnoDB;

CREATE TABLE bin_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    transaction_date DATE NOT NULL,
    reference_no VARCHAR(120) NOT NULL,
    transaction_type VARCHAR(80) NOT NULL,
    lot_number VARCHAR(120),
    expiry_date DATE NULL,
    received_qty INT NOT NULL DEFAULT 0,
    issued_qty INT NOT NULL DEFAULT 0,
    balance_qty INT NOT NULL DEFAULT 0,
    department VARCHAR(150),
    encoded_by VARCHAR(150),
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id),
    INDEX idx_bin_item_date (item_id, transaction_date)
) ENGINE=InnoDB;

CREATE TABLE chat_messages (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    sender_user_id INT NOT NULL,
    receiver_user_id INT NOT NULL,
    message_text VARCHAR(1000) NOT NULL,
    attachment_path VARCHAR(255) NULL,
    attachment_name VARCHAR(255) NULL,
    attachment_mime VARCHAR(80) NULL,
    sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL,
    INDEX idx_chat_conversation (sender_user_id, receiver_user_id, id),
    INDEX idx_chat_unread (receiver_user_id, read_at, id)
) ENGINE=InnoDB;

CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(80) NOT NULL,
    module VARCHAR(100) NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO departments (department_name, status) VALUES
('OPERATING ROOM', 'Active'),
('EMERGENCY ROOM', 'Active'),
('CSSR', 'Active'),
('WARD', 'Active'),
('OUT PATIENT DEPARTMENT', 'Active');

