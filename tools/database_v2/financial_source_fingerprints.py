#!/usr/bin/env python3
"""Generate PRIVATE, aggregate-only source evidence for V2 MySQL verification.

Requires the exact SQL dump SHA256; no database connection. Writes evidence
OUTSIDE the repository. No raw ledger rows, customer data, or descriptions.
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
MONEY = re.compile(r"(^|_)(amount(?:_in|_out)?|debit|credit|balance|price|rate|total_minor)(_|$)", re.I)
NUMERIC_TYPE = re.compile(r"^(?:decimal|double|float|int|bigint|tinyint|smallint)\b", re.I)
DATES = ("created_at", "updated_at", "deleted_at")


def source_evidence(path: Path, checksum: str, row_count: int = 120165) -> dict:
    manifest, chunks = inspect(path)
    cols, _, _, original = verified_schema()
    if checksum.lower() != manifest["source_sha256"] or checksum.lower() != original:
        raise ValueError("Source dump fingerprint differs; refusing evidence generation")
    if manifest["table_count"] != 57 or manifest["total_insert_rows"] != row_count:
        raise ValueError("Source 57-table/count mismatch")

    table_stats = {}
    for table, columns in cols.items():
        meta = dict(columns)
        money_cols = sorted(name for name, sql_type in meta.items()
                            if MONEY.search(name) and NUMERIC_TYPE.match(sql_type))
        rows = source_rows(chunks[table], table)
        ids = [int(row["id"]) for row in rows if "id" in row and row["id"] is not None]
        dates = {}
        for field in DATES:
            if field in meta:
                values = sorted(str(row[field]) for row in rows if row.get(field) is not None)
                dates[field] = {
                    "non_null": len(values),
                    "min": values[0] if values else None,
                    "max": values[-1] if values else None,
                }
        totals = {}
        for field in money_cols:
            total = Decimal(0)
            non_null = 0
            for row in rows:
                value = row.get(field)
                if value is None:
                    continue
                non_null += 1
                try:
                    total += Decimal(str(value))
                except InvalidOperation as e:
                    raise ValueError("Non-numeric source money field: " + table + "." + field) from e
            totals[field] = {"non_null": non_null, "sum": format(total, "f")}
        table_stats[table] = {
            "rows": len(rows),
            "id_non_null": len(ids) if "id" in meta else None,
            "id_min": min(ids) if ids else None,
            "id_max": max(ids) if ids else None,
            "timestamps": dates,
            "numeric_totals": totals,
        }
    return {
        "kind": "source-aggregate-fingerprints-no-customer-rows",
        "source_sha256": manifest["source_sha256"],
        "source_rows": manifest["total_insert_rows"],
        "table_count": manifest["table_count"],
        "tables": table_stats,
        "limitations": [
            "Aggregate count, ID bounds, timestamps, money sums do not prove exact all-column row parity",
            "Run separate MySQL imported-row and FK audits; investigate any exceptions, do not auto-repair",
            "Do not upload or commit this private evidence unless reviewed",
        ],
    }


def main() -> None:
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument("--source", type=Path, required=True)
    p.add_argument("--expected-sha256", required=True)
    p.add_argument("--expected-rows", type=int, default=120165)
    p.add_argument("--out", type=Path, required=True)
    args = p.parse_args()
    out = args.out.resolve()
    if out == ROOT or ROOT in out.parents:
        raise ValueError("Private financial fingerprint must be outside Git repository")
    if out.exists():
        raise ValueError("Refusing overwrite of existing evidence file")
    evidence = source_evidence(args.source, args.expected_sha256, args.expected_rows)
    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text(json.dumps(evidence, indent=2) + "\n", encoding="utf-8")
    print(json.dumps({
        "status": "SOURCE_AGGREGATES_VERIFIED",
        "tables": evidence["table_count"],
        "rows": evidence["source_rows"],
        "money_columns": sum(len(t["numeric_totals"]) for t in evidence["tables"].values()),
        "output": str(out),
    }, indent=2))


if __name__ == "__main__":
    try:
        main()
    except (OSError, ValueError) as e:
        print("BLOCKED: " + str(e), file=sys.stderr)
        sys.exit(2)
