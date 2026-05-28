#!/usr/bin/env bash
# Автоустановка WordPress + тема Twenty Twenty-Five + лендинг PR3
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ ! -f .env ]]; then
  cp .env.example .env
  echo "Создан .env из .env.example — при необходимости отредактируйте пароли."
fi

set -a
# shellcheck disable=SC1091
source .env
set +a

echo "→ Запуск контейнеров..."
docker compose up -d

echo "→ Ожидание WordPress..."
for i in {1..60}; do
  if curl -sf "${WP_URL:-http://localhost:8080}" >/dev/null 2>&1; then
    break
  fi
  sleep 2
done

WP_USER="${WP_ADMIN_USER:-admin}"
WP_PASS="${WP_ADMIN_PASSWORD:-admin123}"
WP_MAIL="${WP_ADMIN_EMAIL:-student@example.com}"
WP_SITE="${WP_TITLE:-Фестиваль цифрового искусства}"
WP_URL="${WP_URL:-http://localhost:8080}"

ensure_wp_cli() {
  docker compose exec -T wordpress bash -lc '
    if command -v wp >/dev/null 2>&1; then
      exit 0
    fi
    curl -fsSL -o /tmp/wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
    chmod +x /tmp/wp-cli.phar
    mv /tmp/wp-cli.phar /usr/local/bin/wp
  '
}

run_wp() {
  docker compose exec -T wordpress wp "$@" --allow-root
}

ensure_wp_cli

if ! run_wp core is-installed 2>/dev/null; then
  echo "→ Установка WordPress..."
  run_wp core install \
    --url="$WP_URL" \
    --title="$WP_SITE" \
    --admin_user="$WP_USER" \
    --admin_password="$WP_PASS" \
    --admin_email="$WP_MAIL" \
    --skip-email
else
  echo "→ WordPress уже установлен, пропуск core install."
fi

echo "→ Активация плагина лендинга..."
run_wp plugin activate festival-pr3-landing

echo ""
echo "Готово."
echo "  Сайт:    $WP_URL"
echo "  Админка: $WP_URL/wp-admin"
echo "  Логин:   $WP_USER"
echo "  Пароль:  $WP_PASS"
echo ""
echo "Лендинг: главная страница «Фестиваль цифрового искусства»."
echo "Редактирование: Страницы → открыть страницу в редакторе блоков."
