-- Wing 2: Sanctum Template — Sovereign Agent Memory
-- Run once per Lead, replacing {slug} with agent slug
-- Council Library V3.0

CREATE DATABASE IF NOT EXISTS agent_{slug} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE agent_{slug};

CREATE TABLE soul (
    id TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
    agent_slug VARCHAR(64) NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    identity_yaml MEDIUMTEXT NOT NULL,
    protocols_yaml MEDIUMTEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by VARCHAR(64) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE user_context (
    id TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
    agent_slug VARCHAR(64) NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    profile_yaml MEDIUMTEXT NOT NULL,
    relationship_notes MEDIUMTEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE memory_lore (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    agent_slug VARCHAR(64) NOT NULL,
    namespace VARCHAR(128) NOT NULL,
    key_name VARCHAR(256) NOT NULL,
    content_json JSON NOT NULL,
    content_text MEDIUMTEXT NULL,
    source_type ENUM('user_directive','session_extraction','document_ingestion','wolf_synthesis') NOT NULL,
    source_ref VARCHAR(256) NULL,
    importance TINYINT UNSIGNED DEFAULT 50,
    tags JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    UNIQUE KEY uk_agent_ns_key (agent_slug, namespace, key_name),
    KEY idx_ns_importance (namespace, importance DESC),
    KEY idx_tags (tags),
    FULLTEXT KEY ft_content (content_text)
) ENGINE=InnoDB;

CREATE TABLE conversation_history (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    agent_slug VARCHAR(64) NOT NULL,
    session_id VARCHAR(128) NOT NULL,
    message_seq INT UNSIGNED NOT NULL,
    role ENUM('user','assistant','system','tool') NOT NULL,
    content_text MEDIUMTEXT NOT NULL,
    reasoning_content LONGTEXT,
    tool_calls JSON NULL,
    tool_results JSON NULL,
    tokens_input INT UNSIGNED NULL,
    tokens_output INT UNSIGNED NULL,
    model_used VARCHAR(128) NULL,
    wolf_id VARCHAR(64) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_session_seq (agent_slug, session_id, message_seq),
    KEY idx_session_created (session_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE wolf_sessions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    wolf_id VARCHAR(64) NOT NULL,
    parent_lead_slug VARCHAR(64) NOT NULL,
    status ENUM('idle','working','blocked','error','terminated') DEFAULT 'idle',
    current_task_json JSON NULL,
    current_task_id VARCHAR(128) NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    error_message TEXT NULL,
    heartbeats_missed TINYINT UNSIGNED DEFAULT 0,
    UNIQUE KEY uk_wolf (wolf_id),
    KEY idx_lead_status (parent_lead_slug, status)
) ENGINE=InnoDB;

CREATE TABLE wolf_working_memory (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    wolf_id VARCHAR(64) NOT NULL,
    namespace VARCHAR(128) NOT NULL,
    key_name VARCHAR(256) NOT NULL,
    value_json JSON NOT NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_wolf_ns_key (wolf_id, namespace, key_name),
    KEY idx_expires (expires_at)
) ENGINE=InnoDB;
