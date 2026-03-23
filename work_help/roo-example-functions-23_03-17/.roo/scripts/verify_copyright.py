#!/usr/bin/env python3
"""Strict copyright structure validator for one PHP file."""

from __future__ import annotations

import re
import sys
from pathlib import Path

COPYRIGHT_RE = re.compile(r"copyright", re.IGNORECASE)
DECLARE_RE = re.compile(r"^\s*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;")


def starts_with_php(text: str) -> bool:
    return text.lstrip("\ufeff").startswith("<?php")


def classify(text: str) -> str:
    if not starts_with_php(text):
        return "BROKEN"

    lines = text.splitlines()
    if not lines:
        return "BROKEN"

    copyright_indexes = [i for i, line in enumerate(lines) if COPYRIGHT_RE.search(line)]
    if not copyright_indexes:
        return "MISSING"

    idx = copyright_indexes[0]

    declare_idx = None
    for i, line in enumerate(lines[:40]):
        if DECLARE_RE.match(line):
            declare_idx = i
            break

    if declare_idx is not None:
        expected = declare_idx + 1
    else:
        expected = 1

    return "OK" if idx == expected else "BROKEN"


def main() -> int:
    if len(sys.argv) < 2:
        print("ERROR: usage: verify_copyright.py <php-file>")
        return 1

    target = Path(sys.argv[1])
    if not target.is_file() or target.suffix.lower() != ".php":
        print("ERROR: not found")
        return 1

    try:
        text = target.read_text(encoding="utf-8")
    except UnicodeDecodeError:
        print("BROKEN")
        return 1

    status = classify(text)
    print(status)
    return 0 if status == "OK" else 1


if __name__ == "__main__":
    raise SystemExit(main())
