"""
generate_key.py — Tạo license key test nhanh (ko cần web)
==========================================================
Tạo license key trực tiếp vào MySQL để test aimbot.

Usage:
    python generate_key.py              # 1 key, 30 ngày
    python generate_key.py 5 90         # 5 keys, 90 ngày
"""

import mysql.connector
import secrets
import sys
import string

DB = {"host": "127.0.0.1", "user": "ioscruxcom", "password": "ioscruxcom", "database": "ioscruxcom"}


def main():
    count = int(sys.argv[1]) if len(sys.argv) > 1 else 1
    days = int(sys.argv[2]) if len(sys.argv) > 2 else 30

    try:
        conn = mysql.connector.connect(**DB)
        cursor = conn.cursor()

        keys = []
        for _ in range(count):
            a = secrets.token_hex(4).upper()[:8]
            b = secrets.token_hex(4).upper()[:8]
            key = f"PXFF-{a}-{b}"
            cursor.execute(
                "INSERT INTO licenses (license_key, duration_days, device_limit) VALUES (%s, %s, 3)",
                (key, days),
            )
            keys.append(key)

        conn.commit()
        cursor.close()
        conn.close()

        print(f"✅ Đã tạo {count} license key ({days} ngày):")
        for k in keys:
            print(f"   {k}")
        print()
        print("👉 Vào web → Dashboard → Nhập key để kích hoạt")

    except mysql.connector.Error as e:
        print(f"❌ Lỗi MySQL: {e}")
        print("   Đảm bảo MySQL đang chạy và đã import schema.sql")


if __name__ == "__main__":
    main()
