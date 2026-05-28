#!/usr/bin/env bash
# Развёртывание на VPS (пользователь arsen00531, nginx + certbot)
# Использование на сервере: cd ~/wp-site && bash scripts/deploy-server.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

DOMAIN="${DOMAIN:-wp.connect-birga-test.ru}"
WP_PORT="${WP_PORT:-9080}"
PROXY_PORT="127.0.0.1:${WP_PORT}"

if [[ ! -f .env ]]; then
  MYSQL_PASS=$(openssl rand -hex 12)
  MYSQL_ROOT=$(openssl rand -hex 12)
  ADMIN_PASS=$(openssl rand -hex 8)
  cat > .env <<EOF
WP_PORT=${WP_PORT}
MYSQL_DATABASE=wordpress
MYSQL_USER=wordpress
MYSQL_PASSWORD=${MYSQL_PASS}
MYSQL_ROOT_PASSWORD=${MYSQL_ROOT}
WP_URL=https://${DOMAIN}
WP_TITLE=Festival PR3
WP_ADMIN_USER=admin
WP_ADMIN_PASSWORD=${ADMIN_PASS}
WP_ADMIN_EMAIL=admin@${DOMAIN#wp.}"
EOF
  chmod 600 .env
  echo "ADMIN_PASSWORD=${ADMIN_PASS}" > ~/.wp-site-credentials
  chmod 600 ~/.wp-site-credentials
fi

set -a
# shellcheck disable=SC1091
source .env
set +a

# Один проброс порта только на localhost
if ! grep -q "127.0.0.1:${WP_PORT}:80" docker-compose.yml 2>/dev/null; then
  sed -i "s|\"\\${WP_PORT:-8080}:80\"|\"127.0.0.1:${WP_PORT}:80\"|" docker-compose.yml || \
    sed -i "s|- \"\${WP_PORT:-8080}:80\"|- \"127.0.0.1:${WP_PORT}:80\"|" docker-compose.yml
fi
rm -f docker-compose.override.yml

echo "→ Docker Compose..."
sudo docker compose up -d

for i in {1..60}; do
  if curl -sf "http://${PROXY_PORT}/" >/dev/null 2>&1; then
    break
  fi
  sleep 2
done

sudo docker compose exec -T wordpress bash -lc '
  if ! command -v wp >/dev/null 2>&1; then
    curl -fsSL -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
    chmod +x /usr/local/bin/wp
  fi
'

if ! sudo docker compose exec -T wordpress wp core is-installed --allow-root 2>/dev/null; then
  sudo docker compose exec -T wordpress wp core install \
    --url="http://${DOMAIN}" \
    --title="${WP_TITLE}" \
    --admin_user="${WP_ADMIN_USER}" \
    --admin_password="${WP_ADMIN_PASSWORD}" \
    --admin_email="${WP_ADMIN_EMAIL}" \
    --skip-email --allow-root
fi

sudo docker compose exec -T wordpress wp plugin activate festival-pr3-landing --allow-root 2>/dev/null || true
sudo docker compose exec -T wordpress wp eval 'if ( function_exists( "festival_pr4_setup" ) ) { festival_pr4_setup(); }' --allow-root 2>/dev/null || true

echo "→ Nginx..."
sudo tee "/etc/nginx/sites-available/${DOMAIN}" > /dev/null <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN};

    client_max_body_size 64M;

    location / {
        proxy_pass http://${PROXY_PORT};
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_read_timeout 300s;
    }
}
NGINX

sudo ln -sf "/etc/nginx/sites-available/${DOMAIN}" "/etc/nginx/sites-enabled/${DOMAIN}"
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx

echo "→ Certbot (нужны открытые порты 80/443 с интернета)..."
if sudo certbot --nginx -d "${DOMAIN}" --non-interactive --agree-tos -m "${WP_ADMIN_EMAIL}" --redirect; then
  sudo docker compose exec -T wordpress wp option update home "https://${DOMAIN}" --allow-root
  sudo docker compose exec -T wordpress wp option update siteurl "https://${DOMAIN}" --allow-root
  sudo docker compose exec -T wordpress wp search-replace "http://${DOMAIN}" "https://${DOMAIN}" --all-tables --allow-root
fi

echo ""
echo "Готово."
echo "  Сайт:    http://${DOMAIN} (или https после certbot)"
echo "  Админка: /wp-admin"
echo "  Логин:   ${WP_ADMIN_USER}"
echo "  Пароль:  см. ~/.wp-site-credentials"
