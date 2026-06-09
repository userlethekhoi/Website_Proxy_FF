"""
dns_redirect.py — DNS + HTTP server redirect game CDN -> local modded files
===========================================================================
When game requests cdn.game.com/assetfile, we respond with local modded version.
No SSL pinning issue because we serve over HTTP.

iPhone MUST set DNS to this PC's IP first!
  Settings -> Wi-Fi -> (i) -> DNS -> Manual -> 192.168.1.252

Usage:
    python dns_redirect.py
"""

import socket
import struct
import threading
import http.server
import os
import time

# Config
LOCAL_IP = "192.168.1.252"
DNS_PORT = 53
HTTP_PORT = 80
ASSETS_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "mod_assets")

# Map: game CDN domain -> local asset files
# When game asks for cdn.xxx.com/assetindexer -> serve assetindexer.mod
ASSET_MAP = {
    "assetindexer": os.path.join(ASSETS_DIR, "assetindexer.mod"),
    "fileinfo": os.path.join(ASSETS_DIR, "fileinfo.txt"),
}

# Domains to redirect (game CDN domains)
# Add real domains discovered from capture
REDIRECT_DOMAINS = [
    "cdn.freefire.com",
    "ff.garena.com",
    "dl.garena.com",
    "res.freefire.com",
]


class DNSHandler:
    """Simple DNS server that redirects game CDN domains to LOCAL_IP."""
    
    def handle(self, data, addr, sock):
        try:
            # Parse DNS query
            transaction_id = data[:2]
            flags = struct.pack(">H", 0x8180)  # Standard response
            questions = data[4:6]
            answer_rrs = b"\x00\x01"  # 1 answer
            authority_rrs = b"\x00\x00"
            additional_rrs = b"\x00\x00"
            
            # Extract domain name from query
            domain = []
            pos = 12
            while data[pos] != 0:
                length = data[pos]
                domain.append(data[pos+1:pos+1+length].decode())
                pos += length + 1
            pos += 1
            domain_name = ".".join(domain).lower()
            
            # Check if this is a game CDN domain
            is_game = any(d in domain_name for d in REDIRECT_DOMAINS)
            
            if is_game:
                # Redirect to our local server
                response_ip = socket.inet_aton(LOCAL_IP)
                response = transaction_id + flags + questions + answer_rrs + authority_rrs + additional_rrs
                response += data[12:pos] + b"\x00\x01\x00\x01"  # Type A, Class IN
                response += b"\x00\x00\x00\x3c"  # TTL 60s
                response += b"\x00\x04" + response_ip  # IP
                print(f"  [DNS] {domain_name} -> {LOCAL_IP}")
            else:
                # Forward to real DNS (Google)
                response = self._forward_dns(data)
                if response:
                    response = response[:2] + flags + response[4:]
                else:
                    return
            
            sock.sendto(response, addr)
        except Exception as e:
            pass  # Ignore malformed packets
    
    def _forward_dns(self, data):
        try:
            fwd = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
            fwd.settimeout(3)
            fwd.sendto(data, ("8.8.8.8", 53))
            resp = fwd.recv(1024)
            fwd.close()
            return resp
        except:
            return None


class AssetHandler(http.server.SimpleHTTPRequestHandler):
    """Serve modded asset files."""
    
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=ASSETS_DIR, **kwargs)
    
    def log_message(self, format, *args):
        print(f"  [HTTP] {args[0]}")


def start_dns():
    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    sock.bind(("0.0.0.0", DNS_PORT))
    handler = DNSHandler()
    print(f"[DNS] Listening on port {DNS_PORT}")
    while True:
        try:
            data, addr = sock.recvfrom(512)
            threading.Thread(target=handler.handle, args=(data, addr, sock), daemon=True).start()
        except:
            pass


def start_http():
    server = http.server.HTTPServer(("0.0.0.0", HTTP_PORT), AssetHandler)
    print(f"[HTTP] Serving {ASSETS_DIR} on port {HTTP_PORT}")
    server.serve_forever()


if __name__ == "__main__":
    print("=" * 55)
    print("  ProxyFF - DNS Redirect + Asset Server")
    print("=" * 55)
    print(f"  DNS:  redirect game CDN -> {LOCAL_IP}")
    print(f"  HTTP: serving modded assets on port {HTTP_PORT}")
    print()
    print("  iPhone: Wi-Fi -> DNS -> Manual -> " + LOCAL_IP)
    print("  Then open Free Fire!")
    print("=" * 55)
    
    threading.Thread(target=start_dns, daemon=True).start()
    time.sleep(0.5)
    
    try:
        start_http()
    except KeyboardInterrupt:
        print("\nStopped.")
