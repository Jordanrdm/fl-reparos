-- =========================================================
-- Migration: Refatorar tabela service_orders com novos campos
-- Data: 2025-12-25
-- Objetivo: Adicionar campos solicitados pelo cliente
-- =========================================================

-- IMPORTANTE: Execute este script inteiro de uma vez no DBeaver
-- Selecione tudo (Ctrl+A) e execute (Ctrl+Enter)

-- Adicionar todos os campos de uma vez
ALTER TABLE service_orders
  ADD COLUMN entry_datetime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER customer_id,
  ADD COLUMN exit_datetime DATETIME NULL AFTER entry_datetime,
  ADD COLUMN technical_report TEXT NULL AFTER status,
  ADD COLUMN reported_problem TEXT NULL AFTER technical_report,
  ADD COLUMN customer_observations TEXT NULL AFTER reported_problem,
  ADD COLUMN internal_observations TEXT NULL AFTER customer_observations,
  ADD COLUMN device_powers_on ENUM('sim', 'nao') DEFAULT 'sim' AFTER internal_observations,
  ADD COLUMN checklist_case TINYINT(1) DEFAULT 0 COMMENT 'Capa' AFTER device_powers_on,
  ADD COLUMN checklist_screen_protector TINYINT(1) DEFAULT 0 COMMENT 'Película' AFTER checklist_case,
  ADD COLUMN checklist_camera TINYINT(1) DEFAULT 0 COMMENT 'Câmera' AFTER checklist_screen_protector,
  ADD COLUMN checklist_housing TINYINT(1) DEFAULT 0 COMMENT 'Carcaça' AFTER checklist_camera,
  ADD COLUMN checklist_lens TINYINT(1) DEFAULT 0 COMMENT 'Lente' AFTER checklist_housing,
  ADD COLUMN checklist_face_id TINYINT(1) DEFAULT 0 COMMENT 'Face ID' AFTER checklist_lens,
  ADD COLUMN checklist_sim_card TINYINT(1) DEFAULT 0 COMMENT 'Chip' AFTER checklist_face_id,
  ADD COLUMN checklist_battery TINYINT(1) DEFAULT 0 COMMENT 'Bateria' AFTER checklist_sim_card,
  ADD COLUMN checklist_charger TINYINT(1) DEFAULT 0 COMMENT 'Carregador' AFTER checklist_battery,
  ADD COLUMN checklist_headphones TINYINT(1) DEFAULT 0 COMMENT 'Fone' AFTER checklist_charger,
  ADD COLUMN technician_name VARCHAR(255) NULL AFTER checklist_headphones,
  ADD COLUMN attendant_name VARCHAR(255) NULL AFTER technician_name;

-- Copiar dados de created_at para entry_datetime (para registros existentes)
UPDATE service_orders SET entry_datetime = created_at WHERE entry_datetime IS NULL OR entry_datetime = '0000-00-00 00:00:00';

-- Verificar resultado
SELECT 'Migration executada com sucesso!' as status;
DESCRIBE service_orders;
