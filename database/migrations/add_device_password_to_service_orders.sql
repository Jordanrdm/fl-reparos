-- =============================================
-- Migration: Adicionar Senha do Aparelho em OS
-- Data: 2026-01-11
-- Descrição: Adiciona campo para registrar senha/padrão
--            do aparelho do cliente na ordem de serviço
-- =============================================

-- Adicionar coluna device_password para armazenar senha/padrão
ALTER TABLE service_orders
ADD COLUMN device_password VARCHAR(100) NULL DEFAULT NULL
COMMENT 'Senha ou padrão do aparelho'
AFTER device_powers_on;

-- Verificar se a alteração foi aplicada
SELECT 'Migration executada com sucesso! Campo device_password adicionado.' as status;
