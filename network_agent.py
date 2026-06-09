import socket
import struct
import mysql.connector
import time
from mysql.connector import Error

# Config
DB_CONFIG = {
    "host": "127.0.0.1",
    "user": "ioscruxcom",
    "password": "ioscruxcom",
    "database": "ioscruxcom",
    "port": 3306
}

# Bind address and port for incoming traffic from client
PROXY_HOST = "127.0.0.1"
PROXY_PORT = 9999

# Remote target server (where we forward modified packages)
TARGET_HOST = "127.0.0.1"
TARGET_PORT = 10000

# Binary Packet configuration
# [2 Byte Header][4 Byte ID][1 Byte Status Flag][4 Byte Checksum]
# Format: '!H I B I' (Network byte order / Big Endian)
# H = unsigned short (2 bytes), I = unsigned int (4 bytes), B = unsigned char (1 byte)
PACKET_FORMAT = '!H I B I'
PACKET_SIZE = struct.calcsize(PACKET_FORMAT) # Should be 2 + 4 + 1 + 4 = 11 bytes

# Status Flag Values mapping from Database Targets
POLICY_FLAG_MAP = {
    "NORMAL":   None,     # Do not modify
    # --- Legacy (still supported) ---
    "FLAG_A":   0x0A,     # Aim Head
    "FLAG_B":   0x0B,     # Aim Neck
    # --- Aimbot Modes ---
    "AIM_HEAD": 0x0A,     # Aim Head - instant snap to head hitbox
    "AIM_NECK": 0x0B,     # Aim Neck - snap to neck hitbox
    "AIM_BODY": 0x0C,     # Aim Body - snap to body hitbox
    "AIM_LOCK": 0x0D,     # Aim Lock - hard lock, no recoil/spread
    "AIM_DRAG": 0x0E,     # Aim Drag - smooth drag crosshair to target
}

# Human-readable labels for logging
AIM_MODE_LABELS = {
    0x0A: "AIM_HEAD",
    0x0B: "AIM_NECK",
    0x0C: "AIM_BODY",
    0x0D: "AIM_LOCK",
    0x0E: "AIM_DRAG",
}

def calculate_checksum(header: int, packet_id: int, status_flag: int) -> int:
    """
    Calculates a simple, robust additive checksum of the payload components.
    In real systems, this could be CRC32 or Adler-32.
    """
    # Simple additive checksum of the components
    checksum = (header + packet_id + status_flag) & 0xFFFFFFFF
    return checksum

def get_routing_policies():
    """
    Retrieves the routing configuration for all registered clients from MySQL.
    Returns a dict mapping client IP address to routing target.
    """
    policies = {}
    conn = None
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        if conn.is_connected():
            cursor = conn.cursor(dictionary=True)
            cursor.execute("SELECT client_ip, routing_target FROM device_filters")
            rows = cursor.fetchall()
            for row in rows:
                policies[row['client_ip']] = row['routing_target']
            cursor.close()
    except Error as e:
        print(f"[Database Error] Could not read policies: {e}")
    finally:
        if conn and conn.is_connected():
            conn.close()
    return policies

def start_proxy():
    # Setup UDP socket
    proxy_socket = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    proxy_socket.bind((PROXY_HOST, PROXY_PORT))
    
    print(f"[*] Core Network Agent listening on UDP {PROXY_HOST}:{PROXY_PORT}")
    print(f"[*] Forwarding target set to {TARGET_HOST}:{TARGET_PORT}")
    
    last_policy_check = 0
    policies = {}
    
    try:
        while True:
            # Periodically reload policies from database every 2 seconds
            current_time = time.time()
            if current_time - last_policy_check > 2.0:
                policies = get_routing_policies()
                last_policy_check = current_time
                print(f"[Sync] Loaded policies for {len(policies)} devices: {policies}")

            # Receive raw binary data from UDP Client
            data, addr = proxy_socket.recvfrom(1024)
            client_ip = addr[0]
            client_port = addr[1]
            
            if len(data) != PACKET_SIZE:
                print(f"[Warning] Received packet with invalid size ({len(data)} bytes) from {client_ip}:{client_port}. Skipping processing.")
                # Directly forward raw data if it doesn't match format
                proxy_socket.sendto(data, (TARGET_HOST, TARGET_PORT))
                continue

            try:
                # 3. Unpack the raw byte payload using struct.unpack
                # [2 Byte Header][4 Byte ID][1 Byte Status Flag][4 Byte Checksum]
                header, packet_id, status_flag, checksum = struct.unpack(PACKET_FORMAT, data)
                
                print(f"\n[Received] Packet from {client_ip}:{client_port}")
                print(f"  - Header      : 0x{header:04X} ({header})")
                print(f"  - Packet ID   : {packet_id}")
                print(f"  - Status Flag : 0x{status_flag:02X} ({status_flag})")
                print(f"  - Checksum    : 0x{checksum:08X} ({checksum})")

                # Verify incoming checksum before editing (optional log)
                expected_in_checksum = calculate_checksum(header, packet_id, status_flag)
                if checksum != expected_in_checksum:
                    print(f"  [!] Warning: Incoming checksum mismatch! Received: 0x{checksum:08X}, Expected: 0x{expected_in_checksum:08X}")

                # 4. Check policy and adjust status flag
                routing_target = policies.get(client_ip, "NORMAL")
                target_flag = POLICY_FLAG_MAP.get(routing_target, None)

                modified = False
                new_status_flag = status_flag

                if target_flag is not None and status_flag != target_flag:
                    new_status_flag = target_flag
                    modified = True
                    mode_label = AIM_MODE_LABELS.get(target_flag, f"0x{target_flag:02X}")
                    print(f"  [*] Aimbot Mode [{routing_target}] ({mode_label}): 0x{status_flag:02X} -> 0x{new_status_flag:02X}")

                # 5. Pack data back using struct.pack and forward
                if modified:
                    # Recalculate checksum
                    new_checksum = calculate_checksum(header, packet_id, new_status_flag)
                    # Pack modified fields
                    outgoing_data = struct.pack(PACKET_FORMAT, header, packet_id, new_status_flag, new_checksum)
                    print(f"  [Modified] Outgoing: ID={packet_id}, Flag=0x{new_status_flag:02X}, New Checksum=0x{new_checksum:08X}")
                else:
                    outgoing_data = data
                    print(f"  [Forwarding] Packet unchanged.")

                # Forward packet to Target Server
                proxy_socket.sendto(outgoing_data, (TARGET_HOST, TARGET_PORT))

            except Exception as e:
                print(f"[Error] Failed to parse or packet manipulation error: {e}")
                # Forward unmodified payload in case of errors
                proxy_socket.sendto(data, (TARGET_HOST, TARGET_PORT))

    except KeyboardInterrupt:
        print("\n[*] Proxy shutting down gracefully.")
    finally:
        proxy_socket.close()

if __name__ == "__main__":
    start_proxy()
