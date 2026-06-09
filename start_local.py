"""
start_local.py — Quick Local Test
==================================
Khởi động PHP server + MITM proxy trên local.
iPhone phải cùng WiFi với máy tính.

Usage:
    python start_local.py

iPhone setup:
    Proxy: <IP máy tính>:8080
    Web:   http://<IP máy tính>:8000
"""

import subprocess
import sys
import os
import time
import socket
import urllib.request

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
PHP_PORT = 8000
MITM_PORT = 8080

GREEN = "\033[92m"
CYAN = "\033[96m"
YELLOW = "\033[93m"
RED = "\033[91m"
RESET = "\033[0m"
BOLD = "\033[1m"


def get_ip():
    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        s.connect(("8.8.8.8", 80))
        ip = s.getsockname()[0]
        s.close()
        return ip
    except:
        return "127.0.0.1"


def main():
    ip = get_ip()

    print("=" * 55)
    print(f"  {BOLD}ProxyFF — LOCAL TEST{RESET}")
    print("=" * 55)
    print(f"  IP may tinh: {GREEN}{ip}{RESET}")
    print()

    # Check if Apache (XAMPP) is already running
    web_url = None
    php_proc = None

    try:
        r = urllib.request.urlopen("http://127.0.0.1/proxyff/index.php", timeout=3)
        if r.status == 200:
            web_url = f"http://{ip}/proxyff"
            print(f"{GREEN}[OK]{RESET} Apache XAMPP da chay san")
            print(f"      Web: {web_url}")
    except:
        pass

    # If Apache not running, try PHP built-in server
    if not web_url:
        php_bin = None
        import shutil
        for p in [r"C:\xampp\php\php.exe", r"C:\laragon\bin\php\php.exe", "php"]:
            if (p == "php" and shutil.which("php")) or os.path.exists(p):
                php_bin = p
                break

        if not php_bin:
            print(f"{RED}[!] Khong tim thay PHP! Cai XAMPP truoc.{RESET}")
            sys.exit(1)

        print(f"{CYAN}[1/2]{RESET} Khoi dong PHP server...")
        php_proc = subprocess.Popen(
            [php_bin, "-S", f"0.0.0.0:{PHP_PORT}", "-t", SCRIPT_DIR],
            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
            cwd=SCRIPT_DIR
        )
        time.sleep(1)
        web_url = f"http://{ip}:{PHP_PORT}"
        print(f"      {GREEN}OK{RESET} {web_url}")

    # Start MITM proxy - dùng mitmdump.exe trực tiếp (không dùng python -m)
    step = "2/2" if php_proc else "1/1"
    print(f"{CYAN}[{step}]{RESET} Khoi dong MITM Proxy...")
    addon = os.path.join(SCRIPT_DIR, "proxy_modifier.py")

    if not os.path.exists(addon):
        print(f"{RED}[!] Khong tim thay proxy_modifier.py{RESET}")
        if php_proc: php_proc.terminate()
        sys.exit(1)

    # Tìm mitmdump.exe
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
        print(f"{RED}[!] Khong tim thay mitmdump.exe{RESET}")
        if php_proc: php_proc.terminate()
        sys.exit(1)

    mitm = subprocess.Popen(
        [mitmdump_bin, "--listen-port", str(MITM_PORT), "--listen-host", "0.0.0.0",
         "-s", addon, "--ssl-insecure", "--set", "block_global=false", "--allow-hosts", r"freefiremobile\.com"],
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, cwd=SCRIPT_DIR
    )
    time.sleep(2)
    print(f"      {GREEN}OK{RESET} Proxy: {ip}:{MITM_PORT}")

    # Instructions
    print()
    print("=" * 55)
    print(f"  {BOLD}  Cau hinh iPhone:{RESET}")
    print("=" * 55)
    print(f"  1  Wi-Fi -> Proxy -> Manual")
    print(f"     Server: {BOLD}{ip}{RESET}")
    print(f"     Port:   {BOLD}{MITM_PORT}{RESET}")
    print()
    print(f"  2  Safari -> {BOLD}{web_url}{RESET}")
    print(f"     -> Dang ky / Dang nhap")
    print()
    print(f"  3  Safari -> {BOLD}http://mitm.it{RESET}")
    print(f"     -> Cai chung chi CA")
    print(f"     -> Settings -> General -> About -> Trust")
    print()
    print(f"  4  Dashboard -> Kich hoat license")
    print(f"     -> Chon aimbot mode -> Vao game test!")
    print()
    print(f"  {YELLOW}Ctrl+C de dung{RESET}")
    print("=" * 55)

    try:
        while True:
            time.sleep(1)
    except KeyboardInterrupt:
        print(f"\n{YELLOW}Da dung.{RESET}")
        if php_proc:
            php_proc.terminate()
        mitm.terminate()


if __name__ == "__main__":
    main()
