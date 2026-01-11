-- =============================================
-- Migration: Adicionar Soft Delete para Usuários
-- Data: 2026-01-11
-- Descrição: Adiciona coluna deleted_at para permitir
--            deleção suave de usuários sem quebrar
--            vínculos com cash_flow, sales, service_orders
-- =============================================

-- Adicionar coluna deleted_at para soft delete
ALTER TABLE users
ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL
COMMENT 'Data de deleção (soft delete)';

-- Criar índice para melhorar performance nas queries
-- que filtram usuários ativos (deleted_at IS NULL)
CREATE INDEX idx_deleted_at ON users(deleted_at);

-- Verificar se a alteração foi aplicada
SELECT 'Migration executada com sucesso!' as status;
