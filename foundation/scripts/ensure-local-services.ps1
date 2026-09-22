<#
    Ensures the local MySQL (3307) and Redis (6380) are up, for the VS Code task that runs on folder
    open. Safe to run any number of times: a service already running is left alone, a port held by a
    process that is not ours is reported and never fought over, and every start is confirmed by
    waiting for the port instead of assumed.

    Starting is delegated to start-local.ps1, unchanged. Problems are printed as
    "<script path>: error: <message>" so the task's problem matcher can surface them in the
    Problems panel; the exit code is 1 whenever something needs attention.
#>
param(
    [string]$MySqlHome = 'C:\laragon\bin\mysql\mysql-8.4.3-winx64',
    [string]$RedisHome = 'C:\laragon\bin\redis\redis-x64-5.0.14.1',
    [int]$StartTimeoutSeconds = 40
)
$ErrorActionPreference = 'Stop'
# Windows PowerShell writes the OEM code page by default; the task terminal expects UTF-8.
[Console]::OutputEncoding = [Text.UTF8Encoding]::new($false)

$workspacePath = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$localPath = Join-Path $workspacePath '.local'
$services = @(
    [pscustomobject]@{ Name = 'MySQL'; Port = 3307; Exe = (Join-Path $MySqlHome 'bin\mysqld.exe'); Marker = (Join-Path $localPath 'mysql') },
    [pscustomobject]@{ Name = 'Redis'; Port = 6380; Exe = (Join-Path $RedisHome 'redis-server.exe'); Marker = (Join-Path $localPath 'redis.conf') }
)
$script:failed = $false

function Report-Problem([string]$message) {
    $script:failed = $true
    Write-Output "${PSCommandPath}: error: $message"
}

# Who listens on the port, if anyone: process id, executable and command line.
function Get-PortOwner([int]$port) {
    $listener = Get-NetTCPConnection -State Listen -LocalPort $port -ErrorAction SilentlyContinue | Select-Object -First 1
    if (!$listener) { return $null }
    $process = Get-CimInstance Win32_Process -Filter "ProcessId = $($listener.OwningProcess)" -ErrorAction SilentlyContinue
    [pscustomobject]@{
        Id = $listener.OwningProcess
        Name = if ($process) { $process.Name } else { 'desconocido' }
        Path = if ($process) { [string]$process.ExecutablePath } else { '' }
        CommandLine = if ($process) { [string]$process.CommandLine } else { '' }
    }
}

# Ours means the expected binary running on this project's data: Laragon's own MySQL shares the
# binary, so the executable alone is not enough.
function Test-Ours($owner, $service) {
    $owner -and $owner.Path -and
        ($owner.Path -ieq $service.Exe) -and
        ($owner.CommandLine.ToLowerInvariant().Contains($service.Marker.ToLowerInvariant()))
}

$toStart = @()
foreach ($service in $services) {
    $owner = Get-PortOwner $service.Port
    if (!$owner) {
        $toStart += $service
    } elseif (Test-Ours $owner $service) {
        Write-Output "$($service.Name) ya estaba corriendo en 127.0.0.1:$($service.Port) (PID $($owner.Id))."
    } else {
        Report-Problem "$($service.Name) no se arrancó: el puerto $($service.Port) está ocupado por otro proceso, $($owner.Name) (PID $($owner.Id)) $($owner.Path). Cerrá ese proceso o liberá el puerto y volvé a correr la tarea."
    }
}

if ($toStart.Count -gt 0) {
    Write-Output "Arrancando: $(($toStart | ForEach-Object Name) -join ', ')..."
    try {
        # start-local.ps1 checks each port again and only starts what is missing.
        & (Join-Path $PSScriptRoot 'start-local.ps1') | Out-Null
    } catch {
        Report-Problem "start-local.ps1 falló: $($_.Exception.Message)"
    }

    $deadline = (Get-Date).AddSeconds($StartTimeoutSeconds)
    foreach ($service in $toStart) {
        do {
            $owner = Get-PortOwner $service.Port
            if ($owner) { break }
            Start-Sleep -Milliseconds 500
        } while ((Get-Date) -lt $deadline)

        if (Test-Ours $owner $service) {
            Write-Output "$($service.Name) arrancó en 127.0.0.1:$($service.Port) (PID $($owner.Id))."
        } elseif ($owner) {
            Report-Problem "$($service.Name) no se arrancó: mientras se iniciaba, el puerto $($service.Port) lo tomó otro proceso, $($owner.Name) (PID $($owner.Id))."
        } else {
            $hint = if ($service.Name -eq 'MySQL') { " Revisá el registro de errores en $(Join-Path $localPath 'mysql')\*.err." } else { '' }
            Report-Problem "$($service.Name) no quedó escuchando en el puerto $($service.Port) después de $StartTimeoutSeconds s.$hint"
        }
    }
}

if ($script:failed) {
    Write-Output 'Hay servicios locales que requieren atención: ver el panel Problemas.'
    exit 1
}
Write-Output 'Servicios locales listos: MySQL 127.0.0.1:3307, Redis 127.0.0.1:6380.'
exit 0
