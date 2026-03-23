#!/usr/bin/env python3
"""Scan PHP files and report copyright status."""

from __future__ import annotations

import os
import subprocess
import sys
from pathlib import Path


def project_root() -> Path:
    return Path(__file__).resolve().parents[2]


def iter_php_files(base: Path):
    for dirpath, dirnames, filenames in os.walk(base):
        dirnames.sort()
        filenames.sort()
        for name in filenames:
            if name.lower().endswith(".php"):
                yield Path(dirpath) / name


def main() -> int:
    root = project_root()
    scope_arg = sys.argv[1] if len(sys.argv) > 1 else "."
    scope = (root / scope_arg).resolve() if scope_arg != "." else root

    if not scope.exists():
        print("ERROR: not found")
        return 1

    if scope.is_file():
        files = [scope] if scope.suffix.lower() == ".php" else []
    else:
        files = list(iter_php_files(scope))

    if not files:
        print("ERROR: not found")
        return 1

    verifier = root / ".roo/scripts/verify_copyright.py"

    exit_code = 0
    for php_file in files:
        proc = subprocess.run(
            [sys.executable, str(verifier), str(php_file)],
            capture_output=True,
            text=True,
            check=False,
        )
        status = proc.stdout.strip() or "BROKEN"
        rel = php_file.relative_to(root).as_posix() if php_file.is_relative_to(root) else php_file.as_posix()

        if status == "OK":
            print(f"OK: {rel}")
        elif status == "MISSING":
            print(f"MISSING: {rel}")
            exit_code = 1
        else:
            print(f"BROKEN: {rel}")
            exit_code = 1

    return exit_code


if __name__ == "__main__":
    raise SystemExit(main())
