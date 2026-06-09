"""
merge_fileinfo.py - Chen dong assetindexer mod vao fileinfo hien tai
====================================================================
1. Doc fileinfo_v123.txt (28KB - ban game hien tai)
2. Doc dong "avatar/assetindexer,..." tu fileinfo cu (soucreproxynew)
3. Chen dong mod vao fileinfo hien tai
4. Luu thanh fileinfo_mod.txt -> proxy serve file nay
"""
import os
import shutil

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))

CURRENT_FILEINFO = os.path.join(SCRIPT_DIR, "mod_assets", "fileinfo_v123.txt")
OLD_FILEINFO = os.path.join(SCRIPT_DIR, "soucreproxynew", "fileinfo")
OUTPUT = os.path.join(SCRIPT_DIR, "mod_assets", "fileinfo_mod.txt")

# Dong assetindexer tu fileinfo cu (proxyvip su dung)
ASSET_INDEXER_LINE = "avatar/assetindexer,z4c5PKvI2Gj+SdP+eRekPvzPvXY=,41213,3,L+U/P13sC4cZw2UIfxkWDaO7lB4="

def main():
    # 1. Doc fileinfo hien tai
    with open(CURRENT_FILEINFO, "r", encoding="utf-8") as f:
        lines = f.readlines()
    print(f"[1] fileinfo_v123.txt: {len(lines)} lines")
    
    # 2. Kiem tra xem da co assetindexer chua
    has_asset = any("assetindexer" in line for line in lines)
    if has_asset:
        print("[!] fileinfo da co assetindexer, khong chen them")
    else:
        # Chen vao vi tri thich hop (sau dong avatarassets neu co, hoac cuoi file)
        inserted = False
        for i, line in enumerate(lines):
            if "avatar" in line.lower():
                lines.insert(i + 1, ASSET_INDEXER_LINE + "\n")
                inserted = True
                print(f"[2] Chen assetindexer vao dong {i+2}")
                break
        
        if not inserted:
            lines.append(ASSET_INDEXER_LINE + "\n")
            print(f"[2] Chen assetindexer vao cuoi file")
    
    # 3. Luu file mod
    with open(OUTPUT, "w", encoding="utf-8") as f:
        f.writelines(lines)
    print(f"[3] Done: {OUTPUT} ({len(lines)} lines, {os.path.getsize(OUTPUT)} bytes)")
    
    # 4. Copy assetindexer.mod vao mod_assets
    src = os.path.join(SCRIPT_DIR, "soucreproxynew", "assetindexer.z4c5PKvI2Gj~2BSdP~2BeRekPvzPvXY~3D")
    dst = os.path.join(SCRIPT_DIR, "mod_assets", "assetindexer.mod")
    if os.path.exists(src):
        shutil.copy2(src, dst)
        print(f"[4] Copied assetindexer.mod ({os.path.getsize(dst)} bytes)")
    
    print("\n[DONE]")
    print("  fileinfo_mod.txt -> proxy serve file nay")
    print("  assetindexer.mod -> proxy serve file nay")
    print("  Game doc fileinfo_mod -> thay assetindexer -> tai assetindexer.mod")
    print("  AssetBundle chua code cheat -> aimbot hoat dong!")

if __name__ == "__main__":
    main()
