# Read-only exact-source UTC timestamp audit. No DML, DDL or migration.
param(
    [Parameter(Mandatory=$true)][string]$SourceSql,
    [Parameter(Mandatory=$true)][string]$SourceEvidence
)
$ErrorActionPreference = "Stop"
$repo = (Resolve-Path (Join-Path $PSScriptRoot "../..")).Path
if (-not (Test-Path -LiteralPath $SourceSql -PathType Leaf)) {
    throw "Original private SQL dump not found"
}
if (-not (Test-Path -LiteralPath $SourceEvidence -PathType Leaf)) {
    throw "Private source-full-row digest evidence not found"
}
$realSql = (Resolve-Path -LiteralPath $SourceSql).Path
$realEvidence = (Resolve-Path -LiteralPath $SourceEvidence).Path
if ($realSql.StartsWith($repo + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase) -or
    $realEvidence.StartsWith($repo + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
    throw "Private SQL and digest must remain outside repository"
}
Push-Location $repo
try {
    $env:APP_ENV = "local"
    $env:DB_CONNECTION = "mysql"
    $env:DB_HOST = "127.0.0.1"
    $env:DB_PORT = "3306"
    $env:DB_DATABASE = "bluemarketing_v2_local_rehearsal"
    $env:DB_URL = ""
    $env:DATABASE_URL = ""

    py -3 ./tools/database_v2/prove_source_export_timezone.py --source "$realSql"
    if ($LASTEXITCODE -ne 0) {
        throw "V2_SOURCE_UTC_PROOF_FAILED; no history changed"
    }

    php ./tools/database_v2/verify_mysql_full_row_digests.php "--source-evidence=$realEvidence" --verify-source-utc
    if ($LASTEXITCODE -ne 0) {
        throw "V2_SOURCE_UTC_IMPORT_SEMANTIC_MISMATCH_OR_BLOCK; do not rewrite history"
    }
    Write-Output "V2_SOURCE_UTC_HISTORICAL_SEMANTICS_PASS: source SHA+UTC and all 57 imported tables"
}
finally {
    Pop-Location
}
