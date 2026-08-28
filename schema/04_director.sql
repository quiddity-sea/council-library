-- Wing 3: The Throne — Director's Strategic Planning
-- Council Library V3.0

CREATE DATABASE IF NOT EXISTS agent_director CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE agent_director;

-- Director also gets the full Sanctum template (§2.2)
-- Run 02_sanctum_template.sql with slug=director first, then this.

CREATE TABLE strategic_plans (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    plan_id VARCHAR(128) NOT NULL UNIQUE,
    title VARCHAR(512) NOT NULL,
    description MEDIUMTEXT NULL,
    status ENUM('draft','active','completed','archived') DEFAULT 'draft',
    priority TINYINT UNSIGNED DEFAULT 50,
    dependencies JSON NULL,
    assigned_agents JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL
) ENGINE=InnoDB;

CREATE TABLE directives (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    directive_id VARCHAR(128) NOT NULL UNIQUE,
    plan_id VARCHAR(128) NULL,
    target_agent_slug VARCHAR(64) NOT NULL,
    target_worker_id VARCHAR(64) NULL,
    action VARCHAR(128) NOT NULL,
    payload_json JSON NOT NULL,
    priority ENUM('low','normal','high','critical') DEFAULT 'normal',
    status ENUM('queued','dispatched','in_progress','completed','failed') DEFAULT 'queued',
    dispatched_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    result_json JSON NULL,
    error_message TEXT NULL,
    KEY idx_target_status (target_agent_slug, status),
    KEY idx_plan (plan_id)
) ENGINE=InnoDB;
