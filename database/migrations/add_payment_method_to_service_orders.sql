-- =========================================================
-- Migration: Adicionar campo payment_method na tabela service_orders
-- Data: 2025-01-02
-- Objetivo: Permitir registrar a forma de pagamento da OS
-- =========================================================

ALTER TABLE service_orders
  ADD COLUMN payment_method ENUM('dinheiro', 'pix', 'cartao_credito', 'cartao_debito') NULL
  COMMENT 'Forma de pagamento da ordem de serviço'
  AFTER total_cost;

-- Verificar resultado
SELECT 'Campo payment_method adicionado com sucesso!' as status;
DESCRIBE service_orders;
