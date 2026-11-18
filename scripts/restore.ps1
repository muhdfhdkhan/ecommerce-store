# scripts/restore.ps1 — restore a database dump created by backup.ps1.
# Usage: powershell -File scripts\restore.ps1 -DumpFile storage\backups\db_2026-11-18_090000.sql
param(
    [Parameter(Mandatory = $true)][string]$DumpFile
)

$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot
$Mysql = 'C:\xampp\mysql\bin\mysql.exe'
$Db = 'patches_motifs'

if (-not (Test-Path $DumpFile)) {
    $DumpFile = Join-Path $Root $DumpFile
}
if (-not (Test-Path $DumpFile)) {
    throw "Dump file not found: $DumpFile"
}

Write-Host "Restoring $DumpFile into $Db ..."
Get-Content $DumpFile | & $Mysql --protocol=tcp -u root $Db
Write-Host "Restore complete. Verify: SELECT COUNT(*) FROM orders;"
