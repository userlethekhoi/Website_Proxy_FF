"""
traffic_analyze.py — Analyze Captured Free Fire Traffic
========================================================
Reads all capture/*.json files and produces a report:
  - Discovered domains/endpoints
  - Requests containing game-related keys (hitbox, aim, shoot, etc.)
  - Suggested GAME_API_PATTERNS for proxy_modifier.py
  - Suggested JSON field mappings

Usage:
    python traffic_analyze.py                    # Full report
    python traffic_analyze.py --endpoints        # Just show endpoints
    python traffic_analyze.py --game-keys        # Show game-related fields
    python traffic_analyze.py --suggest          # Generate ready-to-use config
"""

import json
import os
import sys
import argparse
from collections import defaultdict, Counter

CAPTURE_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "capture")

# Keywords to search for in JSON payloads
GAME_KEYWORDS = [
    "hit", "aim", "shoot", "damage", "recoil", "spread", "bullet",
    "head", "body", "neck", "kill", "death", "match", "player",
    "position", "rotation", "weapon", "fire", "scope", "target",
    "hitbox", "hit_zone", "crosshair", "trigger", "ammo",
    "health", "armor", "headshot", "bodyshot", "limbshot",
    "assist", "magnetic", "drag", "lock", "smooth", "fov",
    "sensitivity", "accuracy", "penetration", "range",
]


def load_captures():
    """Load all capture JSON files."""
    if not os.path.isdir(CAPTURE_DIR):
        print(f"[!] No capture directory found: {CAPTURE_DIR}")
        print("    Run traffic_capture.py first to capture game traffic.")
        sys.exit(1)

    files = [f for f in os.listdir(CAPTURE_DIR) if f.endswith(".json")]
    if not files:
        print(f"[!] No capture files found in {CAPTURE_DIR}")
        print("    Run traffic_capture.py first, then play Free Fire.")
        sys.exit(1)

    captures = []
    for fname in sorted(files):
        fpath = os.path.join(CAPTURE_DIR, fname)
        try:
            with open(fpath, "r", encoding="utf-8") as f:
                captures.append(json.load(f))
        except Exception as e:
            print(f"  [skip] {fname}: {e}")

    return captures


def analyze_endpoints(captures):
    """Show all discovered domains and endpoints."""
    print("\n" + "=" * 65)
    print("  📡 DISCOVERED ENDPOINTS")
    print("=" * 65)

    domains = Counter()
    endpoints = defaultdict(list)

    for cap in captures:
        host = cap.get("host", "unknown")
        path = cap.get("path", "/")
        method = cap.get("method", "?")
        domains[host] += 1
        endpoints[host].append(f"  {method:6s} {path}")

    for domain, count in domains.most_common():
        print(f"\n  🌐 {domain} ({count} requests)")
        unique_eps = sorted(set(endpoints[domain]))
        for ep in unique_eps[:10]:  # Show top 10 per domain
            print(ep)
        if len(unique_eps) > 10:
            print(f"      ... and {len(unique_eps) - 10} more")


def find_game_keys_recursive(obj, prefix=""):
    """Recursively find keys matching game keywords."""
    results = []
    if isinstance(obj, dict):
        for key, value in obj.items():
            key_lower = key.lower()
            for kw in GAME_KEYWORDS:
                if kw in key_lower:
                    full_key = f"{prefix}.{key}" if prefix else key
                    val_preview = str(value)[:120]
                    results.append({
                        "keyword": kw,
                        "full_key": full_key,
                        "value": val_preview,
                        "type": type(value).__name__,
                    })
                    break
            if isinstance(value, dict):
                results.extend(
                    find_game_keys_recursive(value, f"{prefix}.{key}" if prefix else key)
                )
            elif isinstance(value, list) and value and isinstance(value[0], dict):
                results.extend(
                    find_game_keys_recursive(value[0], f"{prefix}.{key}[0]" if prefix else f"{key}[0]")
                )
    return results


