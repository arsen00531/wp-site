#!/usr/bin/env bash
# Повторный выпуск SSL после открытия портов 80/443 в панели VPS
set -euo pipefail
DOMAIN="${1:-wp.connect-birga-test.ru}"
EMAIL="${2:-admin@connect-birga-test.ru}"

sudo certbot --nginx -d "${DOMAIN}" --non-interactive --agree-tos -m "${EMAIL}" --redirect

cd ~/wp-site
sudo docker compose exec -T wordpress wp option update home "https://${DOMAIN}" --allow-root
sudo docker compose exec -T wordpress wp option update siteurl "https://${DOMAIN}" --allow-root
sudo docker compose exec -T wordpress wp search-replace "http://${DOMAIN}" "https://${DOMAIN}" --all-tables --allow-root

echo "SSL настроен для https://${DOMAIN}"
