#!/usr/bin/env python3
"""Read-only by default: verify legacy SQL and plan FK-safe staging import.
With --out-dir, write DATA-ONLY per-table SQL and a metadata manifest outside
the Git repository. Never connects to a database, runs DDL or applies data.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import pathlib
import re
import sys

CREATE = re.compile(r"CREATE TABLE `([^`]+)` \((.*?)\n\) ENGINE=", re.S)
ALTER = re.compile(r"ALTER TABLE `([^`]+)`\s+(.*?);", re.S)
FK = re.compile(r"ADD CONSTRAINT `[^`]+` FOREIGN KEY \(`[^`]+`\) REFERENCES `([^`]+)`", re.S)
INSERT = re.compile(r"INSERT INTO `([^`]+)`\s*\(([^)]*)\) VALUES\s*\n(.*?);(?=\s*\n|$)", re.S)


def count_rows(sql_values: str) -> int:
    rows, depth, quoted, escaped = 0, 0, False, False
    for char in sql_values:
        if escaped:
            escaped = False
            continue
        if quoted and char == "\\":
            escaped = True
            continue
        if char == "'":
            quoted = not quoted
            continue
        if quoted:
            continue
        if char == "(":
            if depth == 0:
                rows += 1
            depth += 1
        elif char == ")":
            depth -= 1
            if depth < 0:
                raise ValueError("Unexpected closing parenthesis in INSERT")
    if depth != 0 or quoted or escaped:
        raise ValueError("Truncated or invalid INSERT VALUES")
    return rows


def ordered_tables(names: set[str], dependencies: dict[str, set[str]]) -> list[str]:
    remaining = {n: dependencies.get(n, set()).intersection(names) - {n} for n in names}
    order = []
    while remaining:
        ready = sorted(n for n, deps in remaining.items() if not deps)
        if not ready:
            raise ValueError("Cyclic dependencies: " + ",".join(sorted(remaining)))
        order.extend(ready)
        for name in ready:
            del remaining[name]
        for deps in remaining.values():
            deps.difference_update(ready)
    return order


def inspect(path: pathlib.Path) -> tuple[dict, dict[str, list[str]]]:
    raw = path.read_bytes()
    body = raw.decode("utf-8-sig", errors="strict")
    tables = set(CREATE.findall(body)[i][0] for i in range(len(CREATE.findall(body))))
    if not tables:
        raise ValueError("No legacy CREATE TABLE declarations found")
    dependencies: dict[str, set[str]] = {n: set() for n in tables}
    fk_count = 0
    for table, definition in ALTER.findall(body):
        if table not in tables:
            raise ValueError("ALTER references undeclared table " + table)
        for parent in FK.findall(definition):
            if parent not in tables:
                raise ValueError("Foreign key parent missing: " + parent)
            dependencies[table].add(parent)
            fk_count += 1

    chunks: dict[str, list[str]] = {name: [] for name in tables}
    counts = {name: 0 for name in tables}
    for match in INSERT.finditer(body):
        name = match.group(1)
        if name not in tables:
            raise ValueError("INSERT references undeclared table " + name)
        chunks[name].append(match.group(0))
        counts[name] += count_rows(match.group(3))

    order = ordered_tables(tables, dependencies)
    manifest = {
        "source_file": path.name,
        "source_sha256": hashlib.sha256(raw).hexdigest(),
        "table_count": len(tables),
        "declared_fk_count": fk_count,
        "total_insert_rows": sum(counts.values()),
        "table_import_order": order,
        "tables": [
            {"name": name, "rows": counts[name], "insert_statements": len(chunks[name]),
             "data_sql_sha256": hashlib.sha256(("\n".join(chunks[name]) + "\n").encode("utf-8")).hexdigest()}
            for name in order
        ],
        "limitations": [
            "DATA ONLY: this does not create target tables or execute SQL",
            "Existing legacy migration history must be archived, never applied as V2 migrations",
            "Foreign-key graph covers declared legacy constraints only; V2 candidate links require review",
            "No historical financial source attribution or reconciliation is inferred",
        ],
    }
    return manifest, chunks


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--source", required=True, type=pathlib.Path)
    ap.add_argument("--expected-sha256", help="Required for writing staging output")
    ap.add_argument("--expected-rows", type=int)
    ap.add_argument("--out-dir", type=pathlib.Path, help="Explicitly emit raw sensitive data files OUTSIDE repository")
    args = ap.parse_args()
    manifest, chunks = inspect(args.source)
    if args.expected_sha256 and manifest["source_sha256"] != args.expected_sha256.lower():
        raise ValueError("Source SHA256 mismatch; aborting")
    if args.expected_rows is not None and manifest["total_insert_rows"] != args.expected_rows:
        raise ValueError("Unexpected row count; aborting")
    if args.out_dir:
        if not args.expected_sha256:
            raise ValueError("--expected-sha256 required for --out-dir")
        out = args.out_dir.resolve()
        repo_root = pathlib.Path(__file__).resolve().parents[2]
        if out == repo_root or repo_root in out.parents:
            raise ValueError("Refusing to write private SQL into Git working tree")
        if out.exists() and any(out.iterdir()):
            raise ValueError("Output directory is not empty; refusing overwrite")
        out.mkdir(parents=True, exist_ok=True)
        for table in manifest["table_import_order"]:
            (out / (table + ".sql")).write_text(
                "-- Sensitive legacy DATA ONLY; verify isolated staging target before import.\n" +
                ("\n".join(chunks[table]) + "\n" if chunks[table] else ""),
                encoding="utf-8")
        (out / "manifest.json").write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")
        print("Wrote sensitive data-only staging files to explicit directory (no database changes).")
    print(json.dumps({k: manifest[k] for k in ("source_sha256", "table_count", "declared_fk_count", "total_insert_rows", "table_import_order")}, indent=2))
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except (ValueError, OSError, UnicodeError) as exc:
        print("BLOCKED: " + str(exc), file=sys.stderr)
        sys.exit(2)
