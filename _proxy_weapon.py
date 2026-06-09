"""
_proxy_weapon.py — Intercept fileinfo + assetindexer + weapon_config
======================================================================
fileinfo -> fileinfo_mod.txt (danh sach asset da mod)
assetindexer -> assetindexer.mod (Unity cheat bundle)
gs_overwrite_weapon_config -> weapon_config.mod.gz (recoil=0, scatter=0)
"""
import os
from mitmproxy import http, ctx

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
ASSETS = os.path.join(SCRIPT_DIR, "mod_assets")

# Tat ca file can intercept
FILES = {
    "fileinfo": os.path.join(ASSETS, "fileinfo_mod.txt"),
    "assetindexer": os.path.join(ASSETS, "assetindexer.mod"),
    "gs_overwrite_weapon_config": os.path.join(ASSETS, "weapon_config.mod.gz"),
}

def request(flow: http.HTTPFlow) -> None:
    url = flow.request.pretty_url.lower()
    
    for key, path in FILES.items():
        if key in url and os.path.exists(path):
            with open(path, "rb") as f:
                data = f.read()
            ct = "text/plain" if key == "fileinfo" else "application/octet-stream"
            flow.response = http.Response.make(200, data, {"Content-Type": ct})
            ctx.log.info(f"[MOD] {key} ({len(data)} bytes)")
            return
