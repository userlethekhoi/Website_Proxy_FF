import gzip
from UnityPy import load

path = r"C:\Users\Le The Khoi\OneDrive\Documents\ProxyFF\mod_assets\weapon_config.bin"
with open(path, "rb") as f:
    compressed = f.read()

data = gzip.decompress(compressed)
env = load(data)

for obj in env.objects:
    d = obj.read()
    if obj.type.name == "TextAsset":
        # Try to access raw bytes
        raw = d.object_reader.get_bytes()
        print(f"Raw bytes: {len(raw)}")
        text = raw.decode("utf-8", errors="replace")
        out = path.replace(".bin", ".json")
        with open(out, "w", encoding="utf-8") as f:
            f.write(text)
        print(f"Saved to: {out}")
        print(f"Preview: {text[:500]}")
        if text.strip().startswith("{") or text.strip().startswith("["):
            import json
            parsed = json.loads(text)
            if isinstance(parsed, list):
                print(f"\nList of {len(parsed)} items")
                if parsed:
                    print(f"First item keys: {list(parsed[0].keys())[:15]}")
            elif isinstance(parsed, dict):
                print(f"\nDict with keys: {list(parsed.keys())[:15]}")
        break
