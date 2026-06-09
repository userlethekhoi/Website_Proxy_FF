"""
tcp_proxy.py - Smart TCP Proxy: Only intercept CDN, forward everything else
===========================================================================
- Reads TLS SNI or HTTP Host from first bytes
- If CDN domain (freefiremobile.com): serves modded file directly
- If not CDN: TCP passthrough (raw forward, no SSL interference)
"""
import socket
import struct
import threading
import os
import ssl

LISTEN_HOST = "0.0.0.0"
LISTEN_PORT = 8080
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
MOD_DIR = os.path.join(SCRIPT_DIR, "mod_assets")

CDN_PATTERNS = [b"freefiremobile.com"]  # Chi CDN, khong chan game server

# Key: URL path pattern -> file to serve
MODDED_FILES = {
    b"gs_overwrite_weapon_config": os.path.join(MOD_DIR, "weapon_config.mod.gz"),
    b"fileinfo": os.path.join(MOD_DIR, "fileinfo_v123.txt"),
    b"assetindexer": os.path.join(MOD_DIR, "assetindexer.mod"),
}

def extract_sni(data):
    """Extract SNI from TLS ClientHello."""
    try:
        if len(data) < 43 or data[0] != 0x16:  # TLS handshake
            return None
        # Skip: 1(ContentType) + 2(Version) + 2(Length)
        # + 1(HandshakeType) + 3(Length) + 2(Version) + 32(Random)
        session_id_len = data[43]
        pos = 44 + session_id_len
        if pos + 2 > len(data):
            return None
        cipher_suites_len = struct.unpack("!H", data[pos:pos+2])[0]
        pos += 2 + cipher_suites_len
        if pos + 1 > len(data):
            return None
        comp_len = data[pos]
        pos += 1 + comp_len
        if pos + 2 > len(data):
            return None
        ext_len = struct.unpack("!H", data[pos:pos+2])[0]
        pos += 2
        end = pos + ext_len
        while pos + 4 <= end:
            ext_type = struct.unpack("!H", data[pos:pos+2])[0]
            ext_len2 = struct.unpack("!H", data[pos+2:pos+4])[0]
            if ext_type == 0:  # SNI
                sni_pos = pos + 9  # skip list_len + name_type + name_len
                sni_len = struct.unpack("!H", data[pos+7:pos+9])[0]
                if sni_pos + sni_len <= len(data):
                    return data[sni_pos:sni_pos+sni_len]
            pos += 4 + ext_len2
    except:
        pass
    return None


def extract_host(data):
    """Extract Host header from HTTP request."""
    try:
        lines = data.split(b"\r\n")
        for line in lines[:20]:
            if line.lower().startswith(b"host:"):
                host = line[5:].strip()
                # Remove port if present
                if b":" in host:
                    host = host.split(b":")[0]
                return host
    except:
        pass
    return None


def is_cdn_domain(host):
    """Check if host matches CDN patterns."""
    if not host:
        return False
    host_lower = host.lower()
    for pattern in CDN_PATTERNS:
        if pattern in host_lower:
            return True
    return False


def handle_cdn(client_sock, host, first_data):
    """Handle CDN connection - accept CONNECT, do TLS, serve modded file."""
    try:
        # Accept CONNECT
        client_sock.send(b"HTTP/1.1 200 Connection Established\r\n\r\n")
        
        # TLS handshake with our cert
        ctx = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
        ctx.load_cert_chain(
            os.path.expandvars(r"%USERPROFILE%\.mitmproxy\mitmproxy-ca-cert.pem"),
            os.path.expandvars(r"%USERPROFILE%\.mitmproxy\mitmproxy-ca.pem"),
        )
        
        tls_sock = ctx.wrap_socket(client_sock, server_side=True)
        
        # Read HTTP request from TLS tunnel
        req = b""
        while b"\r\n\r\n" not in req:
            chunk = tls_sock.recv(4096)
            if not chunk:
                break
            req += chunk
            if len(req) > 16384:
                break
        
        # Extract URL path
        url_path = b"/"
        lines = req.split(b"\r\n")
        if lines:
            parts = lines[0].split(b" ")
            if len(parts) >= 2:
                url_path = parts[1]
        
        host_str = host.decode(errors="replace") if isinstance(host, bytes) else host
        print(f"  [CDN] {host_str}{url_path.decode(errors='replace')}")
        
        # Check if this request matches any modded file
        for pattern, filepath in MODDED_FILES.items():
            if pattern in url_path or pattern in host_str.encode():
                if os.path.exists(filepath):
                    with open(filepath, "rb") as f:
                        content = f.read()
                    
                    resp = (
                        b"HTTP/1.1 200 OK\r\n"
                        b"Content-Type: application/octet-stream\r\n"
                        b"Content-Length: " + str(len(content)).encode() + b"\r\n"
                        b"Connection: close\r\n"
                        b"\r\n"
                    )
                    tls_sock.send(resp + content)
                    print(f"  [MOD] Served {os.path.basename(filepath)} ({len(content)} bytes)")
                    tls_sock.close()
                    return
        
        # Not a modded file - send 404
        tls_sock.send(b"HTTP/1.1 404 Not Found\r\nContent-Length: 0\r\n\r\n")
        tls_sock.close()
    except Exception as e:
        print(f"  [CDN ERR] {e}")
        try:
            client_sock.close()
        except:
            pass


