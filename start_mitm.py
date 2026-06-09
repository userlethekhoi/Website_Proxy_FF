#!/usr/bin/env python3
"""
start_mitm.py — Launch MITM HTTPS Proxy with Aimbot Addon
==========================================================

This script starts mitmproxy with:
  - proxy_modifier.py (aimbot addon)
  - Transparent proxy mode for HTTPS interception
  - All traffic logged to mitm.log

Prerequisites:
    pip install mitmproxy mysql-connector-python

Usage:
    python start_mitm.py                 # Start on port 8080 (default)
    python start_mitm.py --port 9090     # Start on custom port
    python start_mitm.py --transparent   # Transparent mode (requires iptables)

iPhone Setup:
    1. Run this script on your server
    2. On iPhone: Settings → Wi-Fi → Configure Proxy → Manual
    3. Server: <your_server_ip>  Port: 8080
    4. Visit mitm.it on iPhone Safari to install CA certificate
    5. Trust certificate in Settings → General → About → Certificate Trust
"""

import subprocess
import sys
import os
import argparse


def check_mitmproxy():
    """Check if mitmproxy is installed."""
    try:
        import mitmproxy
        print(f"[OK] mitmproxy {mitmproxy.__version__} installed")
        return True
    except ImportError:
        print("[!] mitmproxy not installed!")
        print("    Run: pip install mitmproxy")
        return False


def check_mysql():
    """Check if mysql-connector is installed."""
    try:
        import mysql.connector
        print("[OK] mysql-connector-python installed")
        return True
    except ImportError:
        print("[!] mysql-connector-python not installed!")
        print("    Run: pip install mysql-connector-python")
        return False


def main():
    parser = argparse.ArgumentParser(description="Start MITM Proxy with Aimbot")
    parser.add_argument("--port", type=int, default=8080, help="Proxy listen port (default: 8080)")
    parser.add_argument("--host", default="0.0.0.0", help="Bind address (default: 0.0.0.0)")
    parser.add_argument("--transparent", action="store_true", help="Enable transparent proxy mode")
    parser.add_argument("--no-ssl-insecure", action="store_true", help="Don't use ssl_insecure (use for testing)")
    args = parser.parse_args()

    if not check_mitmproxy() or not check_mysql():
        sys.exit(1)

    # Build command
    script_dir = os.path.dirname(os.path.abspath(__file__))
    addon_path = os.path.join(script_dir, "proxy_modifier.py")

    if not os.path.exists(addon_path):
        print(f"[!] Addon not found: {addon_path}")
        sys.exit(1)

    cmd = [
        sys.executable, "-m", "mitmproxy.tools.main",
        "--mode", f"regular@{args.port}",
        "--listen-host", args.host,
        "-s", addon_path,
        "--set", "block_global=false",
        "--ssl-insecure",  # Accept all upstream SSL certs
        "--ignore-hosts", r".*",
    ]

    if args.transparent:
        cmd[2] = f"transparent@{args.port}"

    print("=" * 60)
    print("  ProxyFF — MITM Aimbot Proxy Launcher")
    print("=" * 60)
    print(f"  Listen:    {args.host}:{args.port}")
    print(f"  Mode:      {'Transparent' if args.transparent else 'Regular HTTP(S)'}")
    print(f"  Addon:     {addon_path}")
    print(f"  Log file:  mitm.log")
    print()
    print("  ⚠️  iPhone must install CA cert from http://mitm.it")
    print("  ⚠️  Then trust cert in Settings → General → About")
    print("=" * 60)
    print()

    # Redirect to log file
    log_file = os.path.join(script_dir, "mitm.log")
    with open(log_file, "a") as log:
        log.write(f"\n{'='*60}\n")
        log.write(f"Started: {__import__('datetime').datetime.now()}\n")
        log.write(f"{'='*60}\n")

    try:
        # Use mitmdump for headless mode
        cmd[1] = "mitmdump"
        subprocess.run(cmd)
    except KeyboardInterrupt:
        print("\n[*] Proxy stopped.")
    except Exception as e:
        print(f"[!] Error: {e}")
        sys.exit(1)


if __name__ == "__main__":
    main()
