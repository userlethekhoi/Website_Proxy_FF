"""
proxy_modifier.py — Production MITM Addon for Free Fire
========================================================
TWO attack modes:
  1. ASSET REPLACEMENT — intercept asset downloads, return modded Unity bundles
  2. HTTP MODIFICATION — modify JSON request payloads (hitbox, recoil, spread)

Mode 1 is the primary method (from proxyvip.py source).
Mode 2 is fallback for API-based games.

Setup:
    pip install mitmproxy mysql-connector-python
    mitmdump -s proxy_modifier.py --listen-port 8080
"""

import json
import os
import time
import threading
from mitmproxy import http, ctx

try:
    import mysql.connector
    from mysql.connector import Error
    HAS_MYSQL = True
except ImportError:
    HAS_MYSQL = False

# ============================================================
# Paths
# ============================================================
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
ASSETS_DIR = os.path.join(SCRIPT_DIR, "mod_assets")

# ============================================================
# Asset Replacement Map
# ============================================================
# Maps URL patterns → local modded asset files
# When game requests these files, proxy returns modded version instead.
# Add more entries based on traffic analysis.
ASSET_REPLACEMENTS = {
    # Thay the file danh sach de game nap mod file tu proxy
    "assetindexer": os.path.join(ASSETS_DIR, "assetindexer.mod"),
    "fileinfo": os.path.join(ASSETS_DIR, "fileinfo_mod.txt"),
    "gs_overwrite_weapon_config": os.path.join(ASSETS_DIR, "weapon_config.mod.gz"),
}

# ============================================================
# Database Config
# ============================================================
DB_CONFIG = {
    "host": "127.0.0.1",
    "user": "ioscruxcom",
    "password": "ioscruxcom",
    "database": "ioscruxcom",
    "port": 3306
}

# ============================================================
# Aimbot Mode Configuration (for JSON/API modification)
# ============================================================
AIMBOT_MODES = {
    "NORMAL":   None,
    "AIM_HEAD": {"hit_zone": "HEAD", "hitbox_id": 1},
    "AIM_NECK": {"hit_zone": "NECK", "hitbox_id": 2},
    "AIM_BODY": {"hit_zone": "BODY", "hitbox_id": 3},
    "AIM_LOCK": {"hit_zone": "HEAD", "hitbox_id": 1, "no_recoil": True, "no_spread": True, "lock_target": True},
    "AIM_DRAG": {"hit_zone": "BODY", "hitbox_id": 3, "aim_assist": True, "smooth_factor": 0.6, "drag_enabled": True},
    "FLAG_A":   {"hit_zone": "HEAD", "hitbox_id": 1},
    "FLAG_B":   {"hit_zone": "NECK", "hitbox_id": 2},
}

GAME_API_PATTERNS = [
    "api.game.com", "firebase", "gameloop", "battle",
    "shoot", "hit", "aim", "recoil", "spread",
]

# Ports map to aimbot policies
PORT_POLICY_MAP = {
    8082: "NORMAL",
    8083: "AIM_HEAD",
    8084: "AIM_NECK",
    8085: "AIM_BODY",
    8086: "AIM_LOCK",
    8087: "AIM_DRAG"
}

# ============================================================
# Policy Cache (Active authorized client IPs)
# ============================================================
_active_clients = set()
_cache_lock = threading.Lock()
_last_sync = 0
SYNC_INTERVAL = 3


def _get_db_connection():
    if not HAS_MYSQL:
        return None
    try:
        return mysql.connector.connect(**DB_CONFIG)
    except Error:
        return None


def _load_active_clients():
    global _active_clients, _last_sync
    conn = _get_db_connection()
    if not conn:
        return
    try:
        cursor = conn.cursor(dictionary=True)
        # Select active users with valid routing filters (registered devices)
        cursor.execute("SELECT client_ip FROM device_filters WHERE is_active = 1")
        rows = cursor.fetchall()
        new_clients = set(row['client_ip'] for row in rows)
        cursor.close()
        with _cache_lock:
            _active_clients = new_clients
            _last_sync = time.time()
        ctx.log.info(f"[Aimbot] Synced {len(new_clients)} active clients from DB")
    except Error as e:
        ctx.log.error(f"[Aimbot] DB error: {e}")
    finally:
        conn.close()


def _is_client_authorized(client_ip: str) -> bool:
    global _last_sync
    if time.time() - _last_sync > SYNC_INTERVAL:
        _load_active_clients()
    with _cache_lock:
        return client_ip in _active_clients


_client_current_modes = {}
_modes_lock = threading.Lock()


def _update_client_policy_db(client_ip: str, mode: str):
    conn = _get_db_connection()
    if not conn:
        return
    try:
        cursor = conn.cursor()
        cursor.execute("UPDATE device_filters SET routing_target = %s, updated_at = NOW() WHERE client_ip = %s", (mode, client_ip))
        conn.commit()
        cursor.close()
    except Exception:
        pass
    finally:
        conn.close()


