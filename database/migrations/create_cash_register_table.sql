CREATE TABLE IF NOT EXISTS cash_register (
    id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NOT NULL,

    opening_date DATETIME NOT NULL,
    closing_date DATETIME NULL,

    opening_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    closing_balance DECIMAL(10,2) NULL,

    total_sales DECIMAL(10,2) DEFAULT 0.00,
    total_expenses DECIMAL(10,2) DEFAULT 0.00,
    total_cash DECIMAL(10,2) DEFAULT 0.00,
    total_card DECIMAL(10,2) DEFAULT 0.00,
    total_pix DECIMAL(10,2) DEFAULT 0.00,

    observations TEXT NULL,

    status ENUM('open', 'closed') DEFAULT 'open',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_cash_register_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,

    INDEX idx_status (status),
    INDEX idx_opening_date (opening_date),
    INDEX idx_user_status (user_id, status)
);
