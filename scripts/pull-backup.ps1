# Copies the server's single verified backup (~/backups/blukios/latest) to this
# laptop, replacing the previous copy. Run weekly by Windows Task Scheduler; see
# README "Backups". A failed copy leaves the previous one in place.
$ErrorActionPreference = 'Stop'
$dest = Join-Path $env:USERPROFILE 'backups\blukios'
$tmp = "$dest.partial"

if (Test-Path $tmp) { Remove-Item $tmp -Recurse -Force }
New-Item -ItemType Directory -Path $tmp | Out-Null

scp -q -o BatchMode=yes 'fatihtesting@100.77.244.19:backups/blukios/latest/*' $tmp
if ($LASTEXITCODE -ne 0) { Remove-Item $tmp -Recurse -Force; exit 1 }
foreach ($f in 'postgres.dump', 'mongo.archive.gz', 'created_at') {
    if (-not (Test-Path (Join-Path $tmp $f))) { Remove-Item $tmp -Recurse -Force; exit 1 }
}

if (Test-Path $dest) { Remove-Item $dest -Recurse -Force }
Move-Item $tmp $dest
"$(Get-Date -Format s) copied backup from $(Get-Content (Join-Path $dest 'created_at'))" |
    Add-Content (Join-Path $env:USERPROFILE 'backups\blukios-pull.log')