def _update_client_policy_async(client_ip: str, mode: str):
    global _client_current_modes
    with _modes_lock:
        last_mode = _client_current_modes.get(client_ip)
        if last_mode == mode:
            return
        _client_current_modes[client_ip] = mode
    threading.Thread(target=_update_client_policy_db, args=(client_ip, mode), daemon=True).start()


# ============================================================
# ASSET REPLACEMENT — Primary Attack Mode
# ============================================================
def request(flow: http.HTTPFlow) -> None:
    """Intercept requests. Resolve mode from port, check authorization."""
    client_ip = flow.client_conn.peername[0]
    
    # 1. Verify if client is authorized (active device license filter)
    if not _is_client_authorized(client_ip):
        return # Unlicensed or inactive device -> normal bypass

    # 2. Get policy mode based on listening port
    listen_port = flow.server_conn.address[1] if (flow.server_conn and flow.server_conn.address) else ctx.options.listen_port
    if not listen_port:
        listen_port = ctx.options.listen_port
    mode = PORT_POLICY_MAP.get(listen_port, "NORMAL")

    # Update active policy target in DB for monitoring status
    _update_client_policy_async(client_ip, mode)

    if mode == "NORMAL":
        return

    url = flow.request.pretty_url
    # --- MODE 1: Asset Replacement ---
    for pattern, local_path in ASSET_REPLACEMENTS.items():
        if pattern in url and os.path.exists(local_path):
            try:
                with open(local_path, "rb") as f:
                    content = f.read()

                content_type = "application/octet-stream"
                if local_path.endswith(".txt"):
                    content_type = "text/plain"

                flow.response = http.Response.make(
                    200, content, {"Content-Type": content_type}
                )
                ctx.log.info(
                    f"[ASSET] {client_ip} ({mode}) | "
                    f"Replaced '{pattern}' with {os.path.basename(local_path)} "
                    f"({len(content):,} bytes)"
                )
                return  # Asset replaced, done!
            except Exception as e:
                ctx.log.error(f"[ASSET] Error replacing {pattern}: {e}")

    # --- MODE 2: JSON Request Modification (fallback) ---
    host = flow.request.pretty_host
    if not any(p in host.lower() for p in GAME_API_PATTERNS):
        return

    config = AIMBOT_MODES.get(mode)
    if config is None:
        return

    try:
        content_type = flow.request.headers.get("Content-Type", "")
        if "application/json" in content_type and flow.request.content:
            data = json.loads(flow.request.content.decode("utf-8", errors="ignore"))
            modified = _modify_json(data, config)
            new_raw = json.dumps(modified, separators=(',', ':')).encode("utf-8")
            if new_raw != flow.request.content:
                flow.request.content = new_raw
                ctx.log.info(f"[JSON] {client_ip} -> {mode} | {host}")
    except Exception as e:
        ctx.log.error(f"[JSON] Error: {e}")


def _modify_json(data: dict, config: dict) -> dict:
    """Apply aimbot modifications to JSON payload."""
    if "hit_zone" in data or "hitbox_id" in data:
        data["hit_zone"] = config["hit_zone"]
        data["hitbox_id"] = config["hitbox_id"]
    if config.get("no_recoil"):
        data["recoil_x"] = 0
        data["recoil_y"] = 0
        data["recoil_multiplier"] = 0
    if config.get("no_spread"):
        data["spread"] = 0
        data["spread_angle"] = 0
        data["bullet_spread"] = 0
    if config.get("lock_target"):
        data["aim_assist_strength"] = 1.0
        data["magnetic_aim"] = True
    if config.get("aim_assist"):
        data["aim_assist_enabled"] = True
        data["aim_assist_strength"] = 1.0
    if config.get("smooth_factor"):
        data["aim_smooth"] = config["smooth_factor"]
    if config.get("drag_enabled"):
        data["drag_aim"] = True
    return data


# ============================================================
# Lifecycle
# ============================================================
def running():
    ctx.log.info("=" * 60)
    ctx.log.info("[Aimbot] MITM Proxy STARTED")
    ctx.log.info(f"[Aimbot] DB: {DB_CONFIG['host']}:{DB_CONFIG['port']}/{DB_CONFIG['database']}")
    ctx.log.info(f"[Aimbot] Assets dir: {ASSETS_DIR}")
    for pattern, path in ASSET_REPLACEMENTS.items():
        status = "[OK]" if os.path.exists(path) else "[MISSING]"
        ctx.log.info(f"[Aimbot]   {status} {pattern} -> {os.path.basename(path)}")
    ctx.log.info("=" * 60)
    _load_active_clients()


def done():
    ctx.log.info("[Aimbot] MITM Proxy STOPPED")