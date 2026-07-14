-- Wing 4: The Registry — Control Plane
-- Council Library V3.0

CREATE DATABASE IF NOT EXISTS agent_registry CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE agent_registry;

CREATE TABLE agents (
    slug VARCHAR(64) PRIMARY KEY,
    display_name VARCHAR(128) NOT NULL,
    role ENUM('lead','director') NOT NULL,
    description MEDIUMTEXT NULL,
    db_name VARCHAR(128) NOT NULL,
    api_key_hash CHAR(64) NOT NULL,
    allowed_scopes JSON NOT NULL,
    rate_limit_rpm INT UNSIGNED DEFAULT 300,
    status ENUM('active','paused','decommissioned') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE specialist_workers (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    worker_id VARCHAR(64) NOT NULL UNIQUE,
    parent_agent_slug VARCHAR(64) NOT NULL,
    specialisation VARCHAR(128) NOT NULL,
    capabilities JSON NOT NULL,
    status ENUM('idle','assigned','busy','offline') DEFAULT 'idle',
    last_heartbeat TIMESTAMP NULL,
    FOREIGN KEY (parent_agent_slug) REFERENCES agents(slug) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE token_budget_ledger (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    tier ENUM('system2_light','system2_heavy') NOT NULL,
    usage_date DATE NOT NULL,
    tokens_used BIGINT UNSIGNED NOT NULL DEFAULT 0,
    daily_limit BIGINT UNSIGNED NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tier_date (tier, usage_date)
) ENGINE=InnoDB;

CREATE TABLE privileged_action_log (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    agent_slug VARCHAR(64) NOT NULL,
    wolf_id VARCHAR(64) NULL,
    action_type ENUM('sql_ddl','sudo_command','schema_alter','production_deploy','destructive_file_op') NOT NULL,
    command_text MEDIUMTEXT NOT NULL,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confirmation_code CHAR(8) NULL,
    confirmed_at TIMESTAMP NULL,
    confirmed_by VARCHAR(64) NULL,
    status ENUM('pending','confirmed','denied','expired') DEFAULT 'pending',
    executed_at TIMESTAMP NULL,
    result_json JSON NULL,
    KEY idx_agent_status (agent_slug, status),
    KEY idx_code (confirmation_code)
) ENGINE=InnoDB;

CREATE TABLE task_queue (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    task_id VARCHAR(128) NOT NULL UNIQUE,
    directive_id VARCHAR(128) NULL,
    source_agent_slug VARCHAR(64) NOT NULL,
    target_agent_slug VARCHAR(64) NOT NULL,
    target_worker_id VARCHAR(64) NULL,
    action VARCHAR(128) NOT NULL,
    payload_json JSON NOT NULL,
    priority ENUM('low','normal','high','critical') DEFAULT 'normal',
    status ENUM('queued','claimed','processing','completed','failed','dead_letter') DEFAULT 'queued',
    claimed_by_worker_id VARCHAR(64) NULL,
    claimed_at TIMESTAMP NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    result_json JSON NULL,
    error_message TEXT NULL,
    retry_count TINYINT UNSIGNED DEFAULT 0,
    max_retries TINYINT UNSIGNED DEFAULT 3,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_target_status (target_agent_slug, status),
    KEY idx_worker_status (claimed_by_worker_id, status),
    KEY idx_priority_created (priority DESC, created_at)
) ENGINE=InnoDB;

CREATE TABLE api_keys (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    key_prefix CHAR(8) NOT NULL,
    key_hash CHAR(64) NOT NULL,
    owner_agent_slug VARCHAR(64) NOT NULL,
    name VARCHAR(128) NOT NULL,
    scopes JSON NOT NULL,
    expires_at TIMESTAMP NULL,
    last_used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_prefix (key_prefix),
    KEY idx_owner (owner_agent_slug)
) ENGINE=InnoDB;
