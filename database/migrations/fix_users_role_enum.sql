-- Corrigir ENUM do campo role na tabela users
-- De: enum('seller', 'technician')
-- Para: enum('admin', 'manager', 'operator')

-- Passo 1: Alterar o tipo ENUM
ALTER TABLE users
MODIFY COLUMN role ENUM('admin', 'manager', 'operator', 'seller', 'technician') DEFAULT NULL;

-- Passo 2: Migrar dados antigos para novos valores
UPDATE users SET role = 'admin' WHERE role = 'seller' OR id = 1;
UPDATE users SET role = 'operator' WHERE role = 'technician';

-- Passo 3: Definir role para usuários que estão NULL
UPDATE users SET role = 'operator' WHERE role IS NULL;

-- Passo 4: Remover valores antigos do ENUM (opcional, deixar por compatibilidade)
-- ALTER TABLE users
-- MODIFY COLUMN role ENUM('admin', 'manager', 'operator') DEFAULT 'operator';

-- Verificar resultado
SELECT id, name, email, role, status FROM users ORDER BY id;
