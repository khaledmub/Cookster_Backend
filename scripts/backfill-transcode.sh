#!/bin/bash
set -euo pipefail
cd /var/www/cookster_admin
LOG=/var/www/cookster_admin/storage/logs/backfill-transcode.log
echo "[$(date -Is)] starting backfill loop" | tee -a "$LOG"
while true; do
  OUT=$(nice -n 19 ionice -c3 sudo -u www-data php artisan videos:backfill-media --transcode --limit=50 2>&1)
  echo "[$(date -Is)] $OUT" | tee -a "$LOG"
  if echo "$OUT" | grep -q 'transcodes=0'; then
    echo "[$(date -Is)] nothing left to dispatch, exiting" | tee -a "$LOG"
    break
  fi
  sleep 30
done