def analyze_game_keys(captures):
    """Find all requests containing game-related JSON fields."""
    print("\n" + "=" * 65)
    print("  🎮 GAME-RELATED REQUESTS")
    print("=" * 65)

    found_any = False
    for cap in captures:
        req_json = cap.get("request_json") or cap.get("response_json")
        potential_keys = cap.get("potential_game_keys", [])

        if not req_json and not potential_keys:
            # Try parsing request_body if not already parsed
            body = cap.get("request_body", "")
            if body and body.strip().startswith("{"):
                try:
                    req_json = json.loads(body)
                    potential_keys = find_game_keys_recursive(req_json)
                except:
                    pass

        if potential_keys:
            found_any = True
            host = cap.get("host", "?")
            path = cap.get("path", "/")
            method = cap.get("method", "?")

            print(f"\n  🔍 {method} {host}{path}")
            for k in potential_keys:
                print(f"      └─ {k['full_key']} = {k['value']}")

    if not found_any:
        print("\n  ⚠️  No game-related keys found in captured traffic.")
        print("  Possible reasons:")
        print("    1. Game uses Protobuf/Binary (not JSON) — needs .proto file")
        print("    2. Game encrypts payload — needs decryption first")
        print("    3. Not enough game actions captured (shoot, aim, die)")
        print("    4. Game communicates over UDP, not HTTP")


def suggest_config(captures):
    """Generate ready-to-use config for proxy_modifier.py."""
    print("\n" + "=" * 65)
    print("  📋 SUGGESTED CONFIG FOR proxy_modifier.py")
    print("=" * 65)

    # Collect all domains seen
    domains = sorted(set(c.get("host", "") for c in captures if c.get("host")))

    # Find domains with game-related content
    game_domains = set()
    for cap in captures:
        if cap.get("potential_game_keys"):
            game_domains.add(cap.get("host", ""))

    # Collect unique game keys
    all_game_keys = set()
    for cap in captures:
        for k in (cap.get("potential_game_keys") or []):
            all_game_keys.add(k.get("full_key", ""))

    print("\n  # Add to GAME_API_PATTERNS in proxy_modifier.py:")
    print("  GAME_API_PATTERNS = [")
    if game_domains:
        for d in sorted(game_domains):
            # Extract just the domain part (without subdomain)
            parts = d.split(".")
            if len(parts) > 2:
                print(f'      "{parts[-2]}.{parts[-1]}",     # {d}')
            else:
                print(f'      "{d}",')
    else:
        print("      # No game domains identified yet")
        print("      # Add domains manually after reviewing capture/ files")
    print("  ]")

    if all_game_keys:
        print("\n  # Potential JSON fields to modify:")
        for k in sorted(all_game_keys):
            print(f"  #   data['{k}'] = ...")

    print(f"\n  # Total domains captured: {len(domains)}")
    if domains:
        print("  # All domains seen:")
        for d in domains:
            print(f"  #   {d}")


def show_summary(captures):
    """Show capture summary."""
    total = len(captures)
    with_game_data = sum(1 for c in captures if c.get("potential_game_keys"))
    domains = len(set(c.get("host", "") for c in captures))

    print("\n" + "=" * 65)
    print("  📊 CAPTURE SUMMARY")
    print("=" * 65)
    print(f"  Total requests captured : {total}")
    print(f"  With game-related data : {with_game_data}")
    print(f"  Unique domains          : {domains}")

    if with_game_data == 0:
        print()
        print("  ⚠️  TIPS TO GET BETTER DATA:")
        print("     1. Play a full match (not just lobby)")
        print("     2. Shoot enemies, get kills, die")
        print("     3. Try different weapons")
        print("     4. Try both training mode + ranked match")
        print("     5. If still nothing, game may use UDP/Custom protocol")
        print("        → Use Wireshark + filter: 'host <iPhone_IP>'")
    else:
        print(f"\n  ✅ Found {with_game_data} requests with game data!")
        print("  → Run with --suggest to generate config")


def main():
    parser = argparse.ArgumentParser(description="Analyze captured Free Fire traffic")
    parser.add_argument("--endpoints", action="store_true", help="Show endpoints only")
    parser.add_argument("--game-keys", action="store_true", help="Show game-related fields")
    parser.add_argument("--suggest", action="store_true", help="Generate config for proxy_modifier.py")
    parser.add_argument("--all", action="store_true", help="Show everything (default)")
    args = parser.parse_args()

    # Default: show all
    if not any([args.endpoints, args.game_keys, args.suggest]):
        args.all = True

    captures = load_captures()
    show_summary(captures)

    if args.all or args.endpoints:
        analyze_endpoints(captures)

    if args.all or args.game_keys:
        analyze_game_keys(captures)

    if args.all or args.suggest:
        suggest_config(captures)

    print("\n" + "=" * 65)


if __name__ == "__main__":
    main()
