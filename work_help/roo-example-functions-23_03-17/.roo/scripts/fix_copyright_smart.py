#!/usr/bin/env python3
"""Strategy-driven deterministic PHP copyright fixer."""

from __future__ import annotations

import os
import re
import sys
from pathlib import Path

COPYRIGHT_LINE = "// Copyright (c) 2026 Roo Local Examples"
DECLARE_RE = re.compile(r"^\s*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;")
VALID_STRATEGIES = {"after_open_tag", "after_declare"}


def project_root() -> Path:
    return Path(__file__).resolve().parents[2]


def find_php_file(query: str, root: Path) -> Path | None:
    needle = (query or "").strip().lower()

    direct = (root / query).resolve() if query else None
    if query and direct and direct.is_file() and direct.suffix.lower() == ".php":
        return direct

    for dirpath, dirnames, filenames in os.walk(root):
        dirnames.sort()
        filenames.sort()
        for name in filenames:
            if not name.lower().endswith(".php"):
                continue
            full = Path(dirpath) / name
            rel = str(full.relative_to(root)).replace("\\", "/").lower()
            if not needle or needle in rel or needle in name.lower():
                return full
    return None


def starts_with_php(text: str) -> bool:
    return text.lstrip("\ufeff").startswith("<?php")


def has_copyright(text: str) -> bool:
    return "copyright" in text.lower()


def apply_strategy(text: str, strategy: str) -> tuple[str, bool]:
    if has_copyright(text):
        return text, False
    if not starts_with_php(text):
        raise ValueError("missing php open tag")

    lines = text.splitlines(keepends=True)
    if not lines:
        raise ValueError("empty file")

    newline = "\r\n" if "\r\n" in text else "\n"

    if strategy == "after_open_tag":
        insert_at = 1
    elif strategy == "after_declare":
        insert_at = None
        for idx, line in enumerate(lines[:40]):
            if DECLARE_RE.match(line):
                insert_at = idx + 1
                break
        if insert_at is None:
            raise ValueError("strategy requires declare(strict_types=1);")
    else:
        raise ValueError(f"unknown strategy: {strategy}")

    lines.insert(insert_at, f"{COPYRIGHT_LINE}{newline}")
    return "".join(lines), True


def main() -> int:
    if len(sys.argv) < 3:
        print("ERROR: usage: fix_copyright_smart.py <path-or-query> <strategy>")
        return 1

    query = sys.argv[1]
    strategy = sys.argv[2].strip()

    if strategy not in VALID_STRATEGIES:
        print("ERROR: strategy must be after_open_tag or after_declare")
        return 1

    root = project_root()
    target = find_php_file(query, root)
    if target is None:
        print("ERROR: not found")
        return 1

    text = target.read_text(encoding="utf-8")

    if has_copyright(text):
        print("OK: already present")
        return 0

    try:
        updated, changed = apply_strategy(text, strategy)
    except ValueError as exc:
        print(f"ERROR: {exc}")
        return 1

    if changed:
        target.write_text(updated, encoding="utf-8")
        rel = target.relative_to(root).as_posix()
        print(f"UPDATED: {rel}")
        return 0

    print("OK: already present")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
