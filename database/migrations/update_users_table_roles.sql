-- Adicionar campo role (perfil) na tabela users
ALTER TABLE users
ADD COLUMN IF NOT EXISTS role ENUM('admin', 'manager', 'operator') DEFAULT 'operator' AFTER password;

-- Adicionar campo status (ativo/inativo)
ALTER TABLE users
ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive') DEFAULT 'active' AFTER role;

-- Adicionar índices para melhor performance
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_role (role);
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_status (status);

-- Garantir que o primeiro usuário seja admin
UPDATE users SET role = 'admin' WHERE id = 1;
