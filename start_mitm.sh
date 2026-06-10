#!/bin/bash
# Script to launch multiple mitmdump proxies for different Free Fire aimbot modes

trap 'kill_all' SIGTERM SIGINT

kill_all() {
    echo "[*] Shutting down all mitmdump proxies..."
    pkill -P $$
    exit 0
}

# Ports mapping:
# 8082 -> NORMAL
# 8083 -> AIM_HEAD
# 8084 -> AIM_NECK
# 8085 -> AIM_BODY
# 8086 -> AIM_LOCK
# 8087 -> AIM_DRAG

PORTS=(8082 8083 8084 8085 8086 8087)

echo "[*] Launching mitmdump proxies..."
for PORT in "${PORTS[@]}"; do
    echo "  - Port $PORT starting..."
    /usr/local/bin/mitmdump --listen-port "$PORT" --listen-host 0.0.0.0 \
        -s /home/ioscruxcom/htdocs/www.ioscrux.com/proxy_modifier.py \
        --ssl-insecure --set block_global=false &
done

echo "[*] All proxies launched. Keeping monitor alive..."
# Wait for all background processes to finish (or sigterm)
wait
