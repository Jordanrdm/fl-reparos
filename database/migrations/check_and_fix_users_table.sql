-- Verificar a estrutura atual da tabela users
DESCRIBE users;

-- Se o campo 'role' não existir ou estiver com tipo errado, executar:
-- Primeiro, remover a coluna role se existir
ALTER TABLE users DROP COLUMN IF EXISTS role;

-- Adicionar novamente com o tipo correto
ALTER TABLE users
ADD COLUMN role ENUM('admin', 'manager', 'operator') DEFAULT 'operator' AFTER password;

-- Remover a coluna status se existir
ALTER TABLE users DROP COLUMN IF EXISTS status;

-- Adicionar novamente com o tipo correto
ALTER TABLE users
ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active' AFTER role;

-- Atualizar o primeiro usuário para admin
UPDATE users SET role = 'admin', status = 'active' WHERE id = 1;

-- Verificar o resultado
SELECT id, name, email, role, status FROM users;
