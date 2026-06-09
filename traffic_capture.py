"""
traffic_capture.py — Capture ALL Free Fire Traffic for Analysis
================================================================
Runs mitmproxy in passive capture mode. Saves every request/response
pair to capture/ directory, organized by domain.

This helps you discover:
  - Which API endpoints the game calls
  - What data format (JSON/Protobuf/Binary) is used
  - What field names control hitbox, aim, recoil, spread

Step 1: CAPTURE
    python traffic_capture.py

Step 2: ANALYZE
    python traffic_analyze.py

Step 3: UPDATE
    Copy real endpoints/fields into proxy_modifier.py GAME_API_PATTERNS
"""

import json
import os
import sys
import time
import subprocess
from datetime import datetime

CAPTURE_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "capture")

ADDON_CODE = '''
import json, os, time
from mitmproxy import http, ctx

CAPTURE_DIR = r"{capture_dir}"
os.makedirs(CAPTURE_DIR, exist_ok=True)

# Track unique domains seen
seen_domains = set()

def request(flow):
    client_ip = flow.client_conn.peername[0]
    host = flow.request.pretty_host
    path = flow.request.path
    method = flow.request.method
    content_type = flow.request.headers.get("Content-Type", "")

    # Track domain
    if host not in seen_domains:
        seen_domains.add(host)
        ctx.log.info(f"[DISCOVER] New domain: {{{{host}}}}")

    # Save every request for analysis
    ts = int(time.time() * 1000)
    safe_host = host.replace("/", "_").replace(":", "_")
    safe_path = path.replace("/", "_").replace("?", "_")[:80]

    entry = {{
        "timestamp": ts,
        "client_ip": client_ip,
        "host": host,
        "path": path,
        "method": method,
        "content_type": content_type,
        "request_headers": dict(flow.request.headers),
        "request_size": len(flow.request.content) if flow.request.content else 0,
    }}

    # Save request body if it's JSON or small enough
    if flow.request.content:
        try:
            body = flow.request.content.decode("utf-8", errors="replace")
            if len(body) < 50000:  # Skip huge payloads
                entry["request_body"] = body
                # Try parse JSON for easy reading
                if "json" in content_type.lower():
                    try:
                        entry["request_json"] = json.loads(body)
                        # Highlight keys that might be game-related
                        game_keys = find_game_keys(entry["request_json"])
                        if game_keys:
                            entry["potential_game_keys"] = game_keys
                    except:
                        pass
        except:
            entry["request_body_hex"] = flow.request.content.hex()[:500]

    filename = f"{{safe_host}}_{{method}}_{{ts}}.json"
    filepath = os.path.join(CAPTURE_DIR, filename)
    with open(filepath, "w", encoding="utf-8") as f:
        json.dump(entry, f, indent=2, ensure_ascii=False, default=str)

def response(flow):
    # Append response data to the same file
    ts = int(time.time() * 1000)
    host = flow.request.pretty_host
    safe_host = host.replace("/", "_").replace(":", "_")

    # Find matching request file (approximate)
    for fname in os.listdir(CAPTURE_DIR):
        if fname.startswith(safe_host) and fname.endswith(".json"):
            fpath = os.path.join(CAPTURE_DIR, fname)
            try:
                with open(fpath, "r", encoding="utf-8") as f:
                    entry = json.load(f)
                # Only add response if not already there
                if "response_status" not in entry:
                    entry["response_status"] = flow.response.status_code
                    entry["response_content_type"] = flow.response.headers.get("Content-Type", "")
                    entry["response_size"] = len(flow.response.content) if flow.response.content else 0
                    if flow.response.content:
                        try:
                            body = flow.response.content.decode("utf-8", errors="replace")
                            if len(body) < 50000:
                                entry["response_body"] = body
                                if "json" in entry.get("response_content_type", "").lower():
                                    try:
                                        entry["response_json"] = json.loads(body)
                                    except:
                                        pass
                        except:
                            entry["response_body_hex"] = flow.response.content.hex()[:500]
                    with open(fpath, "w", encoding="utf-8") as f:
                        json.dump(entry, f, indent=2, ensure_ascii=False, default=str)
                    break
            except:
                continue

def find_game_keys(obj, prefix=""):
    """Find keys that look like game-related fields (hitbox, aim, shoot, etc)."""
    if not isinstance(obj, dict):
        return []
    game_keywords = [
        "hit", "aim", "shoot", "damage", "recoil", "spread", "bullet",
        "head", "body", "neck", "kill", "death", "match", "player",
        "position", "rotation", "weapon", "fire", "scope", "target",
        "hitbox", "hit_zone", "crosshair", "trigger"
    ]
    found = []
    for key, value in obj.items():
        key_lower = key.lower()
        if any(kw in key_lower for kw in game_keywords):
            full_key = f"{{prefix}}.{{key}}" if prefix else key
            found.append({{"key": full_key, "value_preview": str(value)[:100]}})
        if isinstance(value, dict):
            found.extend(find_game_keys(value, f"{{prefix}}.{{key}}" if prefix else key))
    return found

def done():
    domains_file = os.path.join(CAPTURE_DIR, "_discovered_domains.txt")
    with open(domains_file, "w") as f:
        for d in sorted(seen_domains):
            f.write(d + "\\n")
    ctx.log.info(f"[DONE] Captured {{len(os.listdir(CAPTURE_DIR))}} files")
    ctx.log.info(f"[DONE] {{len(seen_domains)}} domains saved to _discovered_domains.txt")
'''.format(capture_dir=CAPTURE_DIR.replace('\\', '\\\\'))


