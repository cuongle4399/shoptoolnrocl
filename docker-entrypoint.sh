#!/usr/bin/env bash
set -euo pipefail

# Render sets $PORT; default to 8080 locally
PORT_NUMBER="${PORT:-8080}"

# Update Apache to listen on $PORT
sed -ri "s/^Listen .*/Listen ${PORT_NUMBER}/" /etc/apache2/ports.conf

# Update default vhost to use $PORT instead of 80
if grep -q "<VirtualHost \*:80>" /etc/apache2/sites-available/000-default.conf; then
  sed -ri "s#<VirtualHost \*:80>#<VirtualHost *:${PORT_NUMBER}>#" /etc/apache2/sites-available/000-default.conf
fi

# Respect APACHE_DOCUMENT_ROOT if provided (kept default to /var/www/html)
if [ -n "${APACHE_DOCUMENT_ROOT:-}" ] && [ -d "$APACHE_DOCUMENT_ROOT" ]; then
  sed -ri "s#DocumentRoot /var/www/html#DocumentRoot ${APACHE_DOCUMENT_ROOT}#" /etc/apache2/sites-available/000-default.conf || true
  sed -ri "s#<Directory /var/www/>#<Directory ${APACHE_DOCUMENT_ROOT}/>#" /etc/apache2/apache2.conf || true
fi

exec "$@"
