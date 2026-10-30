# READ ONLY: separately authorized NEW V2 local database candidate.
# This script does NOT create a database, run migrations, import or edit rows.
[CmdletBinding()]
param(
    [Parameter(Mandatory=$true)][string]$Database,
    [Parameter(Mandatory=$true)][string]$SourceSql,
    [Parameter(Mandatory=$true)][string]$SourceRowDigests,
    [Parameter(Mandatory=$true)][string]$Manifest,
    [Parameter(Mandatory=$true)][string]$FinancialEvidence
)
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$sha = 'bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed'
$repo = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path

# Never accept original clone, V1, dev/client/staging/live, an arbitrary server
# or a guessed target. A NEW candidate must have a distinct explicit name.
if ($Database -cnotmatch '^bluemarketing_v2_local_[a-z0-9_]+$' -or
    $Database -ceq 'bluemarketing_v2_local_rehearsal' -or
    $Database -match '(?i:prod|stag|live|dev|client)') {
    throw 'V2_UTC_CANDIDATE_REFUSED: require distinct new isolated local V2 database'
}

foreach ($path in @($SourceSql, $SourceRowDigests, $Manifest, $FinancialEvidence)) {
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) {
        throw 'V2_UTC_CANDIDATE_REFUSED: missing private evidence file'
    }
    $real = (Resolve-Path -LiteralPath $path).Path
    if ($real.StartsWith($repo + [IO.Path]::DirectorySeparatorChar,
            [StringComparison]::OrdinalIgnoreCase)) {
        throw 'V2_UTC_CANDIDATE_REFUSED: private source evidence inside Git'
    }
}
$sourcePath = (Resolve-Path -LiteralPath $SourceSql).Path
$rowEvidencePath = (Resolve-Path -LiteralPath $SourceRowDigests).Path
$manifestPath = (Resolve-Path -LiteralPath $Manifest).Path
$financePath = (Resolve-Path -LiteralPath $FinancialEvidence).Path

Push-Location $repo
try {
    # Before even selecting a database, prove the exact original export is UTC.
    py -3 ./tools/database_v2/prove_source_export_timezone.py --source "$sourcePath"
    if ($LASTEXITCODE -ne 0) { throw 'SOURCE_SHA_OR_UTC_HEADER_PROOF_FAILED' }

    $env:APP_ENV = 'local'
    $env:DB_CONNECTION = 'mysql'
    $env:DB_HOST = '127.0.0.1'
    $env:DB_PORT = '3306'
    $env:DB_DATABASE = $Database
    $env:DB_URL = ''
    $env:DATABASE_URL = ''

    php ./tools/database_v2/verify_mysql_full_row_digests.php `
        "--source-evidence=$rowEvidencePath" --verify-source-utc
    if ($LASTEXITCODE -ne 0) {
        throw 'V2_NEW_UTC_REHEARSAL_FULL_ROW_PARITY_FAILED'
    }

    php ./tools/database_v2/verify_imported_mysql_financial.php `
        "--manifest=$manifestPath" `
        "--source-evidence=$financePath" `
        "--expected-sha256=$sha" --expected-rows=120165
    if ($LASTEXITCODE -ne 0) {
        throw 'V2_NEW_UTC_REHEARSAL_FINANCIAL_PARITY_FAILED'
    }

    Write-Output 'V2_NEW_UTC_REHEARSAL_PARITY_PASS: 57 original tables, 120165 rows, UTC instants + financial aggregates; READ_ONLY'
    Write-Warning 'This does NOT accept ambiguous JV source ownership, authorize deployment or replace V1.'
}
finally {
    Pop-Location
}
