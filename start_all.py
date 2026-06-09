"""
start_all.py — One-click Launch: Web + MITM Proxy + Ngrok Tunnel
==================================================================
Starts everything locally and exposes via Ngrok so iPhone can connect
from anywhere (not just same WiFi).

What it does:
  1. Starts PHP dev server on port 8000 (web dashboard)
  2. Starts mitmproxy on port 8080 (HTTPS interception)
  3. Starts Ngrok tunnels → public URLs
  4. Updates MySQL with public proxy address (for .mobileconfig)
  5. Shows iPhone setup QR/instructions

Usage:
    python start_all.py

Prerequisites:
    pip install mitmproxy mysql-connector-python
    - PHP installed (php in PATH)
    - MySQL running on localhost:3306
    - Ngrok account (free) + authtoken configured
        ngrok config add-authtoken <YOUR_TOKEN>
"""

import subprocess
import sys
import os
import time
import json
import socket
import threading
import urllib.request

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))

# Ports
PHP_PORT = 8000
MITM_PORT = 8080
UDP_PORT = 9999

# Colors for terminal
GREEN = "\033[92m"
CYAN = "\033[96m"
YELLOW = "\033[93m"
RED = "\033[91m"
RESET = "\033[0m"
BOLD = "\033[1m"


def check_prerequisites():
    """Verify all tools are available."""
    issues = []

    # PHP
    try:
        subprocess.run(["php", "-v"], capture_output=True, check=True)
        print(f"{GREEN}[OK]{RESET} PHP found")
    except:
        issues.append("PHP not found. Install from https://windows.php.net/download")

    # mitmproxy
    try:
        import mitmproxy
        print(f"{GREEN}[OK]{RESET} mitmproxy {mitmproxy.__version__}")
    except ImportError:
        issues.append("mitmproxy not found. Run: pip install mitmproxy")

    # MySQL connector
    try:
        import mysql.connector
        print(f"{GREEN}[OK]{RESET} mysql-connector-python")
    except ImportError:
        issues.append("mysql-connector not found. Run: pip install mysql-connector-python")

    # MySQL server
    try:
        import mysql.connector
        conn = mysql.connector.connect(host="127.0.0.1", user="ioscruxcom", password="ioscruxcom", port=3306)
        conn.close()
        print(f"{GREEN}[OK]{RESET} MySQL server running")
    except:
        issues.append("MySQL not running on localhost:3306 or user 'ioscruxcom' cannot connect.")

    # Ngrok
    try:
        r = subprocess.run(["ngrok", "version"], capture_output=True, text=True)
        print(f"{GREEN}[OK]{RESET} ngrok found")
    except:
        issues.append("ngrok not found. Download from https://ngrok.com/download")

    if issues:
        print(f"\n{RED}⚠ Missing prerequisites:{RESET}")
        for i in issues:
            print(f"  - {i}")
        return False
    return True


def get_local_ip():
    """Get local network IP."""
    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        s.connect(("8.8.8.8", 80))
        ip = s.getsockname()[0]
        s.close()
        return ip
    except:
        return "127.0.0.1"


def start_php_server():
    """Start PHP built-in server for web dashboard."""
    print(f"\n{CYAN}[PHP]{RESET} Starting web server on port {PHP_PORT}...")
    os.chdir(SCRIPT_DIR)
    proc = subprocess.Popen(
        ["php", "-S", f"0.0.0.0:{PHP_PORT}"],
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
        cwd=SCRIPT_DIR,
    )
    time.sleep(1)
    # Verify it started
    if proc.poll() is not None:
        print(f"{RED}[PHP] Failed to start!{RESET}")
        return None
    print(f"{GREEN}[PHP]{RESET} Web server running: http://localhost:{PHP_PORT}")
    return proc


