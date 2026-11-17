#!/usr/bin/env python3
"""PRIVATE read-only, full-column, 57-table historical source row fingerprints.

All values are included; no customer/source row is emitted. Each original row
gets a SHA256 digest; sorted row digests preserve duplicate identical rows.
Reproducible with tools/database_v2/verify_mysql_full_row_digests.php.
This is a *semantic stored-value* comparison: integer and DECIMAL columns are
normalized to the V2 SQL type, but descriptions, dates, NULL and strings are
preserved exactly. No FLOAT, JSON or BLOB columns are in this source snapshot.
"""
from __future__ import annotations

import argparse
from decimal import Decimal, InvalidOperation
import hashlib
import json
from pathlib import Path
import re
import sys

from legacy_import_plan import inspect
from rehearse_legacy_in_sqlite import source_rows
from v2_import_plan import verified_schema

ROOT = Path(__file__).resolve().parents[2]
SOURCE_SHA = "bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed"
NUMERIC = re.compile(r"^(?:bigint|int|tinyint|smallint)\b", re.I)
DECIMAL = re.compile(r"^decimal\((\d+),\s*(\d+)\)", re.I)
ALLOWED = {"bigint", "int", "tinyint", "smallint", "decimal", "varchar",
           "char", "text", "mediumtext", "longtext", "date",
           "datetime", "timestamp", "enum"}


def canon(value: object, sql_type: str) -> str | None:
    if value is None:
        return None
    literal = str(value)
    if NUMERIC.match(sql_type):
        if not re.fullmatch(r"[+-]?\d+", literal):
            raise ValueError("Non-integer source value")
        return str(int(literal))
    match = DECIMAL.match(sql_type)
    if match:
        scale = int(match.group(2))
        if scale > 30:
            raise ValueError("Unexpected source decimal scale")
        try:
            decimal = Decimal(literal)
            unit = Decimal(1).scaleb(-scale)
            if decimal != decimal.quantize(unit):
                raise ValueError("Decimal source value exceeds stored scale")
            return format(decimal.quantize(unit), "f")
        except InvalidOperation as e:
            raise ValueError("Invalid source DECIMAL value") from e
    base = re.match(r"^[a-z]+", sql_type.lower())
    if base is None or base.group() not in ALLOWED:
        raise ValueError("Unreviewed SQL data type for row verification")
    return literal


def row_hash(values: list[str | None]) -> str:
    digester = hashlib.sha256()
    for value in values:
        if value is None:
            digester.update(b"N")
        else:
            binary = value.encode("utf-8")
            digester.update(b"V")
            digester.update(str(len(binary)).encode("ascii"))
            digester.update(b":")
            digester.update(binary)
    return digester.hexdigest()


def table_hash(digests: list[str]) -> str:
    return hashlib.sha256("\n".join(sorted(digests)).encode("ascii")).hexdigest()


def source_digests(source: Path, expected_sha: str = SOURCE_SHA,
                   expected_rows: int = 120165) -> dict:
    metadata, _, _, evidence_sha = verified_schema()
    manifest, chunks = inspect(source)
    if expected_sha != SOURCE_SHA or evidence_sha != expected_sha or manifest["source_sha256"] != expected_sha:
        raise ValueError("Original source/schema SHA256 mismatch")
    if len(metadata) != 57 or manifest["table_count"] != 57 or manifest["total_insert_rows"] != expected_rows:
        raise ValueError("57-table/row-count source mismatch")

    result = {}
    total = 0
    for name, columns in sorted(metadata.items()):
        names = list(columns)
        parsed = source_rows(chunks[name], name)
        parts = []
        for record in parsed:
            if set(record) != set(names):
                raise ValueError("Source columns differ from complete schema in " + name)
            parts.append(row_hash([
                canon(record[col], columns[col])
                for col in names
            ]))
        expected_count = next(t["rows"] for t in manifest["tables"] if t["name"] == name)
        if len(parsed) != expected_count:
            raise ValueError("Source INSERT count differs for " + name)
        result[name] = {
            "rows": len(parts),
            "column_count": len(names),
            "digest": table_hash(parts),
        }
        total += len(parts)
    if total != expected_rows:
        raise ValueError("Full row total mismatch")
    return {
        "kind": "v2-full-row-semantic-v1",
        "source_sha256": expected_sha,
        "source_rows": expected_rows,
        "source_tables": 57,
        "tables": result,
        "limitations": [
            "Full stored-value equality on original columns, not a new-V2-derived-column policy check.",
            "Source and target must use the same character set and timezone interpretation.",
            "Does not establish typed financial source attribution or debit-credit correctness.",
        ],
    }


def main() -> None:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--source", type=Path, required=True)
    ap.add_argument("--out", type=Path, required=True)
    ap.add_argument("--expected-sha256", default=SOURCE_SHA)
    ap.add_argument("--expected-rows", type=int, default=120165)
    args = ap.parse_args()
    out = args.out.resolve()
    if out == ROOT or ROOT in out.parents or out.exists():
        raise ValueError("Evidence must be a new file OUTSIDE the Git repository")
    report = source_digests(args.source, args.expected_sha256, args.expected_rows)
    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text(json.dumps(report, indent=2) + "\n", encoding="utf-8")
    print("SOURCE_FULL_ROW_DIGESTS_READY: 57 tables, 120165 records, original columns verified")
    print("PRIVATE_EVIDENCE_PATH:", out)


if __name__ == "__main__":
    try:
        main()
    except (OSError, ValueError, UnicodeError) as exc:
        print("SOURCE_FULL_ROW_DIGESTS_BLOCKED: " + str(exc), file=sys.stderr)
        sys.exit(2)
