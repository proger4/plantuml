#!/usr/bin/env python
from __future__ import annotations

import argparse
import json
from pathlib import Path


def project_root() -> Path:
    return Path(__file__).resolve().parents[2]


def load_llm_config(root: Path) -> dict:
    path = root / ".roo/config/llm.yaml"
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except FileNotFoundError:
        raise RuntimeError(f"missing config: {path}")
    except json.JSONDecodeError as exc:
        raise RuntimeError(f"invalid config format: {path} ({exc})")


def main() -> int:
    parser = argparse.ArgumentParser(description="Validate local LLM JSON response structure")
    parser.add_argument("json_file")
    parser.add_argument("--format", choices=["table", "json"], default="table")
    args = parser.parse_args()

    root = project_root()
    try:
        llm_cfg = load_llm_config(root)
    except RuntimeError as exc:
        if args.format == "json":
            print(json.dumps({"ok": False, "error": str(exc)}, ensure_ascii=False, indent=2))
        else:
            print(f"ERROR: {exc}")
        return 1

    required = llm_cfg.get("required_response_keys", [])
    if not isinstance(required, list) or not all(isinstance(x, str) for x in required):
        msg = "invalid required_response_keys in llm.yaml"
        if args.format == "json":
            print(json.dumps({"ok": False, "error": msg}, ensure_ascii=False, indent=2))
        else:
            print(f"ERROR: {msg}")
        return 1

    target = Path(args.json_file)
    if not target.is_absolute():
        target = (root / target).resolve()

    if not target.is_file():
        msg = f"file not found: {target}"
        if args.format == "json":
            print(json.dumps({"ok": False, "error": msg}, ensure_ascii=False, indent=2))
        else:
            print(f"ERROR: {msg}")
        return 1

    try:
        payload = json.loads(target.read_text(encoding="utf-8"))
    except json.JSONDecodeError as exc:
        msg = f"invalid JSON: {exc}"
        if args.format == "json":
            print(json.dumps({"ok": False, "error": msg}, ensure_ascii=False, indent=2))
        else:
            print(f"ERROR: {msg}")
        return 1

    if not isinstance(payload, dict):
        msg = "response must be JSON object"
        if args.format == "json":
            print(json.dumps({"ok": False, "error": msg}, ensure_ascii=False, indent=2))
        else:
            print(f"ERROR: {msg}")
        return 1

    missing = [k for k in required if k not in payload]
    ok = not missing
    result = {
        "ok": ok,
        "file": str(target),
        "required_keys": required,
        "missing_keys": missing,
    }

    if args.format == "json":
        print(json.dumps(result, ensure_ascii=False, indent=2))
    else:
        if ok:
            print("OK: LLM JSON has all required keys")
        else:
            print("ERROR: missing keys: " + ", ".join(missing))

    return 0 if ok else 1


if __name__ == "__main__":
    raise SystemExit(main())