def start_mitm_proxy():
    """Start mitmproxy in background."""
    print(f"\n{CYAN}[MITM]{RESET} Starting HTTPS proxy on port {MITM_PORT}...")
    addon_path = os.path.join(SCRIPT_DIR, "proxy_modifier.py")

    proc = subprocess.Popen(
        [
            sys.executable, "-m", "mitmproxy.tools.main", "mitmdump",
            "--listen-port", str(MITM_PORT),
            "--listen-host", "0.0.0.0",
            "-s", addon_path,
            "--ssl-insecure",
            "--set", "block_global=false",
            "--ignore-hosts", r".*",
        ],
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
        cwd=SCRIPT_DIR,
    )
    time.sleep(2)
    if proc.poll() is not None:
        print(f"{RED}[MITM] Failed to start!{RESET}")
        return None
    print(f"{GREEN}[MITM]{RESET} Proxy running: http://localhost:{MITM_PORT}")
    return proc


def start_ngrok():
    """Start ngrok tunnels and get public URLs."""
    print(f"\n{CYAN}[Ngrok]{RESET} Creating tunnels...")

    # Start ngrok with multiple tunnels via config file
    ngrok_config = os.path.join(SCRIPT_DIR, "ngrok.yml")
    config_content = f"""
version: "2"
authtoken: {os.environ.get('NGROK_AUTHTOKEN', 'YOUR_TOKEN_HERE')}
tunnels:
  web:
    proto: http
    addr: {PHP_PORT}
  proxy:
    proto: tcp
    addr: {MITM_PORT}
"""
    # Don't overwrite if exists with auth token
    if not os.path.exists(ngrok_config):
        with open(ngrok_config, "w") as f:
            f.write(config_content)

    proc = subprocess.Popen(
        ["ngrok", "start", "--all", "--config", ngrok_config, "--log", "stdout"],
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True,
    )

    # Wait and parse ngrok output for tunnel URLs
    web_url = None
    proxy_addr = None
    start_time = time.time()

    print(f"{YELLOW}[Ngrok]{RESET} Waiting for tunnels (may take 10-15s)...")

    while time.time() - start_time < 30:
        line = proc.stdout.readline()
        if not line:
            time.sleep(0.5)
            continue

        # Parse ngrok console output
        if "url=" in line:
            # Format: url=https://xxxx.ngrok-free.app
            url = line.split("url=")[-1].strip()
            if url.startswith("https://") and not web_url:
                web_url = url
                print(f"{GREEN}[Ngrok]{RESET} Web:    {BOLD}{web_url}{RESET}")
        elif "tcp://" in line:
            # Look for tcp tunnel address
            if "addr=" in line:
                addr_part = line.split("addr=")[-1].strip()
                if ":" in addr_part and not proxy_addr:
                    proxy_addr = addr_part
                    print(f"{GREEN}[Ngrok]{RESET} Proxy:  {BOLD}{proxy_addr}{RESET}")

        # Also try the ngrok API
        if web_url is None or proxy_addr is None:
            try:
                api_resp = urllib.request.urlopen("http://127.0.0.1:4040/api/tunnels", timeout=2)
                tunnels = json.loads(api_resp.read())["tunnels"]
                for t in tunnels:
                    if t["proto"] == "https" and not web_url:
                        web_url = t["public_url"]
                    if t["proto"] == "tcp" and not proxy_addr:
                        proxy_addr = t["public_url"].replace("tcp://", "")
                if web_url and proxy_addr:
                    break
            except:
                pass

    if not web_url or not proxy_addr:
        print(f"{RED}[Ngrok] Failed to get URLs! Check ngrok dashboard at http://127.0.0.1:4040{RESET}")
        return None, None, proc

    return web_url, proxy_addr, proc


def update_settings(web_url, proxy_host, proxy_port):
    """Update MySQL system_settings with public URLs."""
    try:
        import mysql.connector
        conn = mysql.connector.connect(
            host="127.0.0.1", user="ioscruxcom", password="ioscruxcom", database="ioscruxcom", port=3306
        )
        cursor = conn.cursor()
        cursor.execute(
            "INSERT INTO system_settings (setting_key, setting_value, setting_label) "
            "VALUES (%s, %s, %s) "
            "ON DUPLICATE KEY UPDATE setting_value = %s",
            ("proxy_host", proxy_host, "Proxy Host (Ngrok)", proxy_host),
        )
        cursor.execute(
            "INSERT INTO system_settings (setting_key, setting_value, setting_label) "
            "VALUES (%s, %s, %s) "
            "ON DUPLICATE KEY UPDATE setting_value = %s",
            ("proxy_ssl_port", str(proxy_port), "Proxy SSL Port", str(proxy_port)),
        )
        conn.commit()
        cursor.close()
        conn.close()
        print(f"{GREEN}[DB]{RESET} Updated proxy host: {proxy_host}:{proxy_port}")
    except Exception as e:
        print(f"{YELLOW}[DB]{RESET} Could not update settings: {e}")


