#!/usr/bin/env bash
# =============================================================================
# setup.sh  –  Bootstrap the Drupal + React CMS project
#
# Usage:
#   chmod +x scripts/setup.sh
#   ./scripts/setup.sh
#
# Environment variables (override defaults by exporting before running):
#   DB_NAME     – database name        (default: drupal_cms)
#   DB_USER     – database user        (default: drupal)
#   DB_PASS     – database password    (default: drupal)
#   DB_HOST     – database host        (default: 127.0.0.1)
#   SITE_NAME   – Drupal site name     (default: Drupal React CMS)
#   ADMIN_USER  – Drupal admin user    (default: admin)
#   ADMIN_PASS  – Drupal admin pass    (default: admin123)
# =============================================================================
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

DB_NAME="${DB_NAME:-drupal_cms}"
DB_USER="${DB_USER:-drupal}"
DB_PASS="${DB_PASS:-drupal}"
DB_HOST="${DB_HOST:-127.0.0.1}"
SITE_NAME="${SITE_NAME:-Drupal React CMS}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_PASS="${ADMIN_PASS:-admin123}"

DRUSH="$ROOT/vendor/bin/drush"
REACT_APP="$ROOT/web/themes/custom/react_theme/react-app"

echo "==> [1/5] Installing PHP dependencies via Composer..."
cd "$ROOT"
composer install --no-interaction --optimize-autoloader

echo "==> [2/5] Installing Drupal..."
"$DRUSH" site:install standard \
  --db-url="mysql://${DB_USER}:${DB_PASS}@${DB_HOST}/${DB_NAME}" \
  --site-name="${SITE_NAME}" \
  --account-name="${ADMIN_USER}" \
  --account-pass="${ADMIN_PASS}" \
  --yes

echo "==> [3/5] Enabling modules and theme..."
"$DRUSH" en datetime options content_api serialization basic_auth rest restui --yes
"$DRUSH" theme:enable react_theme --yes
"$DRUSH" config:set system.theme default react_theme --yes

echo "==> [4/5] Installing Node dependencies and building React app..."
cd "$REACT_APP"
npm install
npm run build

echo "==> [5/5] Importing config and clearing caches..."
cd "$ROOT"
"$DRUSH" config:import --yes 2>/dev/null || true   # skip if config/sync is empty
"$DRUSH" cache:rebuild

cat <<EOF

╔══════════════════════════════════════════════════════════╗
║            Setup complete! 🎉                           ║
║                                                          ║
║  Drupal admin: http://localhost/user/login               ║
║    Username : ${ADMIN_USER}                                     ║
║    Password : ${ADMIN_PASS}                                  ║
║                                                          ║
║  React dev server:                                       ║
║    cd web/themes/custom/react_theme/react-app            ║
║    npm run dev    →   http://localhost:5173               ║
╚══════════════════════════════════════════════════════════╝
EOF
