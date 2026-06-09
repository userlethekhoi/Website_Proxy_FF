"""
_capture.py — Log ALL traffic, both HTTP and HTTPS
"""
import json, os, time
from mitmproxy import http, ctx

CAPTURE_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "capture")
os.makedirs(CAPTURE_DIR, exist_ok=True)

domains_file = os.path.join(CAPTURE_DIR, "_domains.txt")

def request(flow):
    host = flow.request.pretty_host
    url = flow.request.pretty_url
    is_https = "https://" in url.lower() or flow.request.port == 443 or flow.server_conn and flow.server_conn.tls_established
    
    # Log ALL domains to file
    with open(domains_file, "a", encoding="utf-8") as f:
        proto = "HTTPS" if is_https else "HTTP "
        f.write(f"{proto} | {host}{flow.request.path}\n")
    
    ctx.log.info(f"[{proto}] {host}{flow.request.path}")

