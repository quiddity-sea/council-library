-- ═══════════════════════════════════════════════════════════════
-- Wing 5: User-Agent Assignments
-- Authority for which users can access which agents and with what permissions
-- ═══════════════════════════════════════════════════════════════

USE agent_registry;

CREATE TABLE IF NOT EXISTS user_agent_assignments (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL COMMENT 'References the user in the Self layer',
    agent_id        VARCHAR(50) NOT NULL COMMENT 'Agent slug (zeon7, leon, gemma, otec, wolf)',
    template_id     VARCHAR(100) NOT NULL DEFAULT 'default' COMMENT 'UI template to render',
    permissions     JSON NOT NULL DEFAULT '["chat"]' COMMENT 'Array of permitted capabilities',
    memory_scope    VARCHAR(50) NOT NULL DEFAULT 'public' COMMENT 'Memory access scope: public, shared, full',
    status          ENUM('active', 'suspended', 'revoked') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_user_agent (user_id, agent_id),
    INDEX idx_user_assignments (user_id, status),
    INDEX idx_agent_assignments (agent_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: Give prime operator (user_id = 1) full access to all agents
INSERT IGNORE INTO user_agent_assignments (user_id, agent_id, template_id, permissions, memory_scope) VALUES
(1, 'zeon7', 'zeon7-cockpit',  '["chat","search","memory","knowledge","news_desk","vision","blog","admin"]', 'full'),
(1, 'leon',  'leon-workspace', '["chat","search","memory","knowledge","tasks","admin"]', 'full'),
(1, 'gemma', 'gemma-dashboard','["chat","search","memory","wellness","admin"]', 'full'),
(1, 'otec',  'otec-observatory','["chat","search","memory","orchestration","admin"]', 'full');
