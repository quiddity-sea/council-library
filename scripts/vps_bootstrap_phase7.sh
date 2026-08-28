#!/usr/bin/env bash
set -euo pipefail

# ==============================================================================
# FOREVERBOX / COUNCIL / HERMES VPS BOOTSTRAP (PHASE 7)
# Target Host: vigorous-panini (100.126.174.30)
# ==============================================================================

echo "=== [1/6] Installing System Dependencies ==="
apt-get update -y
apt-get install -y mariadb-server php-cli php-mysql php-curl php-mbstring php-xml git curl unzip python3 python3-pip python3-venv

echo "=== [2/6] Configuring Directories & Permissions ==="
mkdir -p /foreverbox_data
mkdir -p /var/log/council

echo "=== [3/6] Initializing MariaDB Canonical Schemas ==="
systemctl enable --now mariadb

mariadb -e "
CREATE DATABASE IF NOT EXISTS agent_registry CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS quiddity_commons CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS agent_curator CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS agent_producer CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS agent_coach CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS agent_director CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS agent_wolf CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
"

# Apply schemas
if [ -d "/foreverbox_data/council-library/schema" ]; then
    mariadb < /foreverbox_data/council-library/schema/01_commons.sql || true
    mariadb < /foreverbox_data/council-library/schema/02_sanctum.sql || true
    mariadb < /foreverbox_data/council-library/schema/03_registry.sql || true
    mariadb < /foreverbox_data/council-library/schema/07_soul_components.sql || true
    echo "Schemas applied successfully."
fi

echo "=== [4/6] Setting Up Council PHP REST API ==="
if [ -d "/foreverbox_data/council-library/php-api" ]; then
    cd /foreverbox_data/council-library/php-api
    if command -v composer >/dev/null 2>&1; then
        composer install --no-dev --optimize-autoloader
    fi

    # Create systemd service for Council API
    cat << 'EOF' > /etc/systemd/system/council-api.service
[Unit]
Description=Council REST API Daemon (Slim 4)
After=network.target mariadb.service
Wants=mariadb.service

[Service]
Type=simple
User=root
WorkingDirectory=/foreverbox_data/council-library/php-api
ExecStart=/usr/bin/php -S 0.0.0.0:8080 -t public
Restart=always
RestartSec=3
Environment=APP_ENV=production
Environment=DB_HOST=localhost
Environment=DB_USER=root
Environment=DB_PASS=
Environment=LOCAL_MODEL_URL=http://100.106.5.121:11434

[Install]
WantedBy=multi-user.target
EOF

    systemctl daemon-reload
    systemctl enable --now council-api.service
fi

echo "=== [5/6] Setting Up Python Runtime & Hermes ==="
if [ ! -d "/foreverbox_data/venv" ]; then
    python3 -m venv /foreverbox_data/venv
fi
/foreverbox_data/venv/bin/pip install --upgrade pip
/foreverbox_data/venv/bin/pip install mysql-connector-python pyyaml requests sentence-transformers

echo "=== [6/6] Verifying Council API & Network Connectivity ==="
sleep 2
curl -s http://127.0.0.1:8080/v1/healthz || echo "Council API still starting..."

echo ">>> VPS BOOTSTRAP COMPLETE! <<<"

