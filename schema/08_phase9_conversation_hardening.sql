-- ═══════════════════════════════════════════════════════════════
-- Phase 9: Canonical Conversation System Schema Enhancements
-- ═══════════════════════════════════════════════════════════════

USE agent_curator;
ALTER TABLE conversation_history
    ADD COLUMN IF NOT EXISTS source_interface ENUM('self_public', 'self_admin', 'from_the_noise', 'hermes_cli', 'other') NOT NULL DEFAULT 'self_public' AFTER is_public,
    ADD COLUMN IF NOT EXISTS head_used VARCHAR(128) NULL AFTER source_interface,
    ADD COLUMN IF NOT EXISTS request_id VARCHAR(64) NULL AFTER head_used,
    ADD INDEX IF NOT EXISTS idx_source_interface (source_interface);

USE agent_producer;
ALTER TABLE conversation_history
    ADD COLUMN IF NOT EXISTS source_interface ENUM('self_public', 'self_admin', 'from_the_noise', 'hermes_cli', 'other') NOT NULL DEFAULT 'self_public' AFTER is_public,
    ADD COLUMN IF NOT EXISTS head_used VARCHAR(128) NULL AFTER source_interface,
    ADD COLUMN IF NOT EXISTS request_id VARCHAR(64) NULL AFTER head_used,
    ADD INDEX IF NOT EXISTS idx_source_interface (source_interface);

USE agent_coach;
ALTER TABLE conversation_history
    ADD COLUMN IF NOT EXISTS source_interface ENUM('self_public', 'self_admin', 'from_the_noise', 'hermes_cli', 'other') NOT NULL DEFAULT 'self_public' AFTER is_public,
    ADD COLUMN IF NOT EXISTS head_used VARCHAR(128) NULL AFTER source_interface,
    ADD COLUMN IF NOT EXISTS request_id VARCHAR(64) NULL AFTER head_used,
    ADD INDEX IF NOT EXISTS idx_source_interface (source_interface);

USE agent_director;
ALTER TABLE conversation_history
    ADD COLUMN IF NOT EXISTS source_interface ENUM('self_public', 'self_admin', 'from_the_noise', 'hermes_cli', 'other') NOT NULL DEFAULT 'self_public' AFTER is_public,
    ADD COLUMN IF NOT EXISTS head_used VARCHAR(128) NULL AFTER source_interface,
    ADD COLUMN IF NOT EXISTS request_id VARCHAR(64) NULL AFTER head_used,
    ADD INDEX IF NOT EXISTS idx_source_interface (source_interface);

USE agent_wolf;
ALTER TABLE conversation_history
    ADD COLUMN IF NOT EXISTS source_interface ENUM('self_public', 'self_admin', 'from_the_noise', 'hermes_cli', 'other') NOT NULL DEFAULT 'self_public' AFTER is_public,
    ADD COLUMN IF NOT EXISTS head_used VARCHAR(128) NULL AFTER source_interface,
    ADD COLUMN IF NOT EXISTS request_id VARCHAR(64) NULL AFTER head_used,
    ADD INDEX IF NOT EXISTS idx_source_interface (source_interface);
