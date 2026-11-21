#!/usr/bin/env bash
# Подготовка контейнера Render: порт, постоянный диск, пароль админки, index.html.
set -euo pipefail

WEB=/var/www/html
PORT="${PORT:-10000}"
DATA="$WEB/data"
UPLOADS="$WEB/uploads"
STORAGE="$WEB/storage"

# 1. Apache должен слушать порт, который выдаёт Render (по умолчанию 10000).
if [ -f /etc/apache2/ports.conf ]; then
    sed -ri "s/^Listen[[:space:]].*/Listen ${PORT}/" /etc/apache2/ports.conf
    if ! grep -q "^Listen ${PORT}$" /etc/apache2/ports.conf; then
        echo "Listen ${PORT}" >> /etc/apache2/ports.conf
    fi
fi
VHOST=/etc/apache2/sites-available/000-default.conf
if [ -f "$VHOST" ]; then
    sed -ri "s/<VirtualHost[^>]*>/<VirtualHost *:${PORT}>/" "$VHOST"
fi

# 2. Если подключён постоянный диск, переносим на него data/ и uploads/.
#    Без диска сайт работает, но правки сбрасываются при перезапуске.
if [ -d "$STORAGE" ] && [ -w "$STORAGE" ]; then
    mkdir -p "$STORAGE/data" "$STORAGE/uploads" "$STORAGE/backups"
    if [ ! -f "$STORAGE/.seeded" ]; then
        cp -a "$DATA/." "$STORAGE/data/" 2>/dev/null || true
        cp -a "$UPLOADS/." "$STORAGE/uploads/" 2>/dev/null || true
        : > "$STORAGE/.seeded"
    fi
    rm -rf "$DATA" "$UPLOADS"
    ln -s "$STORAGE/data" "$DATA"
    ln -s "$STORAGE/uploads" "$UPLOADS"
fi

# 3. Пароль админки приходит переменной окружения; в образе открытого текста нет.
if [ ! -f "$DATA/config.php" ]; then
    if [ -z "${BM_ADMIN_PASSWORD:-}" ]; then
        echo "ERROR: set BM_ADMIN_PASSWORD (min 12 characters) in Render dashboard" >&2
        exit 1
    fi
    BM_ADMIN_PASSWORD="$BM_ADMIN_PASSWORD" php /usr/local/bin/make_config.php "$DATA/config.php"
fi
# Маркер запрещает повторную установку пароля через setup.php.
: > "$DATA/setup.lock"

# 4. Пересобираем index.html из сохранённого контента (после рестарта с диском).
php /usr/local/bin/render_index.php || true

# 5. Права: PHP должен писать в data/ и uploads/, но не в остальные папки.
chown -R www-data:www-data "$WEB" 2>/dev/null || true
chmod 0750 "$DATA" "$DATA/backups" 2>/dev/null || true
chmod 0640 "$DATA/config.php" 2>/dev/null || true
chmod 0775 "$UPLOADS" 2>/dev/null || true

exec apache2-foreground