def main():
    print("=" * 60)
    print(f"  {BOLD}ProxyFF — One-Click Launch (Ngrok){RESET}")
    print("=" * 60)
    print()

    if not check_prerequisites():
        print(f"\n{RED}Fix issues above and retry.{RESET}")
        sys.exit(1)

    local_ip = get_local_ip()
    print(f"\n  Local IP: {GREEN}{local_ip}{RESET}")

    # Start PHP
    php_proc = start_php_server()
    if not php_proc:
        sys.exit(1)

    # Start MITM proxy
    mitm_proc = start_mitm_proxy()
    if not mitm_proc:
        php_proc.terminate()
        sys.exit(1)

    # Start Ngrok
    web_url, proxy_addr, ngrok_proc = start_ngrok()
    if not web_url or not proxy_addr:
        php_proc.terminate()
        mitm_proc.terminate()
        sys.exit(1)

    proxy_host, proxy_port = proxy_addr.split(":") if ":" in proxy_addr else (proxy_addr, "8080")

    # Update MySQL
    update_settings(web_url, proxy_host, proxy_port)

    # Show iPhone setup
    print()
    print("=" * 60)
    print(f"  {BOLD}🎉 ALL SYSTEMS READY!{RESET}")
    print("=" * 60)
    print()
    print(f"  {CYAN}📱 iPhone Setup Steps:{RESET}")
    print(f"  ─────────────────────────────────────")
    print(f"  1. Open Safari → {BOLD}{web_url}{RESET}")
    print(f"  2. Đăng nhập / Đăng ký")
    print(f"  3. Dashboard → Kích hoạt license")
    print(f"  4. Tải .mobileconfig → Cài đặt")
    print()
    print(f"  Hoặc cấu hình thủ công:")
    print(f"  5. Settings → Wi-Fi → Proxy → Manual")
    print(f"     Server: {BOLD}{proxy_host}{RESET}")
    print(f"     Port:   {BOLD}{proxy_port}{RESET}")
    print(f"  6. Safari → http://mitm.it → Cài cert")
    print(f"  7. Settings → General → About → Trust Cert")
    print()
    print(f"  {CYAN}🌐 URLs:{RESET}")
    print(f"  Website:    {BOLD}{web_url}{RESET}")
    print(f"  Proxy:      {BOLD}{proxy_host}:{proxy_port}{RESET}")
    print(f"  Dashboard:  {BOLD}{web_url}/dashboard.php{RESET}")
    print(f"  Admin:      {BOLD}{web_url}/admin.php{RESET}")
    print()
    print(f"  {YELLOW}⚠ Press Ctrl+C to stop all services{RESET}")
    print("=" * 60)

    try:
        # Keep running
        while True:
            time.sleep(1)
            # Check if processes are still alive
            if php_proc.poll() is not None:
                print(f"{RED}[PHP] Server crashed!{RESET}")
                break
            if mitm_proc.poll() is not None:
                print(f"{RED}[MITM] Proxy crashed!{RESET}")
                break
            if ngrok_proc.poll() is not None:
                print(f"{RED}[Ngrok] Tunnel closed!{RESET}")
                break
    except KeyboardInterrupt:
        print(f"\n{YELLOW}[*] Shutting down...{RESET}")
    finally:
        for p in [php_proc, mitm_proc, ngrok_proc]:
            try:
                p.terminate()
                p.wait(timeout=5)
            except:
                try:
                    p.kill()
                except:
                    pass
        print(f"{GREEN}[*] All services stopped.{RESET}")


if __name__ == "__main__":
    main()