def forward_data(src, dst, direction):
    """Forward data between two sockets."""
    try:
        while True:
            data = src.recv(4096)
            if not data:
                break
            dst.send(data)
    except:
        pass


def handle_client(client_sock, addr):
    """Handle incoming client connection."""
    try:
        # Read first bytes to determine destination
        first = client_sock.recv(4096)
        if not first:
            client_sock.close()
            return
        
        # Extract host from TLS SNI or HTTP Host
        host = extract_sni(first) or extract_host(first)
        
        if host:
            host_str = host.decode(errors="replace")
            print(f"  [{addr[0]}] {host_str}")
        else:
            print(f"  [{addr[0]}] unknown host")
        
        if host and is_cdn_domain(host):
            # Intercept CDN traffic - accept CONNECT, do TLS, serve modded
            handle_cdn(client_sock, host, first)
        else:
            # FORWARD: connect to real destination via TLS SNI or HTTP
            # For TLS, we need to forward the raw bytes
            # But we need the real server IP, which we got from DNS
            
            # Since we're intercepting at proxy level, the client already 
            # resolved DNS. The first data contains the target info.
            # For HTTP proxy mode, client sends CONNECT host:port
            if first.startswith(b"CONNECT "):
                # Parse CONNECT host:port
                parts = first.split(b" ")
                if len(parts) >= 2:
                    target = parts[1].decode()
                    if ":" in target:
                        target_host, target_port = target.split(":")
                        target_port = int(target_port)
                    else:
                        target_host = target
                        target_port = 443
                    
                    try:
                        remote = socket.create_connection((target_host, target_port), timeout=10)
                        client_sock.send(b"HTTP/1.1 200 Connection Established\r\n\r\n")
                        
                        # Bidirectional forwarding
                        t1 = threading.Thread(target=forward_data, args=(client_sock, remote, "C->S"), daemon=True)
                        t2 = threading.Thread(target=forward_data, args=(remote, client_sock, "S->C"), daemon=True)
                        t1.start()
                        t2.start()
                        t1.join(timeout=60)
                        t2.join(timeout=60)
                        remote.close()
                    except Exception as e:
                        print(f"  [ERR] Forward failed: {e}")
                        client_sock.send(b"HTTP/1.1 502 Bad Gateway\r\n\r\n")
            else:
                # Direct HTTP request
                try:
                    host_str = host.decode(errors="replace") if host else "unknown"
                    remote = socket.create_connection((host_str, 80), timeout=10)
                    remote.send(first)
                    
                    t1 = threading.Thread(target=forward_data, args=(client_sock, remote, "C->S"), daemon=True)
                    t2 = threading.Thread(target=forward_data, args=(remote, client_sock, "S->C"), daemon=True)
                    t1.start()
                    t2.start()
                    t1.join(timeout=60)
                    t2.join(timeout=60)
                    remote.close()
                except Exception as e:
                    print(f"  [ERR] HTTP forward failed: {e}")
        
        client_sock.close()
    except Exception as e:
        print(f"  [ERR] handle_client: {e}")
        try:
            client_sock.close()
        except:
            pass


def main():
    server = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    server.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    server.bind((LISTEN_HOST, LISTEN_PORT))
    server.listen(50)
    
    print("=" * 60)
    print(f"  ProxyFF - Smart TCP Proxy")
    print(f"  Listen: {LISTEN_HOST}:{LISTEN_PORT}")
    print(f"  CDN Interception: {list(CDN_PATTERNS)}")
    print(f"  Modded files: {list(MODDED_FILES.keys())}")
    print(f"  Non-CDN traffic: forwarded (passthrough)")
    print("=" * 60)
    
    try:
        while True:
            client, addr = server.accept()
            threading.Thread(target=handle_client, args=(client, addr), daemon=True).start()
    except KeyboardInterrupt:
        print("\nStopped.")
    finally:
        server.close()


if __name__ == "__main__":
    main()
