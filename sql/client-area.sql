CREATE TABLE clients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    name VARCHAR(255) DEFAULT NULL,
    hosting_location VARCHAR(255) DEFAULT NULL,
    hosting_storage_gb INT UNSIGNED DEFAULT NULL,
    hosting_cpu_cores INT UNSIGNED DEFAULT NULL,
    hosting_memory_gb INT UNSIGNED DEFAULT NULL,
    hosting_payment_cycle VARCHAR(100) DEFAULT NULL,
    hosting_cost VARCHAR(255) DEFAULT NULL,
    hosting_last_payment_date DATE DEFAULT NULL,
    hosting_custom_text TEXT DEFAULT NULL,
    maintenance_type VARCHAR(50) DEFAULT NULL,
    maintenance_cost VARCHAR(255) DEFAULT NULL,
    maintenance_last_payment_date DATE DEFAULT NULL,
    maintenance_billing_frequency VARCHAR(100) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY clients_email_unique (email)
);

CREATE TABLE magic_login_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    email VARCHAR(255) NOT NULL,
    selector CHAR(16) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    requested_ip VARCHAR(45) DEFAULT NULL,
    requested_user_agent VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY magic_login_tokens_selector_unique (selector),
    KEY magic_login_tokens_client_id_index (client_id),
    KEY magic_login_tokens_expires_at_index (expires_at),
    CONSTRAINT magic_login_tokens_client_id_fk
        FOREIGN KEY (client_id) REFERENCES clients (id)
        ON DELETE CASCADE
);
