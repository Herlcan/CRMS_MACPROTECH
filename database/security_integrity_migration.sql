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

-- Add business-code uniqueness when existing data is already clean.
SET @sql := (
    SELECT IF(
        (
            SELECT COUNT(*)
            FROM (
                SELECT INDEX_NAME
                FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'users'
                AND NON_UNIQUE = 0
                GROUP BY INDEX_NAME
                HAVING GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') = 'username'
            ) existing_unique
        ) = 0
        AND (
            SELECT COUNT(*)
            FROM (
                SELECT username
                FROM users
                GROUP BY username
                HAVING COUNT(*) > 1
            ) duplicate_values
        ) = 0,
        'ALTER TABLE users ADD UNIQUE KEY uq_users_username (username)',
        'SELECT 1'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        (
            SELECT COUNT(*)
            FROM (
                SELECT INDEX_NAME
                FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'users'
                AND NON_UNIQUE = 0
                GROUP BY INDEX_NAME
                HAVING GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') = 'email'
            ) existing_unique
        ) = 0
        AND (
            SELECT COUNT(*)
            FROM (
                SELECT email
                FROM users
                GROUP BY email
                HAVING COUNT(*) > 1
            ) duplicate_values
        ) = 0,
        'ALTER TABLE users ADD UNIQUE KEY uq_users_email (email)',
        'SELECT 1'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        (
            SELECT COUNT(*)
            FROM (
                SELECT INDEX_NAME
                FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'work_order'
                AND NON_UNIQUE = 0
                GROUP BY INDEX_NAME
                HAVING GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') = 'code'
            ) existing_unique
        ) = 0
        AND (
            SELECT COUNT(*)
            FROM (
                SELECT code
                FROM work_order
                GROUP BY code
                HAVING COUNT(*) > 1
            ) duplicate_values
        ) = 0,
        'ALTER TABLE work_order ADD UNIQUE KEY uq_work_order_code (code)',
        'SELECT 1'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        (
            SELECT COUNT(*)
            FROM (
                SELECT INDEX_NAME
                FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'payments'
                AND NON_UNIQUE = 0
                GROUP BY INDEX_NAME
                HAVING GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') = 'payment_code'
            ) existing_unique
        ) = 0
        AND (
            SELECT COUNT(*)
            FROM (
                SELECT payment_code
                FROM payments
                GROUP BY payment_code
                HAVING COUNT(*) > 1
            ) duplicate_values
        ) = 0,
        'ALTER TABLE payments ADD UNIQUE KEY uq_payments_payment_code (payment_code)',
        'SELECT 1'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        (
            SELECT COUNT(*)
            FROM (
                SELECT INDEX_NAME
                FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'items'
                AND NON_UNIQUE = 0
                GROUP BY INDEX_NAME
                HAVING GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') = 'product_code'
            ) existing_unique
        ) = 0
        AND (
            SELECT COUNT(*)
            FROM (
                SELECT product_code
                FROM items
                GROUP BY product_code
                HAVING COUNT(*) > 1
            ) duplicate_values
        ) = 0,
        'ALTER TABLE items ADD UNIQUE KEY uq_items_product_code (product_code)',
        'SELECT 1'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

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
