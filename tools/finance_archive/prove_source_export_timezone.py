#!/usr/bin/env python3
"""Read-only private SQL export timezone evidence; never prints SQL row data.

This tool verifies the exact ORIGINAL private SQL snapshot SHA256 and inspects
only its header for an explicit import-replayable SQL time_zone assignment.
The source file must remain outside Git and is never copied or modified.
A missing timezone assignment is UNKNOWN rather than guessed from local OS.
"""
from __future__ import annotations

import argparse
import hashlib
import pathlib
import re
import sys

SOURCE_SHA256 = "bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed"
HEADER_BYTES = 262144
# MySQL/MariaDB executable versioned comments and plain session SQL.
TIMEZONE_SETTING = re.compile(
    rb"(?im)(?:/\*!\d{5,6}\s*)?SET\s+(?:(?:@@)?SESSION\.)?TIME_ZONE"
    rb"\s*=\s*(['\"])([+-](?:0\d|1[0-4]):[0-5]\d|UTC|SYSTEM)\1"
)
TIMEZONE_TOKEN = re.compile(
    rb"(?im)(?:/\*!\d{5,6}\s*)?SET\s+(?:(?:@@)?SESSION\.)?TIME_ZONE\s*="
)


def inspect_header(header: bytes) -> str | None:
    assignments = TIMEZONE_TOKEN.findall(header)
    found = [x.group(2).decode("ascii").upper() for x in TIMEZONE_SETTING.finditer(header)]
    if not assignments or not found or len(found) != len(assignments):
        return None
    if len(set(found)) != 1:
        return None
    value = found[0]
    if value == "SYSTEM":
        return None
    return value


def run_selftest() -> None:
    samples = [
        (b"/*!40103 SET TIME_ZONE='+00:00' */;\nINSERT INTO t VALUES(1);", "+00:00"),
        (b"SET SESSION.time_zone = '-05:00';\n", "-05:00"),
        (b"SET time_zone = 'UTC';\n", "UTC"),
        (b"INSERT INTO log VALUES('text');", None),
        (b"SET TIME_ZONE=@OLD_TIME_ZONE;", None),
        (b"SET time_zone='SYSTEM';", None),
        (b"SET time_zone='+00:00'; SET time_zone='+05:00';", None),
    ]
    for header, expected in samples:
        if inspect_header(header) != expected:
            raise RuntimeError("SOURCE_EXPORT_TZ_SELFTEST_FAILED")
    print("SOURCE_EXPORT_TZ_SELFTEST_PASS")


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--source", type=pathlib.Path)
    parser.add_argument("--expected-sha256", default=SOURCE_SHA256)
    parser.add_argument("--selftest", action="store_true")
    args = parser.parse_args()
    if args.selftest:
        run_selftest()
        return 0
    if not args.source or args.expected_sha256.lower() != SOURCE_SHA256:
        print("SOURCE_EXPORT_TZ_REFUSED: source file and exact snapshot SHA required", file=sys.stderr)
        return 2
    p = args.source.resolve(strict=True)
    repo = pathlib.Path(__file__).resolve().parents[2]
    if p == repo or repo in p.parents or not p.is_file():
        print("SOURCE_EXPORT_TZ_REFUSED: original dump must be outside repository", file=sys.stderr)
        return 2
    digest = hashlib.sha256()
    with p.open("rb") as source:
        header = source.read(HEADER_BYTES)
        digest.update(header)
        while block := source.read(1024 * 1024):
            digest.update(block)
    if digest.hexdigest() != SOURCE_SHA256:
        print("SOURCE_EXPORT_TZ_REFUSED: snapshot SHA mismatch", file=sys.stderr)
        return 2
    zone = inspect_header(header)
    if zone is None:
        print("SOURCE_EXPORT_TIMEZONE_UNPROVEN: no unambiguous fixed source session timezone in SQL header")
        return 3
    print(f"SOURCE_EXPORT_TIMEZONE_EXPLICIT_PASS: snapshot_sha_match=true, session_timezone={zone}")
    print("LIMITATION: import-session interpretation and timestamp value parity require independent evidence.")
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except (OSError, ValueError):
        print("SOURCE_EXPORT_TZ_REFUSED: cannot read original source snapshot", file=sys.stderr)
        sys.exit(2)
