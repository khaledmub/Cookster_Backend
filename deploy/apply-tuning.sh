#!/usr/bin/env bash
# Apply Cookster production tuning (4 vCPU / 15 GiB). Run with sudo.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
REPO="$(cd "$ROOT/.." && pwd)"

echo "==> PHP-FPM pool"
cp "$REPO/deploy/php/99-cookster-fpm.conf" /etc/php/8.2/fpm/pool.d/zz-cookster.conf
cp "$REPO/deploy/php/99-cookster-opcache.ini" /etc/php/8.2/fpm/conf.d/99-cookster-opcache.ini
cp "$REPO/deploy/php/99-cookster-opcache.ini" /etc/php/8.2/cli/conf.d/99-cookster-opcache.ini

echo "==> MariaDB"
cp "$REPO/deploy/mysql/99-cookster.cnf" /etc/mysql/mariadb.conf.d/99-cookster.cnf

echo "==> systemd queue workers"
cp "$REPO/deploy/systemd/cookster-transcode@.service" /etc/systemd/system/
cp "$REPO/deploy/systemd/cookster-queue@.service" /etc/systemd/system/
cp "$REPO/deploy/systemd/cookster-queue.target" /etc/systemd/system/
systemctl daemon-reload

echo "==> Enable 4 transcode + 2 general workers"
for i in 1 2 3 4; do
  systemctl enable --now "cookster-transcode@${i}.service" 2>/dev/null || true
done
systemctl enable cookster-queue.target
for i in 1 2; do
  systemctl enable --now "cookster-queue@${i}.service" 2>/dev/null || true
done

echo "==> Restart services"
systemctl restart php8.2-fpm
systemctl restart mariadb
for i in 1 2 3 4; do systemctl restart "cookster-transcode@${i}.service"; done
for i in 1 2; do systemctl restart "cookster-queue@${i}.service"; done
cd "$REPO" && sudo -u www-data php artisan config:clear && sudo -u www-data php artisan config:cache

echo "Done. Verify:"
echo "  systemctl status 'cookster-*'"
echo "  free -h && uptime"
echo "  mysql -e \"SHOW VARIABLES LIKE 'innodb_buffer_pool_size';\""
