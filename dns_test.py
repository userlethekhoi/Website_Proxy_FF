"""
dns_test.py — Minimal DNS server to redirect CDN -> localhost
"""

import socket
import struct
import threading
import http.server
import os
import sys

LOCAL_IP = sys.argv[1] if len(sys.argv) > 1 else "192.168.1.252"
ASSETS_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "mod_assets")

# Domains to redirect
REDIRECT = ["dl-core.cdn.freefiremobile.com", "dl.cdn.freefiremobile.com"]


def handle_dns(data, addr, sock):
    try:
        domain = []
        pos = 12
        while data[pos] != 0:
            length = data[pos]
            domain.append(data[pos+1:pos+1+length].decode())
            pos += length + 1
        domain_name = ".".join(domain).lower()

        is_game = any(d in domain_name for d in REDIRECT)

        response = data[:2]  # transaction ID
        response += b"\x81\x80"  # flags: standard response, no error
        response += data[4:6]  # questions
        response += b"\x00\x01"  # 1 answer
        response += b"\x00\x00\x00\x00"  # 0 authority, 0 additional

        # Copy question section
        q_pos = 12
        while data[q_pos] != 0:
            q_pos += data[q_pos] + 1
        q_pos += 5  # skip null + QTYPE + QCLASS
        response += data[12:q_pos]

        # Answer: type A, class IN, TTL 300, IP = LOCAL_IP
        response += b"\xc0\x0c"  # pointer to domain name
        response += b"\x00\x01\x00\x01"  # type A, class IN
        response += b"\x00\x00\x00\x3c"  # TTL 60s
        response += b"\x00\x04"  # data length 4
        response += socket.inet_aton(LOCAL_IP)

        if is_game:
            print(f"  [DNS] {domain_name} -> {LOCAL_IP}")
            response_ip = socket.inet_aton(LOCAL_IP)
            answer = b"\xc0\x0c" + b"\x00\x01\x00\x01" + b"\x00\x00\x00\x3c" + b"\x00\x04" + response_ip
            sock.sendto(response + answer, addr)
        else:
            # Forward to real DNS
            try:
                fwd = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
                fwd.settimeout(3)
                fwd.sendto(data, ("8.8.8.8", 53))
                resp = fwd.recv(1024)
                fwd.close()
                sock.sendto(resp, addr)
            except:
                pass
    except Exception as e:
        pass  # ignore malformed packets


def start_dns():
    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    sock.bind(("0.0.0.0", 53))
    print(f"[DNS] Port 53 - redirecting CDN -> {LOCAL_IP}")
    while True:
        try:
            data, addr = sock.recvfrom(512)
            threading.Thread(target=handle_dns, args=(data, addr, sock), daemon=True).start()
        except:
            pass


def start_http():
    os.chdir(ASSETS_DIR)
    server = http.server.HTTPServer(("0.0.0.0", 80), http.server.SimpleHTTPRequestHandler)
    print(f"[HTTP] Port 80 - serving: {ASSETS_DIR}")
    server.serve_forever()


if __name__ == "__main__":
    print("=" * 50)
    print("  DNS Redirect + HTTP Server")
    print("=" * 50)
    
    threading.Thread(target=start_dns, daemon=True).start()
    
    try:
        start_http()
    except KeyboardInterrupt:
        print("\nStopped.")
    except PermissionError:
        print("\n[!] Need admin rights for port 80!")
        print("    Run: python dns_test.py")
        print("    Or close XAMPP Apache first.")
