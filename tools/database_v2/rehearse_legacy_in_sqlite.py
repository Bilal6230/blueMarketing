#!/usr/bin/env python3
"""Read-only real-source SQL rehearsal against disposable in-memory SQLite.

Verifies all source row tuples, PK/unique keys and 40+46 FK parent links.
It is NOT equivalent to loading MySQL 8.4 or verifying financial source identity.
Customer data never leaves the process and is never written into Git/output.
"""
from __future__ import annotations

import argparse
from collections import Counter, defaultdict
import hashlib
import json
from pathlib import Path
import re
import sqlite3
import sys

from legacy_import_plan import INSERT, inspect, ordered_tables
from v2_import_plan import verified_schema


def mysql_string(value: str) -> str:
    out = []
    escapes = {"0": "\0", "n": "\n", "r": "\r", "t": "\t",
               "b": "\b", "Z": "\x1a", "\\": "\\", "'": "'", '"': '"'}
    i = 0
    while i < len(value):
        c = value[i]
        if c == "\\" and i + 1 < len(value):
            out.append(escapes.get(value[i + 1], value[i + 1]))
            i += 2
            continue
        if c == "'" and i + 1 < len(value) and value[i + 1] == "'":
            out.append("'")
            i += 2
            continue
        out.append(c)
        i += 1
    return "".join(out)


def sql_fields(s: str) -> tuple:
    raw, start, quoted, escaped = [], 0, False, False
    for i, char in enumerate(s):
        if escaped:
            escaped = False
            continue
        if quoted and char == "\\":
            escaped = True
            continue
        if char == "'":
            quoted = not quoted
            continue
        if char == "," and not quoted:
            raw.append(s[start:i].strip())
            start = i + 1
    if quoted or escaped:
        raise ValueError("Invalid SQL tuple quoting")
    raw.append(s[start:].strip())
    result = []
    for field in raw:
        if field.upper() == "NULL":
            result.append(None)
        elif field.startswith("'") and field.endswith("'"):
            result.append(mysql_string(field[1:-1]))
        else:
            result.append(field)
    return tuple(result)


def source_rows(chunks: list[str], table: str) -> list[dict]:
    data = []
    for sql in chunks:
        match = INSERT.fullmatch(sql.strip()) if False else INSERT.match(sql)
        if match is None or match.group(1) != table:
            raise ValueError("Invalid source INSERT in " + table)
        columns = re.findall(r"`([^`]+)`", match.group(2))
        text = match.group(3)
        start, depth, quoted, escaped = None, 0, False, False
        for i, ch in enumerate(text):
            if escaped:
                escaped = False
                continue
            if quoted and ch == "\\":
                escaped = True
                continue
            if ch == "'":
                quoted = not quoted
                continue
            if quoted:
                continue
            if ch == "(":
                if depth == 0:
                    start = i + 1
                depth += 1
            elif ch == ")":
                depth -= 1
                if depth < 0:
                    raise ValueError("Unbalanced source tuple")
                if depth == 0:
                    row = sql_fields(text[start:i])
                    if len(row) != len(columns):
                        raise ValueError("Source column/tuple mismatch in " + table)
                    data.append(dict(zip(columns, row)))
        if quoted or depth != 0:
            raise ValueError("Unterminated source values in " + table)
    return data


def quote(s: str) -> str:
    return '"' + s.replace('"', '""') + '"'


