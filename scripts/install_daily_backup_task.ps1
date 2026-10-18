param(
    [string]$PhpPath = 'C:\xampp\php\php.exe'
)

$ErrorActionPreference = 'Stop'
$ProjectPath = Split-Path -Parent $PSScriptRoot
$BackupScript = Join-Path $PSScriptRoot 'daily_backup.php'

if (-not (Test-Path -LiteralPath $PhpPath -PathType Leaf)) {
    throw "PHP executable not found at '$PhpPath'. Pass the correct path with -PhpPath."
}
if (-not (Test-Path -LiteralPath $BackupScript -PathType Leaf)) {
    throw "Daily backup script not found at '$BackupScript'."
}

$Action = New-ScheduledTaskAction `
    -Execute $PhpPath `
    -Argument ('"{0}"' -f $BackupScript) `
    -WorkingDirectory $ProjectPath
$Trigger = New-ScheduledTaskTrigger -Daily -At '1:00 AM'
$Settings = New-ScheduledTaskSettingsSet `
    -StartWhenAvailable `
    -RestartCount 3 `
    -RestartInterval (New-TimeSpan -Minutes 15) `
    -MultipleInstances IgnoreNew

Register-ScheduledTask `
    -TaskName '707 GYM Daily Database Backup' `
    -Action $Action `
    -Trigger $Trigger `
    -Settings $Settings `
    -Description 'Creates a dated 707 GYM SQL database backup every day at 1:00 AM.' `
    -Force | Out-Null

Write-Host 'Daily backup task installed. It runs at 1:00 AM and retries failures up to three times.'
Write-Host 'Confirm MySQL is running and the scheduled-task user can connect to the configured database.'
