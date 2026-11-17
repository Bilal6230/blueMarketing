param(
    [Parameter(Mandatory=$true)]
    [string]$BundleFolder,
    [string]$ProjectRoot = 'C:\laragon\www\blueMarketing'
)
$ErrorActionPreference = 'Stop'
$root = (Resolve-Path $ProjectRoot).Path
$bundle = (Resolve-Path $BundleFolder).Path
$source = Join-Path $bundle 'database\migrations'
$dest = Join-Path $root 'database\migrations'
$archive = Join-Path $root 'database\old_migration'
if (!(Test-Path (Join-Path $root 'artisan'))) { throw 'Invalid Laravel project root' }
if (!(Test-Path $source)) { throw 'Extract the full one-table-migrations bundle first' }
if (Test-Path $archive) { throw 'old_migration already exists; refusing to overwrite' }
if (!(Test-Path $dest)) { throw 'database/migrations is missing' }
$files = @(Get-ChildItem -LiteralPath $source -File -Filter '*.php')
if ($files.Count -ne 148) { throw "Expected 148 migration files, found $($files.Count)" }
$old = @(Get-ChildItem -LiteralPath $dest -File -Filter '*.php')
if ($old.Count -lt 100) { throw "Expected 100+ historical migrations, found $($old.Count)" }
Move-Item -LiteralPath $dest -Destination $archive
New-Item -ItemType Directory -Path $dest | Out-Null
Copy-Item (Join-Path $source '*.php') -Destination $dest
Write-Host "Archived $($old.Count) old migration files to database\old_migration" -ForegroundColor Green
Write-Host "Installed 146 individual CREATE migrations + safety gate + FK finisher." -ForegroundColor Green
Write-Host "Point DB_DATABASE to a NEW EMPTY database before php artisan migrate." -ForegroundColor Yellow
