param(
    [string]$MySqlHome = 'C:\laragon\bin\mysql\mysql-8.4.3-winx64',
    [string]$RedisHome = 'C:\laragon\bin\redis\redis-x64-5.0.14.1',
    [string]$MailpitBinary = 'C:\laragon\bin\mailpit\1.22.3\mailpit.exe'
)
$ErrorActionPreference = 'Stop'
$workspacePath = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$localPath = Join-Path $workspacePath '.local'
$mysqlDataPath = Join-Path $localPath 'mysql'
$redisDataPath = Join-Path $localPath 'redis'
New-Item -ItemType Directory -Path $mysqlDataPath,$redisDataPath -Force | Out-Null
if (!(Test-Path -LiteralPath (Join-Path $mysqlDataPath 'mysql'))) {
    & (Join-Path $MySqlHome 'bin\mysqld.exe') --no-defaults --initialize-insecure "--basedir=$MySqlHome" "--datadir=$mysqlDataPath"
    if ($LASTEXITCODE -ne 0) { throw 'MySQL initialization failed.' }
}
$mysqlListener = Get-NetTCPConnection -State Listen -LocalPort 3307 -ErrorAction SilentlyContinue
if (!$mysqlListener) {
    $process = Start-Process -FilePath (Join-Path $MySqlHome 'bin\mysqld.exe') -ArgumentList '--no-defaults',"--basedir=$MySqlHome","--datadir=$mysqlDataPath",'--port=3307','--bind-address=127.0.0.1','--mysqlx=OFF' -WindowStyle Hidden -PassThru
    $process.Id | Set-Content (Join-Path $localPath 'mysql.pid')
}
$redisConfig = Join-Path $localPath 'redis.conf'
if (!(Test-Path -LiteralPath $redisConfig)) {
    $randomBytes = New-Object byte[] 32
    $generator = [Security.Cryptography.RandomNumberGenerator]::Create()
    $generator.GetBytes($randomBytes)
    $generator.Dispose()
    $redisPassword = [BitConverter]::ToString($randomBytes).Replace('-','').ToLowerInvariant()
    # Secrets are written only to ignored local files.
    $redisPassword | Set-Content (Join-Path $localPath 'redis-password')
    $redisDir = $redisDataPath.Replace('\','/')
    [IO.File]::WriteAllText($redisConfig,"bind 127.0.0.1`nport 6380`nprotected-mode yes`nrequirepass $redisPassword`nappendonly yes`ndir $redisDir`n",[Text.UTF8Encoding]::new($false))
}
$redisListener = Get-NetTCPConnection -State Listen -LocalPort 6380 -ErrorAction SilentlyContinue
if (!$redisListener) {
    $process = Start-Process -FilePath (Join-Path $RedisHome 'redis-server.exe') -ArgumentList $redisConfig -WorkingDirectory $redisDataPath -WindowStyle Hidden -PassThru
    $process.Id | Set-Content (Join-Path $localPath 'redis.pid')
}
Write-Output 'Local services requested: MySQL 127.0.0.1:3307; Redis 127.0.0.1:6380.'
if (!(Get-NetTCPConnection -State Listen -LocalPort 8025 -ErrorAction SilentlyContinue)) {
    $process = Start-Process -FilePath $MailpitBinary -ArgumentList '--listen','127.0.0.1:8025','--smtp','127.0.0.1:1025' -WindowStyle Hidden -PassThru
    $process.Id | Set-Content (Join-Path $localPath 'mailpit.pid')
}
Write-Output 'Mailpit requested: SMTP 127.0.0.1:1025; inbox http://127.0.0.1:8025.'