def main():
    # Write addon to file
    addon_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), "_capture_addon.py")
    with open(addon_path, "w", encoding="utf-8") as f:
        f.write(ADDON_CODE)

    os.makedirs(CAPTURE_DIR, exist_ok=True)

    # Find mitmdump.exe
    mitmdump_bin = None
    for p in [
        os.path.expandvars(r"%LOCALAPPDATA%\Programs\Python\Python314\Scripts\mitmdump.exe"),
        os.path.expandvars(r"%LOCALAPPDATA%\Programs\Python\Python313\Scripts\mitmdump.exe"),
        os.path.expandvars(r"%LOCALAPPDATA%\Programs\Python\Python312\Scripts\mitmdump.exe"),
        "mitmdump",
    ]:
        if os.path.exists(p) or p == "mitmdump":
            mitmdump_bin = p
            break

    if not mitmdump_bin:
        print("mitmdump.exe not found!")
        sys.exit(1)

    print("=" * 65)
    print("  ProxyFF — Traffic Capture Mode")
    print("=" * 65)
    print(f"  Captures saved to: {CAPTURE_DIR}")
    print()
    print("  📱 iPhone setup:")
    print("     1. Settings → Wi-Fi → Configure Proxy → Manual")
    print("     2. Server: <YOUR_PC_IP>  Port: 8888")
    print("     3. Open Safari → http://mitm.it → Install CA cert")
    print("     4. Settings → General → About → Trust Certificate")
    print()
    print("  🎮 Then:")
    print("     1. Open Free Fire on iPhone")
    print("     2. Play a match (shoot, aim, get kills)")
    print("     3. Press Ctrl+C here to stop capture")
    print("     4. Run: python traffic_analyze.py")
    print("=" * 65)
    print()

    cmd = [
        mitmdump_bin,
        "--listen-port", "8080",
        "--listen-host", "0.0.0.0",
        "-s", addon_path,
        "--ssl-insecure",
        "--allow-hosts", r"freefiremobile\.com",
    ]

    try:
        subprocess.run(cmd)
    except KeyboardInterrupt:
        print("\n[*] Capture stopped.")
        print(f"[*] Files saved to: {CAPTURE_DIR}")
        print(f"[*] Run: python traffic_analyze.py")


if __name__ == "__main__":
    main()
