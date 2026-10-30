# BlueMarketing V2 — guarded Windows/Laragon historical data rehearsal
# Stage 1: read-only SQL verification/staging. NO database connection.
# Stage 2: -Apply, explicitly named empty local V2 MySQL/MariaDB database.
# No CREATE DATABASE, DROP, TRUNCATE, migrate:fresh or production writes.
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)][string]$SourceSql,
    [Parameter(Mandatory = $true)][string]$StageDir,
    [switch]$Apply,
    [switch]$ResumePrepared,
    [switch]$PreserveLegacyZeroDate,
    [string]$Database = '',
    [string]$ConfirmEmptyDatabase = '',
    [string]$SourceRowDigests = ''
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$expectedSha = 'bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed'
$expectedRows = '120165'
$repo = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$source = [System.IO.Path]::GetFullPath($SourceSql)
$staging = [System.IO.Path]::GetFullPath($StageDir)

function Assert-SourceZeroDateEvidence([string]$EvidencePath) {
    if (!(Test-Path -LiteralPath $EvidencePath -PathType Leaf)) {
        throw 'SOURCE_EVIDENCE_MISSING: financial source fingerprint file is absent'
    }
    $evidence = Get-Content -LiteralPath $EvidencePath -Raw | ConvertFrom-Json
    if ($evidence.source_sha256 -ne $expectedSha -or [int]$evidence.source_rows -ne 120165) {
        throw 'SOURCE_EVIDENCE_MISMATCH: SHA256 or row count is not verified'
    }
    $exceptions = $evidence.mysql8_zero_date_exceptions
    if ($null -eq $exceptions -or
        (@($exceptions.PSObject.Properties.Name) -join ',') -cne 'leads' -or
        (@($exceptions.leads.PSObject.Properties.Name) -join ',') -cne 'follow_up' -or
        [int]$exceptions.leads.follow_up -ne 1) {
        throw 'ZERO_DATE_EVIDENCE_INVALID: expected exactly one leads.follow_up zero TIMESTAMP; refresh fingerprints before any import'
    }
}

function Run-Checked([string]$Command, [string[]]$Arguments) {
    & $Command @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw ("STEP_FAILED: {0} returned {1}. Check output. NO automatic cleanup or retry." -f $Command, $LASTEXITCODE)
    }
}

