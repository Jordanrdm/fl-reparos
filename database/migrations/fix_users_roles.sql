-- Script para verificar e corrigir roles dos usuários

-- Verificar quais usuários não têm role definido
SELECT id, name, email, role, status
FROM users;

-- Atualizar usuários sem role para 'operator' por padrão
UPDATE users
SET role = 'operator'
WHERE role IS NULL OR role = '';

-- Atualizar o primeiro usuário para admin se necessário
UPDATE users
SET role = 'admin', status = 'active'
WHERE id = 1;

-- Verificar novamente após atualização
SELECT id, name, email, role, status
FROM users;
