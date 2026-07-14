-- Wing 1: The Commons — Shared Knowledge Repository
-- Council Library V3.0

CREATE DATABASE IF NOT EXISTS quiddity_commons CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE quiddity_commons;

CREATE TABLE quiddity_files (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    relative_path VARCHAR(1024) NOT NULL,
    content_hash CHAR(64) NOT NULL,
    mime_type VARCHAR(128) DEFAULT 'text/markdown',
    file_size_bytes INT UNSIGNED,
    last_modified TIMESTAMP NOT NULL,
    indexed_at TIMESTAMP NULL,
    indexing_status ENUM('pending','processing','indexed','failed') DEFAULT 'pending',
    error_message TEXT NULL,
    UNIQUE KEY uk_path (relative_path),
    KEY idx_status (indexing_status)
) ENGINE=InnoDB;

CREATE TABLE quiddity_vector_references (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    file_id BIGINT UNSIGNED NOT NULL,
    chunk_index INT UNSIGNED NOT NULL,
    chunk_text MEDIUMTEXT NOT NULL,
    chunk_token_count INT UNSIGNED NOT NULL,
    embedding VECTOR(1024) NOT NULL,
    chunk_metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (file_id) REFERENCES quiddity_files(id) ON DELETE CASCADE,
    KEY idx_file_chunk (file_id, chunk_index)
) ENGINE=InnoDB;

ALTER TABLE quiddity_vector_references
    ADD VECTOR INDEX idx_vector_hnsw (embedding);

CREATE TABLE conversation_vectors (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    agent_slug VARCHAR(64) NOT NULL,
    session_id VARCHAR(128) NOT NULL,
    message_id BIGINT UNSIGNED NOT NULL,
    role ENUM('user','assistant','system','tool') NOT NULL,
    content_text MEDIUMTEXT NOT NULL,
    embedding VECTOR(1024) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_agent_session (agent_slug, session_id),
    KEY idx_created (created_at)
) ENGINE=InnoDB;

ALTER TABLE conversation_vectors
    ADD VECTOR INDEX idx_conv_vector_hnsw (embedding);

CREATE TABLE ingestion_dead_letter (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    file_id BIGINT UNSIGNED NOT NULL,
    chunk_index INT UNSIGNED NOT NULL,
    chunk_text MEDIUMTEXT NOT NULL,
    error_message TEXT NOT NULL,
    error_trace TEXT NULL,
    retry_count TINYINT UNSIGNED DEFAULT 0,
    max_retries TINYINT UNSIGNED DEFAULT 5,
    last_attempted_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (file_id) REFERENCES quiddity_files(id) ON DELETE CASCADE,
    KEY idx_retry (retry_count, max_retries)
) ENGINE=InnoDB;

CREATE TABLE quiddity_folder_centroids (
    id SMALLINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    folder_name VARCHAR(128) NOT NULL UNIQUE,
    centroid VECTOR(1024) NOT NULL,
    sample_count INT UNSIGNED NOT NULL,
    rebuilt_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

ALTER TABLE quiddity_folder_centroids
    ADD VECTOR INDEX idx_centroid_vector (centroid);
