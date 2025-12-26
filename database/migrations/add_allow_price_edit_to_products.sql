-- Migration: Adicionar campo para controlar edição de preço na venda
-- Data: 2025-12-25

-- Adicionar campo allow_price_edit na tabela products
ALTER TABLE products
ADD COLUMN allow_price_edit TINYINT(1) DEFAULT 1 COMMENT 'Permite editar preço na venda (0=Não, 1=Sim)' AFTER price;

-- Por padrão, todos os produtos permitem edição (valor 1)
-- Você pode atualizar manualmente os produtos que NÃO devem permitir edição:
-- UPDATE products SET allow_price_edit = 0 WHERE name LIKE '%capa%' OR name LIKE '%película%';

-- Verificar resultado
SELECT id, name, price, allow_price_edit FROM products LIMIT 10;