if (!(Test-Path -LiteralPath $source -PathType Leaf)) {
    throw 'SOURCE_MISSING: select the original private u429032065_dev.sql outside Git'
}
if ($source.StartsWith($repo + [System.IO.Path]::DirectorySeparatorChar,
        [System.StringComparison]::OrdinalIgnoreCase)) {
    throw 'PRIVATE_DUMP_IN_GIT_TREE: move the SQL source outside the repository'
}
if ($staging.StartsWith($repo + [System.IO.Path]::DirectorySeparatorChar,
        [System.StringComparison]::OrdinalIgnoreCase)) {
    throw 'PRIVATE_STAGING_IN_GIT_TREE: choose a directory outside C:\laragon\www\blueMarketing'
}
$actual = (Get-FileHash -LiteralPath $source -Algorithm SHA256).Hash.ToLowerInvariant()
if ($actual -ne $expectedSha) {
    throw ("SOURCE_SHA256_MISMATCH: expected {0}; got {1}. NO work performed." -f $expectedSha, $actual)
}
if (!(Get-Command php -ErrorAction SilentlyContinue)) {
    throw 'PHP_NOT_FOUND: use Laragon terminal or add its PHP executable to PATH'
}
$python = 'python'
$pythonPrefix = @()
if (Get-Command py -ErrorAction SilentlyContinue) {
    $python = 'py'
    $pythonPrefix = @('-3')
} elseif (!(Get-Command python -ErrorAction SilentlyContinue)) {
    throw 'PYTHON_NOT_FOUND: install Python 3 or run through py -3'
}
Push-Location $repo
try {
    if (!$Apply) {
        if (Test-Path -LiteralPath $staging) {
            throw 'STAGING_ALREADY_EXISTS: choose a NEW outside-Git directory, never overwrite private evidence'
        }
        Write-Host 'STAGE 1 — VERIFY/EXTRACT ONLY. No local database will be connected.' -ForegroundColor Cyan
        Run-Checked $python ($pythonPrefix + @(
            'tools/database_v2/v2_import_plan.py',
            '--source', $source,
            '--expected-sha256', $expectedSha,
            '--expected-rows', $expectedRows,
            '--out-dir', $staging
        ))
        Run-Checked $python ($pythonPrefix + @(
            'tools/database_v2/financial_source_fingerprints.py',
            '--source', $source,
            '--expected-sha256', $expectedSha,
            '--expected-rows', $expectedRows,
            '--out', (Join-Path $staging 'source_fingerprints.json')
        ))
        Assert-SourceZeroDateEvidence (Join-Path $staging 'source_fingerprints.json')
        # Immutable source-column evidence for independent UTC comparison.
        # This writes only SHA digests in the PRIVATE staging directory.
        Run-Checked $python ($pythonPrefix + @(
            'tools/database_v2/full_row_digests.py',
            '--source', $source,
            '--out', (Join-Path $staging 'source_full_row_digests.json')
        ))
        Write-Warning 'STRICT MYSQL EXCEPTION: one historical leads.follow_up TIMESTAMP is 0000-00-00 00:00:00.'
        Write-Warning 'No original value will be silently changed; local apply requires -PreserveLegacyZeroDate.'
        Write-Host 'STAGING_COMPLETE: no database connection, migrations or import executed.' -ForegroundColor Green
        Write-Host ('PRIVATE_STAGE: ' + $staging)
        Write-Host 'NEXT: obtain authorization and create a SEPARATE EMPTY database, e.g. bluemarketing_v2_local_utc_rehearsal.'
        Write-Host 'For Stage 2 explicitly pass -Database, matching -ConfirmEmptyDatabase and private -SourceRowDigests path.'
        Write-Warning 'Old bluemarketing_v2_local_rehearsal contains shifted TIMESTAMP instants. NEVER reimport into it.'
        return
    }

    if (!$PreserveLegacyZeroDate) {
        throw 'LEGACY_ZERO_DATE_REVIEW_REQUIRED: add explicit -PreserveLegacyZeroDate; one source TIMESTAMP is 0000-00-00 00:00:00'
    }
    # Prevent a guessed/partial target. Require fresh database and exact
    # second explicit confirmation; never create or delete a database.
    if ($Database -cnotmatch '^bluemarketing_v2_local_[a-z0-9_]+
    if (!(Test-Path -LiteralPath (Join-Path $staging 'manifest.json') -PathType Leaf) -or
        !(Test-Path -LiteralPath (Join-Path $staging 'source_fingerprints.json') -PathType Leaf)) {
        throw 'PRIVATE_STAGING_MISSING: run Stage 1 FIRST, before any MySQL operations'
    }

    if (-not $SourceRowDigests) {
        $SourceRowDigests = Join-Path $staging 'source_full_row_digests.json'
    }
    if (!(Test-Path -LiteralPath $SourceRowDigests -PathType Leaf)) {
        throw 'SOURCE_UTC_FULL_ROW_DIGESTS_REQUIRED: provide exact private source row hashes outside Git'
    }
    $privateDigests = (Resolve-Path -LiteralPath $SourceRowDigests).Path
    if ($privateDigests.StartsWith($repo + [System.IO.Path]::DirectorySeparatorChar,
            [System.StringComparison]::OrdinalIgnoreCase)) {
        throw 'SOURCE_UTC_FULL_ROW_DIGESTS_IN_GIT_REJECTED'
    }

    # Validate all private source exception evidence BEFORE the first DB
    # connection or migration; a stale {} fingerprint is NOT approved.
    Assert-SourceZeroDateEvidence (Join-Path $staging 'source_fingerprints.json')

    $env:APP_ENV = 'local'
    $env:DB_CONNECTION = 'mysql'
    $env:DB_DATABASE = $Database
    $env:DB_HOST = '127.0.0.1'
    $env:DB_URL = ''
    $env:DATABASE_URL = ''
    Write-Host 'STAGE 2 — ISOLATED LOCAL DB ONLY. No old database is selected.' -ForegroundColor Yellow
    if ($ResumePrepared) {
        Write-Host 'RECOVERY MODE: SQL staging preflight followed by READ-ONLY V2 prepared-schema verification.' -ForegroundColor Cyan
        # Stage already migrated and bank-seeded; NEVER run migrations,
        # db:seed or any schema reset again on this recovery path.
        Run-Checked 'php' @(
            'tools/database_v2/import_staged_mysql.php',
            ('--dir=' + $staging),
            ('--expected-sha256=' + $expectedSha),
            ('--expected-rows=' + $expectedRows),
            ('--source-evidence=' + (Join-Path $staging 'source_fingerprints.json')),
            '--preserve-legacy-zero-date'
        )
        Run-Checked 'php' @(
            'tools/database_v2/check_local_v2_target.php',
            ('--expected-name=' + $Database),
            ('--require-prepared'),
            ('--manifest=' + (Join-Path $staging 'manifest.json'))
        )
    } else {
        Run-Checked 'php' @(
            'tools/database_v2/check_local_v2_target.php',
            ('--expected-name=' + $Database), '--require-empty'
        )
        Run-Checked 'php' @(
            'artisan', 'migrate', '--path=database/v2_migrations',
            '--force', '--no-interaction'
        )
        Run-Checked 'php' @(
            'artisan', 'db:seed', '--class=V2LegacyBanksSeeder',
            '--force', '--no-interaction'
        )
    }
    Run-Checked 'php' @(
        'tools/database_v2/import_staged_mysql.php',
        ('--dir=' + $staging),
        ('--expected-sha256=' + $expectedSha),
        ('--expected-rows=' + $expectedRows),
        ('--source-evidence=' + (Join-Path $staging 'source_fingerprints.json')),
        '--preserve-legacy-zero-date',
        '--apply'
    )
    Run-Checked 'php' @(
        'tools/database_v2/verify_imported_mysql_financial.php',
        ('--manifest=' + (Join-Path $staging 'manifest.json')),
        ('--source-evidence=' + (Join-Path $staging 'source_fingerprints.json')),
        ('--expected-sha256=' + $expectedSha),
        ('--expected-rows=' + $expectedRows),
        '--verify-source-utc'
    )
    # A UTC import is NOT accepted just because timestamps appear correct
    # in the local MySQL timezone. Verify every original source column at UTC.
    Run-Checked 'php' @(
        'tools/database_v2/verify_mysql_full_row_digests.php',
        ('--source-evidence=' + $privateDigests), '--verify-source-utc'
    )
    Write-Host 'V2_NEW_LOCAL_IMPORT_UTC_ROW_PARITY_PASS — 57 source tables verified at +00:00.' -ForegroundColor Green
    Write-Host 'V2_LOCAL_IMPORT_AND_AGGREGATE_PARITY_PASS — source DB untouched.' -ForegroundColor Green
    Write-Host 'Do not use this isolated rehearsal database as production.'
} finally {
    Pop-Location
}

