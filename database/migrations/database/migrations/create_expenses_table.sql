CREATE TABLE expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,

    description VARCHAR(255) NOT NULL,

    type ENUM('fixa', 'variavel', 'fornecedor') NOT NULL,

    amount DECIMAL(10,2) NOT NULL,

    expense_date DATE NOT NULL,

    supplier_id INT NULL,

    payment_method ENUM('dinheiro', 'pix', 'cartao', 'boleto') NOT NULL,

    status ENUM('pago', 'pendente') DEFAULT 'pendente',

    observations TEXT NULL,

    user_id INT NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_expenses_supplier
        FOREIGN KEY (supplier_id) REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_expenses_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
);