def run(source: Path, expected_sha: str, expected_rows: int = 120165) -> dict:
    manifest, chunks = inspect(source)
    metadata, original, added, fingerprint = verified_schema()
    if manifest["source_sha256"] != expected_sha.lower() or fingerprint != expected_sha.lower():
        raise ValueError("Source/schema evidence fingerprint mismatch; no DB write attempted")
    if manifest["table_count"] != 57 or manifest["total_insert_rows"] != expected_rows:
        raise ValueError("Source 57-table/row-count mismatch; no DB write attempted")

    edges = [(t, f["column"], f["references"], f["ref_column"])
             for t, m in original.items() for f in m.get("foreign", [])]
    if len(edges) != 40:
        raise ValueError("Expected 40 declared FKs")
    edges += [(t, col, p, "id") for t, cs in added.items() for col, p in cs.items()]
    if len(edges) != 86:
        raise ValueError("Expected 46 additional parent links")
    deps = defaultdict(set)
    for child, _, parent, _ in edges:
        if child not in metadata or (parent not in metadata and parent != "banks"):
            raise ValueError("Unrecognized source relationship")
        deps[child].add(parent)
    order = ordered_tables(set(metadata), deps)
    db = sqlite3.connect(":memory:")
    db.execute("PRAGMA foreign_keys=ON")
    try:
        db.execute("CREATE TABLE banks (id TEXT PRIMARY KEY)")
        db.executemany("INSERT INTO banks (id) VALUES (?)", [(str(i),) for i in range(1, 28)])
        rows_by_table = {}
        for table in order:
            target = "legacy_migration_history" if table == "migrations" else table
            keys = original.get(table, {}).get("primary", [])
            columns = metadata[table]
            definitions = [quote(c) + " TEXT" + (" PRIMARY KEY" if keys == [c] else "") for c in columns]
            if len(keys) > 1:
                definitions.append("PRIMARY KEY (" + ",".join(quote(c) for c in keys) + ")")
            if table == "sites":
                definitions.append("project_head_subhead_id TEXT")
            for entry in original.get(table, {}).get("unique", []):
                definitions.append("UNIQUE (" + ",".join(quote(c) for c in entry["cols"]) + ")")
            for child, col, parent, ref_col in edges:
                if child == table:
                    if parent == "migrations":
                        parent = "legacy_migration_history"
                    definitions.append(f"FOREIGN KEY ({quote(col)}) REFERENCES {quote(parent)} ({quote(ref_col)}) ON DELETE RESTRICT")
            db.execute(f"CREATE TABLE {quote(target)} (" + ",".join(definitions) + ")")

        for table in order:
            target = "legacy_migration_history" if table == "migrations" else table
            rows = source_rows(chunks[table], table)
            if len(rows) != next(x["rows"] for x in manifest["tables"] if x["name"] == table):
                raise ValueError("Source parse count mismatch for " + table)
            rows_by_table[table] = rows
            if rows:
                columns = list(metadata[table])
                sql = "INSERT INTO " + quote(target) + " (" + ",".join(map(quote, columns)) + ") VALUES (" + ",".join("?" for _ in columns) + ")"
                db.executemany(sql, [tuple(row.get(c) for c in columns) for row in rows])

        sites = db.execute("SELECT count(*) FROM sites WHERE subhead_accounting_id IS NOT NULL").fetchone()[0]
        db.execute("""UPDATE sites SET project_head_subhead_id=subhead_accounting_id
            WHERE subhead_accounting_id IS NOT NULL AND EXISTS (
              SELECT 1 FROM project_head_subheads a
              WHERE a.id=sites.subhead_accounting_id AND a.project_id=sites.project_id)""")
        mapped = db.execute("SELECT count(*) FROM sites WHERE project_head_subhead_id IS NOT NULL").fetchone()[0]
        if mapped != sites:
            raise ValueError("Site account project mismatch")
        mismatches = []
        for table in order:
            target = "legacy_migration_history" if table == "migrations" else table
            columns = list(metadata[table])
            sql = "SELECT " + ",".join(map(quote, columns)) + " FROM " + quote(target)
            actual = Counter(db.execute(sql).fetchall())
            expected = Counter(tuple(row.get(c) for c in columns) for row in rows_by_table[table])
            if actual != expected:
                mismatches.append(table)
        failures = db.execute("PRAGMA foreign_key_check").fetchall()
        return {
            "engine": "SQLite in-memory rehearsal ONLY; NOT a MySQL 8.4 import",
            "source_sha256": manifest["source_sha256"],
            "source_tables": 57,
            "source_rows": manifest["total_insert_rows"],
            "mirror_rows": sum(len(rows) for rows in rows_by_table.values()),
            "source_parent_fk_edges": len(edges),
            "fk_violations": len(failures),
            "row_multiset_mismatch_tables": len(mismatches),
            "verified_site_account_links": mapped,
            "archived_legacy_migration_rows": len(rows_by_table["migrations"]),
            "original_source_modified": False,
        }
    finally:
        db.close()


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--source", type=Path, required=True)
    parser.add_argument("--expected-sha256", required=True)
    parser.add_argument("--expected-rows", type=int, default=120165)
    args = parser.parse_args()
    print(json.dumps(run(args.source, args.expected_sha256, args.expected_rows), indent=2))


if __name__ == "__main__":
    try:
        main()
    except (ValueError, OSError, sqlite3.DatabaseError) as e:
        print("SOURCE_REHEARSAL_BLOCKED: " + str(e), file=sys.stderr)
        sys.exit(2)
