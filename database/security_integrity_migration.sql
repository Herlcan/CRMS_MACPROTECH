-- MACPROTECH security and integrity migration
-- Run this against the crms_macprotech database after taking a backup.

CREATE TABLE IF NOT EXISTS login_attempts (
    id int(11) NOT NULL AUTO_INCREMENT,
    username varchar(100) NOT NULL,
    ip_address varchar(45) NOT NULL,
    success tinyint(1) NOT NULL DEFAULT 0,
    attempted_at datetime NOT NULL DEFAULT current_timestamp(),
    lock_until datetime DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_login_attempts_lookup (username, ip_address, success, attempted_at),
    KEY idx_login_attempts_lock (username, ip_address, lock_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE stock_out_transaction
    ADD COLUMN IF NOT EXISTS average_cost_snapshot decimal(10,2) DEFAULT NULL AFTER quantity;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE work_order ADD CONSTRAINT fk_work_order_client FOREIGN KEY (client_id) REFERENCES client(id) ON DELETE RESTRICT ON UPDATE CASCADE',
        'SELECT 1'
    )
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'work_order'
    AND CONSTRAINT_NAME = 'fk_work_order_client'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE payments ADD CONSTRAINT fk_payments_work_order FOREIGN KEY (work_order_id) REFERENCES work_order(id) ON DELETE RESTRICT ON UPDATE CASCADE',
        'SELECT 1'
    )
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'payments'
    AND CONSTRAINT_NAME = 'fk_payments_work_order'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE refunds ADD CONSTRAINT fk_refunds_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE RESTRICT ON UPDATE CASCADE',
        'SELECT 1'
    )
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'refunds'
    AND CONSTRAINT_NAME = 'fk_refunds_payment'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE payment_transaction ADD CONSTRAINT fk_payment_transaction_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE RESTRICT ON UPDATE CASCADE',
        'SELECT 1'
    )
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'payment_transaction'
    AND CONSTRAINT_NAME = 'fk_payment_transaction_payment'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE payment_transaction ADD CONSTRAINT fk_payment_transaction_work_order FOREIGN KEY (work_order_id) REFERENCES work_order(id) ON DELETE RESTRICT ON UPDATE CASCADE',
        'SELECT 1'
    )
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'payment_transaction'
    AND CONSTRAINT_NAME = 'fk_payment_transaction_work_order'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE refunds ADD CONSTRAINT fk_refunds_user FOREIGN KEY (refunded_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE',
        'SELECT 1'
    )
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'refunds'
    AND CONSTRAINT_NAME = 'fk_refunds_user'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE payment_transaction ADD CONSTRAINT fk_payment_transaction_recorded_by FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE',
        'SELECT 1'
    )
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'payment_transaction'
    AND CONSTRAINT_NAME = 'fk_payment_transaction_recorded_by'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
