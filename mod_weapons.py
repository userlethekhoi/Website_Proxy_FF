"""
mod_weapons.py — Extract, modify, and repack weapon config
===========================================================
Modifies Free Fire weapon stats for aimbot effect:
  - Damage x10
  - No recoil (scatter = 0)
  - Max aim assist (lock time, range)
  - Instant reload
"""

import gzip
import csv
import io
import os
import sys
from UnityPy import Environment
from UnityPy.classes import TextAsset, AssetBundle

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
IN_FILE = os.path.join(SCRIPT_DIR, "mod_assets", "weapon_config.bin")
OUT_FILE = os.path.join(SCRIPT_DIR, "mod_assets", "weapon_config.mod")

def extract_csv(bundle_path):
    """Extract CSV text from UnityFS weapon config bundle."""
    with open(bundle_path, "rb") as f:
        data = gzip.decompress(f.read())
    
    # Find CSV start position
    header_start = data.find(b"id,name,type,subtype")
    if header_start == -1:
        return None
    
    # Find CSV end: look for the row after last weapon
    # CSV ends where data becomes non-ASCII/padding
    csv_region = data[header_start:]
    
    # Find where readable CSV ends (look for "gs_overwrite" marker or long null sequence)
    end_marker = csv_region.find(b"gs_overwrite_weapon_config")
    if end_marker == -1:
        # Fallback: find long sequence of nulls
        end_marker = csv_region.find(b"\x00\x00\x00\x00\x00")
    
    if end_marker > 0:
        csv_bytes = csv_region[:end_marker]
    else:
        csv_bytes = csv_region
    
    # Clean trailing garbage
    csv_bytes = csv_bytes.rstrip(b"\x00\x20")
    
    # Convert \x00 within CSV to empty (some fields use null as separator)
    # Actually game CSV uses comma separators, null bytes are padding
    csv_text = csv_bytes.decode("ascii", errors="replace")
    
    return csv_text


def mod_weapons(csv_text, mode="HEAD"):
    """Modify weapon stats."""
    reader = csv.reader(io.StringIO(csv_text))
    rows = list(reader)
    header = rows[0]
    columns = {name: i for i, name in enumerate(header)}
    
    print(f"CSV Header: {len(header)} columns")
    print(f"Rows: {len(rows) - 1} weapons")
    
    # Find key column indices
    cols = {
        'damage': columns.get('damage', -1),
        'min_damage': columns.get('min_damage', -1),
        'fire_interval': columns.get('fire_interval', -1),
        'scatter_speed': columns.get('scatter_speed', -1),
        'scatter_move': columns.get('scatter_move', -1),
        'scatter_max': columns.get('scatter_max', -1),
        'scatter_recover_speed': columns.get('scatter_recover_speed', -1),
        'scatter_num': columns.get('scatter_num', -1),
        'bite_armor': columns.get('bite_armor', -1),
        'reload_speed': columns.get('reload_speed', -1),
        'reload_time': columns.get('reload_time', -1),
        'player_speed_factor': columns.get('player_speed_factor', -1),
        'aim_assist_lock_keep_time': columns.get('aim_assist_lock_keep_time', -1),
        'aim_assist_lock_decrease_time': columns.get('aim_assist_lock_decrease_time', -1),
        'aim_assist_min_screen_range': columns.get('aim_assist_min_screen_range', -1),
        'aim_assist_max_screen_range': columns.get('aim_assist_max_screen_range', -1),
        'armor_penetration_rate': columns.get('armor_penetration_rate', -1),
        'name': columns.get('name', 1),
    }
    
    modded = 0
    for i, row in enumerate(rows):
        if i == 0:
            continue  # Skip header
        
        name = row[cols['name']] if cols['name'] < len(row) else "?"
        
        # Skip "unread" (empty placeholder) rows
        if name == "unread":
            continue
        
        try:
            # DAMAGE x10
            if cols['damage'] >= 0 and cols['damage'] < len(row) and row[cols['damage']]:
                dmg = float(row[cols['damage']])
                row[cols['damage']] = str(int(dmg * 10))
            
            if cols['min_damage'] >= 0 and cols['min_damage'] < len(row) and row[cols['min_damage']]:
                dmg = float(row[cols['min_damage']])
                row[cols['min_damage']] = str(int(dmg * 10))
            
            # NO RECOIL - scatter = 0
            for s_col in ['scatter_speed', 'scatter_move', 'scatter_max', 'scatter_recover_speed', 'scatter_num']:
                idx = cols.get(s_col, -1)
                if idx >= 0 and idx < len(row):
                    row[idx] = "0"
            
            # MAX AIM ASSIST
            for a_col in ['aim_assist_lock_keep_time', 'aim_assist_min_screen_range', 'aim_assist_max_screen_range']:
                idx = cols.get(a_col, -1)
                if idx >= 0 and idx < len(row) and row[idx]:
                    try:
                        val = float(row[idx])
                        row[idx] = str(val * 10)  # 10x aim assist
                    except:
                        pass
            
            if cols['aim_assist_lock_decrease_time'] >= 0:
                idx = cols['aim_assist_lock_decrease_time']
                if idx < len(row):
                    row[idx] = "0"  # Never decrease lock
            
            # ARMOR PENETRATION 100%
            if cols['bite_armor'] >= 0 and cols['bite_armor'] < len(row):
                row[cols['bite_armor']] = "1.0"
            
            if cols['armor_penetration_rate'] >= 0 and cols['armor_penetration_rate'] < len(row):
                row[cols['armor_penetration_rate']] = "1.0"
            
            # FAST RELOAD (half time)
            if cols['reload_speed'] >= 0 and cols['reload_speed'] < len(row):
                row[cols['reload_speed']] = "3.0"
            if cols['reload_time'] >= 0 and cols['reload_time'] < len(row):
                row[cols['reload_time']] = "0.1"
            
            modded += 1
        except Exception as e:
            print(f"  SKIP {name}: {e}")
    
    print(f"Modified {modded} weapons")
    
    # Rebuild CSV
    output = io.StringIO()
    writer = csv.writer(output)
    writer.writerows(rows)
    return output.getvalue()


