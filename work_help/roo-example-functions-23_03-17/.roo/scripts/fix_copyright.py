#!/usr/bin/env python3
"""Deterministic copyright fixer for PHP files."""

from __future__ import annotations

import os
import re
import sys
from pathlib import Path

COPYRIGHT_LINE = "// Copyright (c) 2026 Roo Local Examples"
DECLARE_RE = re.compile(r"^\s*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;")


def project_root() -> Path:
    return Path(__file__).resolve().parents[2]


def has_copyright(text: str) -> bool:
    return "copyright" in text.lower()


def starts_with_php(text: str) -> bool:
    return text.lstrip("\ufeff").startswith("<?php")


def find_php_file(query: str, root: Path) -> Path | None:
    needle = (query or "").strip().lower()

    # Direct path first.
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


def insert_copyright(text: str) -> tuple[str, bool]:
    if has_copyright(text):
        return text, False
    if not starts_with_php(text):
        raise ValueError("missing php open tag")

    newline = "\r\n" if "\r\n" in text else "\n"
    lines = text.splitlines(keepends=True)
    if not lines:
        raise ValueError("empty file")

    insert_at = 1
    for idx, line in enumerate(lines[:40]):
        if DECLARE_RE.match(line):
            insert_at = idx + 1
            break

    lines.insert(insert_at, f"{COPYRIGHT_LINE}{newline}")
    return "".join(lines), True


def main() -> int:
    query = sys.argv[1] if len(sys.argv) > 1 else ""
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
        updated, changed = insert_copyright(text)
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
