"""
aimbot_server.py — DNS Redirect + HTTP Server = Real Aimbot
============================================================
1. DNS: redirect game CDN → local IP
2. HTTP: serve modded weapon config with MAX AIM ASSIST
3. Game downloads modded config → aim head when shooting

iPhone: DNS → 192.168.1.252
"""
import socket, struct, threading, http.server, os, sys, time

LOCAL_IP = sys.argv[1] if len(sys.argv) > 1 else "192.168.1.252"
ASSETS = os.path.join(os.path.dirname(os.path.abspath(__file__)), "mod_assets")

REDIRECT_DOMAINS = ["freefiremobile.com"]

def handle_dns(data, addr, sock):
    try:
        domain, pos = [], 12
        while data[pos] != 0:
            domain.append(data[pos+1:pos+1+data[pos]].decode())
            pos += data[pos] + 1
        domain_name = ".".join(domain).lower()

        is_game = any(d in domain_name for d in REDIRECT_DOMAINS)

        if is_game:
            resp = data[:2] + b"\x81\x80" + data[4:6] + b"\x00\x01\x00\x00\x00\x00"
            q_end = pos + 5
            resp += data[12:q_end]
            resp += b"\xc0\x0c\x00\x01\x00\x01\x00\x00\x00\x3c\x00\x04" + socket.inet_aton(LOCAL_IP)
            sock.sendto(resp, addr)
            print(f"  [DNS] {domain_name} -> {LOCAL_IP}")
        else:
            fwd = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
            fwd.settimeout(3)
            try:
                fwd.sendto(data, ("8.8.8.8", 53))
                resp = fwd.recv(1024)
                sock.sendto(resp, addr)
            except: pass
            fwd.close()
    except: pass

def start_dns():
    s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    s.bind(("0.0.0.0", 53))
    print(f"[DNS] Redirecting freefiremobile.com -> {LOCAL_IP}")
    while True:
        try:
            data, addr = s.recvfrom(512)
            threading.Thread(target=handle_dns, args=(data, addr, s), daemon=True).start()
        except: pass

class Handler(http.server.SimpleHTTPRequestHandler):
    def __init__(self, *a, **kw):
        super().__init__(*a, directory=ASSETS, **kw)
    def log_message(self, f, *a):
        print(f"  [HTTP] {a[0]}")

def start_http():
    s = http.server.HTTPServer(("0.0.0.0", 80), Handler)
    print(f"[HTTP] Serving modded assets on port 80")
    s.serve_forever()

if __name__ == "__main__":
    print("=" * 55)
    print("  AIMBOT SERVER - DNS + HTTP")
    print("=" * 55)
    threading.Thread(target=start_dns, daemon=True).start()
    time.sleep(0.5)
    
    # Also start Apache on different port for web
    try:
        import subprocess
        subprocess.Popen(["C:\\xampp\\apache\\bin\\httpd.exe", "-f", "C:\\xampp\\apache\\conf\\httpd.conf"],
                        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    except: pass
    
    try:
        start_http()
    except KeyboardInterrupt:
        print("\nStopped.")
    except PermissionError:
        print("[!] Need admin for port 80. Close XAMPP Apache first.")
