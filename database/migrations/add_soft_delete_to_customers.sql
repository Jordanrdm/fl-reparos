-- =============================================
-- Migration: Adicionar Soft Delete para Clientes
-- Data: 2026-01-11
-- Descrição: Adiciona coluna deleted_at para permitir
--            deleção suave de clientes sem quebrar
--            vínculos com service_orders e sales
-- =============================================

-- Adicionar coluna deleted_at para soft delete
ALTER TABLE customers
ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL
COMMENT 'Data de deleção (soft delete)';

-- Criar índice para melhorar performance nas queries
-- que filtram clientes ativos (deleted_at IS NULL)
CREATE INDEX idx_deleted_at ON customers(deleted_at);

-- Verificar se a alteração foi aplicada
SELECT 'Migration executada com sucesso!' as status;
