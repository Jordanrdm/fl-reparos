-- =========================================================
-- Migration: Adicionar campo installments na tabela service_orders
-- Data: 2025-01-02
-- Objetivo: Registrar número de parcelas quando pagamento for cartão de crédito
-- =========================================================

ALTER TABLE service_orders
  ADD COLUMN installments INT DEFAULT 1
  COMMENT 'Número de parcelas (cartão crédito)'
  AFTER payment_method;

-- Verificar resultado
SELECT 'Campo installments adicionado com sucesso!' as status;
DESCRIBE service_orders;
