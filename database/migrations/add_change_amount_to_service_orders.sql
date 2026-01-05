-- =========================================================
-- Migration: Adicionar campo change_amount na tabela service_orders
-- Data: 2025-01-02
-- Objetivo: Registrar valor do troco quando pagamento for em dinheiro
-- =========================================================

ALTER TABLE service_orders
  ADD COLUMN change_amount DECIMAL(10,2) DEFAULT 0.00
  COMMENT 'Valor do troco (quando pagamento em dinheiro)'
  AFTER installments;

-- Verificar resultado
SELECT 'Campo change_amount adicionado com sucesso!' as status;
DESCRIBE service_orders;
