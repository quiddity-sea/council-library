-- ═══════════════════════════════════════════════════════════════
-- Conversation Extensions for Self Integration
-- ═══════════════════════════════════════════════════════════════

USE agent_curator;
ALTER TABLE conversation_history
    ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45) NULL AFTER wolf_id,
    ADD COLUMN IF NOT EXISTS operator_id INT NULL AFTER ip_address,
    ADD COLUMN IF NOT EXISTS session_summary TEXT NULL AFTER operator_id,
    ADD COLUMN IF NOT EXISTS is_public TINYINT(1) NOT NULL DEFAULT 0 AFTER session_summary,
    ADD INDEX IF NOT EXISTS idx_operator_session (operator_id, session_id);

USE agent_producer;
ALTER TABLE conversation_history
    ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45) NULL AFTER wolf_id,
    ADD COLUMN IF NOT EXISTS operator_id INT NULL AFTER ip_address,
    ADD COLUMN IF NOT EXISTS session_summary TEXT NULL AFTER operator_id,
    ADD COLUMN IF NOT EXISTS is_public TINYINT(1) NOT NULL DEFAULT 0 AFTER session_summary,
    ADD INDEX IF NOT EXISTS idx_operator_session (operator_id, session_id);

USE agent_coach;
ALTER TABLE conversation_history
    ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45) NULL AFTER wolf_id,
    ADD COLUMN IF NOT EXISTS operator_id INT NULL AFTER ip_address,
    ADD COLUMN IF NOT EXISTS session_summary TEXT NULL AFTER operator_id,
    ADD COLUMN IF NOT EXISTS is_public TINYINT(1) NOT NULL DEFAULT 0 AFTER session_summary,
    ADD INDEX IF NOT EXISTS idx_operator_session (operator_id, session_id);

USE agent_director;
ALTER TABLE conversation_history
    ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45) NULL AFTER wolf_id,
    ADD COLUMN IF NOT EXISTS operator_id INT NULL AFTER ip_address,
    ADD COLUMN IF NOT EXISTS session_summary TEXT NULL AFTER operator_id,
    ADD COLUMN IF NOT EXISTS is_public TINYINT(1) NOT NULL DEFAULT 0 AFTER session_summary,
    ADD INDEX IF NOT EXISTS idx_operator_session (operator_id, session_id);

USE agent_wolf;
ALTER TABLE conversation_history
    ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45) NULL AFTER wolf_id,
    ADD COLUMN IF NOT EXISTS operator_id INT NULL AFTER ip_address,
    ADD COLUMN IF NOT EXISTS session_summary TEXT NULL AFTER operator_id,
    ADD COLUMN IF NOT EXISTS is_public TINYINT(1) NOT NULL DEFAULT 0 AFTER session_summary,
    ADD INDEX IF NOT EXISTS idx_operator_session (operator_id, session_id);
