# scripts/backup.ps1 - Patches&Motifs backup (Phase 8: backup & recovery).
# Dumps the database via mysqldump and zips it together with uploads + storage logs.
# Usage: powershell -File scripts\backup.ps1 [-Keep 14]
param(
    [int]$Keep = 14
)

$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot
$Stamp = Get-Date -Format 'yyyy-MM-dd_HHmmss'
$BackupDir = Join-Path $Root 'storage\backups'
New-Item -ItemType Directory -Force -Path $BackupDir | Out-Null

# DB credentials: root is local-dev only; production should use a dedicated dump user.
$Mysql = 'C:\xampp\mysql\bin\mysql.exe'
$Dump  = 'C:\xampp\mysql\bin\mysqldump.exe'
$Db    = 'patches_motifs'

$DumpFile = Join-Path $BackupDir "db_$Stamp.sql"
& $Dump --protocol=tcp --single-transaction --routines --triggers --default-character-set=utf8mb4 -u root $Db | Out-File -Encoding utf8 $DumpFile
if ((Get-Item $DumpFile).Length -lt 1KB) {
    throw "Database dump looks empty - aborting so we do not archive a broken backup."
}

# Zip dump + uploads + storage logs into one artifact.
$ZipFile = Join-Path $BackupDir "backup_$Stamp.zip"
Compress-Archive -Path $DumpFile, (Join-Path $Root 'public\uploads'), (Join-Path $Root 'storage\logs') -DestinationPath $ZipFile -Force
Remove-Item $DumpFile

# Retention: keep the newest $Keep zips.
Get-ChildItem $BackupDir -Filter 'backup_*.zip' |
    Sort-Object Name -Descending |
    Select-Object -Skip $Keep |
    ForEach-Object { Remove-Item $_.FullName }

Write-Host "Backup created: $ZipFile ($([math]::Round((Get-Item $ZipFile).Length / 1MB, 2)) MB)"
