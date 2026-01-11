-- =============================================
-- Migration: Adicionar Novos Campos ao Checklist de OS
-- Data: 2026-01-11
-- Descrição: Adiciona novos campos ao checklist
--            (mantém campos antigos para uso futuro)
-- =============================================

-- Adicionar novos campos de checklist
ALTER TABLE service_orders
ADD COLUMN checklist_lens_condition VARCHAR(50) NULL DEFAULT NULL COMMENT 'Lente: arranhada/trincada/sem' AFTER checklist_lens,
ADD COLUMN checklist_back_cover VARCHAR(100) NULL DEFAULT NULL COMMENT 'Tampa: trincada/detalhes' AFTER checklist_lens_condition,
ADD COLUMN checklist_screen TINYINT(1) DEFAULT 0 COMMENT 'Tela trincada' AFTER checklist_back_cover,
ADD COLUMN checklist_connector TINYINT(1) DEFAULT 0 COMMENT 'Conector' AFTER checklist_screen,
ADD COLUMN checklist_camera_front_back VARCHAR(50) NULL DEFAULT NULL COMMENT 'Câmera: frontal/traseira' AFTER checklist_connector;

-- Verificar alteração
SELECT 'Migration executada com sucesso! Novos campos de checklist adicionados.' as status;
