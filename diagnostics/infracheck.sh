#!/usr/bin/env bash
# Infra-side performance check for the Moodle K8s deployment.
# Read-only: inspects resource usage, OPcache/FPM status, slow queries, Redis.
# Requires kubectl access to the cluster. Adjust NS / selectors to your env.
#
#   ./diagnostics/infracheck.sh
#
set -uo pipefail
NS="${NS:-jo-moodle}"
APP_SEL="${APP_SEL:-app=moodle}"     # label selector for the Moodle pods
DB_SEL="${DB_SEL:-app=moodle-db}"
REDIS_SEL="${REDIS_SEL:-app=redis}"

line() { printf '\n===== %s =====\n' "$1"; }
pod()  { kubectl -n "$NS" get pod -l "$1" -o jsonpath='{.items[0].metadata.name}' 2>/dev/null; }

APP_POD=$(pod "$APP_SEL"); DB_POD=$(pod "$DB_SEL"); REDIS_POD=$(pod "$REDIS_SEL")
echo "namespace=$NS  app=$APP_POD  db=$DB_POD  redis=$REDIS_POD"

line "Pod resource usage (is anything CPU/mem pinned or being OOMKilled?)"
kubectl -n "$NS" top pods 2>/dev/null || echo "metrics-server not available"
echo "--- recent restarts / OOMKills ---"
kubectl -n "$NS" get pods -o wide
kubectl -n "$NS" get events --field-selector reason=OOMKilling 2>/dev/null | tail -20

line "PHP-FPM live status (queued requests = pool too small / app too slow)"
# Needs pm.status_path=/php_status reachable; served via the web container.
kubectl -n "$NS" exec "$APP_POD" -- sh -c \
  'curl -s "http://127.0.0.1/php_status?full" 2>/dev/null || curl -s "http://127.0.0.1:9000/php_status" 2>/dev/null' \
  | grep -E "active processes|idle processes|listen queue|max children reached|slow requests" \
  || echo "php_status not reachable — check pm.status_path / Apache proxy"

line "OPcache as seen by PHP-FPM (the figure that actually matters)"
# Drops a temp one-liner into the docroot, curls it through FPM, removes it.
kubectl -n "$NS" exec "$APP_POD" -- sh -c '
  f=/var/www/html/_opcache_$$.php
  printf "%s" "<?php \$s=opcache_get_status(false); printf(\"enabled=%s scripts=%d hit_rate=%.1f%% used=%dMB free=%dMB wasted=%.1f%% restarts(oom)=%d\n\", var_export(\$s[\"opcache_enabled\"],true), \$s[\"opcache_statistics\"][\"num_cached_scripts\"], \$s[\"opcache_statistics\"][\"opcache_hit_rate\"], \$s[\"memory_usage\"][\"used_memory\"]/1048576, \$s[\"memory_usage\"][\"free_memory\"]/1048576, \$s[\"memory_usage\"][\"current_wasted_percentage\"], \$s[\"opcache_statistics\"][\"oom_restarts\"]);" > "$f"
  curl -s "http://127.0.0.1/_opcache_$$.php" 2>/dev/null || curl -s "http://127.0.0.1:8080/_opcache_$$.php" 2>/dev/null
  rm -f "$f"
' || echo "could not query FPM opcache"

line "MySQL: is the buffer pool warm + what are the slow queries?"
kubectl -n "$NS" exec "$DB_POD" -- sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "
  SHOW GLOBAL STATUS LIKE \"Threads_running\";
  SHOW GLOBAL STATUS LIKE \"Innodb_buffer_pool_reads\";
  SHOW GLOBAL STATUS LIKE \"Innodb_buffer_pool_read_requests\";
  SHOW GLOBAL STATUS LIKE \"Slow_queries\";
  SHOW PROCESSLIST;" 2>/dev/null' || echo "set MYSQL_ROOT_PASSWORD in the DB pod env or pass creds"
echo "--- top slow queries (last entries) ---"
kubectl -n "$NS" exec "$DB_POD" -- sh -c 'tail -120 /var/log/mysql/slow.log 2>/dev/null' \
  || echo "slow.log not found at /var/log/mysql/slow.log"

line "Redis: is it being used + is it evicting?"
kubectl -n "$NS" exec "$REDIS_POD" -- redis-cli INFO stats 2>/dev/null \
  | grep -E "keyspace_hits|keyspace_misses|evicted_keys|expired_keys"
kubectl -n "$NS" exec "$REDIS_POD" -- redis-cli INFO memory 2>/dev/null \
  | grep -E "used_memory_human|maxmemory_human|mem_fragmentation_ratio"
kubectl -n "$NS" exec "$REDIS_POD" -- redis-cli DBSIZE 2>/dev/null

line "TTFB measured from INSIDE the cluster (isolates app time from network/CDN)"
kubectl -n "$NS" exec "$APP_POD" -- sh -c \
  'for i in 1 2 3; do curl -s -o /dev/null -w "ttfb=%{time_starttransfer}s total=%{time_total}s\n" http://127.0.0.1/login/index.php; done' \
  || echo "local curl failed"

echo
echo "Compare in-cluster TTFB vs what you see in a browser:"
echo "  * in-cluster fast, browser slow  -> network / TLS / CDN / ingress / NFS file serving"
echo "  * in-cluster ALSO slow           -> app or DB (run diagnostics/perfcheck.php next)"
