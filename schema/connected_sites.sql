-- Connected Sites Registry — tracks every website linked through foreverbox_data/connected_sites/
-- Each site gets an embedding of its description + purpose for semantic search via /v1/commons/search

CREATE TABLE IF NOT EXISTS quiddity_commons.connected_sites (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(128) NOT NULL UNIQUE,
    domain VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    purpose TEXT,
    main_vectors JSON,
    filter_tags JSON,
    creator VARCHAR(64) NOT NULL DEFAULT 'leon',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by VARCHAR(64) DEFAULT 'leon',
    web_root_path VARCHAR(512) NOT NULL,
    symlink_path VARCHAR(512),
    is_active TINYINT(1) DEFAULT 1,
    INDEX idx_active (is_active),
    INDEX idx_domain (domain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