def repack_bundle(csv_text, input_path, output_path):
    """Replace CSV in bundle bytes directly (no UnityPy repacking needed)."""
    with open(input_path, "rb") as f:
        data = gzip.decompress(f.read())
    
    # Find original CSV location
    header_start = data.find(b"id,name,type,subtype")
    if header_start == -1:
        print("    ERROR: Cannot find CSV in bundle!")
        return 0
    
    end_marker = data.find(b"gs_overwrite_weapon_config", header_start)
    if end_marker == -1:
        end_marker = data.find(b"\x00\x00\x00\x00\x00", header_start)
    
    orig_len = end_marker - header_start
    print(f"    Original CSV: {orig_len} bytes at offset {header_start}")
    
    # Prepare new CSV bytes
    new_csv = csv_text.encode("ascii", errors="replace")
    new_len = len(new_csv)
    print(f"    New CSV: {new_len} bytes")
    
    if new_len <= orig_len:
        # Pad with spaces to match original length
        new_csv = new_csv + b" " * (orig_len - new_len)
    else:
        print(f"    WARNING: New CSV is larger ({new_len} > {orig_len})!")
        print("    This may break the bundle. Truncating...")
        new_csv = new_csv[:orig_len]
    
    # Replace in place
    modified = bytearray(data)
    modified[header_start:end_marker] = new_csv
    
    # Compress and save
    import gzip as gz
    with gzip.open(output_path + ".gz", "wb") as f:
        f.write(bytes(modified))
    
    return os.path.getsize(output_path + ".gz")


def main():
    print("=" * 50)
    print("  Modding Free Fire Weapons")
    print("=" * 50)
    
    print(f"\n[1] Extracting CSV from {IN_FILE}...")
    csv_text = extract_csv(IN_FILE)
    if not csv_text:
        print("FAILED to extract CSV!")
        return
    
    lines = csv_text.split("\n")
    print(f"    {len(lines)} lines extracted")
    
    # Save original CSV for reference
    csv_path = os.path.join(SCRIPT_DIR, "mod_assets", "weapons_original.csv")
    with open(csv_path, "w", encoding="utf-8") as f:
        f.write(csv_text)
    print(f"    Original saved: {csv_path}")
    
    print(f"\n[2] Modding weapons...")
    modded_csv = mod_weapons(csv_text)
    
    # Save modded CSV
    mod_csv_path = os.path.join(SCRIPT_DIR, "mod_assets", "weapons_modded.csv")
    with open(mod_csv_path, "w", encoding="utf-8") as f:
        f.write(modded_csv)
    print(f"    Modded saved: {mod_csv_path}")
    
    print(f"\n[3] Repacking bundle...")
    size = repack_bundle(modded_csv, IN_FILE, OUT_FILE)
    print(f"    Output: {OUT_FILE} ({size:,} bytes)")
    
    # Compress with gzip
    import tempfile
    gz_path = OUT_FILE + ".gz"
    with open(OUT_FILE, "rb") as f_in:
        with gzip.open(gz_path, "wb") as f_out:
            f_out.write(f_in.read())
    
    gz_size = os.path.getsize(gz_path)
    print(f"    Compressed: {gz_path} ({gz_size:,} bytes)")
    
    print(f"\n[DONE] Weapon config modded!")
    print(f"  Damage: x10")
    print(f"  Recoil: OFF")
    print(f"  Aim Assist: MAX")
    print(f"  Reload: INSTANT")


if __name__ == "__main__":
    main()
