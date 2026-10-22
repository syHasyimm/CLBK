$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$environmentFile = Join-Path $projectRoot '.env.docker.local'
$composeFile = Join-Path $projectRoot 'compose.local.yaml'

function New-LocalSecret {
    $bytes = New-Object byte[] 32
    $generator = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try { $generator.GetBytes($bytes) } finally { $generator.Dispose() }
    return [Convert]::ToBase64String($bytes)
}

function Get-FreeLocalPort([int] $Preferred) {
    foreach ($candidate in $Preferred..($Preferred + 50)) {
        $listener = New-Object System.Net.Sockets.TcpListener([System.Net.IPAddress]::Loopback, $candidate)
        try {
            $listener.Start()
            return $candidate
        } catch [System.Net.Sockets.SocketException] {
            continue
        } finally {
            $listener.Stop()
        }
    }
    throw "Tidak ada port lokal kosong mulai dari $Preferred."
}

function Invoke-LocalCompose {
    & docker compose --env-file $environmentFile -f $composeFile @args
    if ($LASTEXITCODE -ne 0) {
        throw 'Perintah Docker Compose gagal. Periksa pesan di atas; data tidak dihapus.'
    }
}

Push-Location $projectRoot
try {
    $null = Get-Command docker -ErrorAction Stop
    & docker info --format '{{.ServerVersion}}' | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Jalankan Docker Desktop terlebih dahulu.' }

    if (-not (Test-Path -LiteralPath $environmentFile)) {
        $existingVolume = & docker volume ls --quiet --filter 'name=^clbk-local_mysql-data$'
        if ($existingVolume) {
            throw '.env.docker.local hilang, tetapi volume database lokal sudah ada. Pulihkan file env lama agar password dan APP_KEY tetap sesuai.'
        }
        $httpPort = Get-FreeLocalPort 8088
        $settings = @(
            "CLBK_LOCAL_HTTP_PORT=$httpPort"
            "CLBK_LOCAL_APP_KEY=base64:$(New-LocalSecret)"
            "CLBK_LOCAL_DB_PASSWORD=$(New-LocalSecret)"
            "CLBK_LOCAL_MYSQL_ROOT_PASSWORD=$(New-LocalSecret)"
        ) -join "`n"
        [IO.File]::WriteAllText($environmentFile, $settings + "`n", (New-Object Text.UTF8Encoding($false)))
    }

    Invoke-LocalCompose config --quiet
    Invoke-LocalCompose up --detach --build --quiet-build --remove-orphans --wait --wait-timeout 180
    Invoke-LocalCompose exec -T --user www-data app php artisan migrate --force --no-interaction

    & docker compose --env-file $environmentFile -f $composeFile exec -T app test -f /data/storage/framework/local-initialized
    $alreadyInitialized = $LASTEXITCODE -eq 0
    if (-not $alreadyInitialized) {
        Invoke-LocalCompose exec -T --user www-data app php artisan db:seed --force --no-interaction
        Invoke-LocalCompose exec -T --user www-data app touch /data/storage/framework/local-initialized
    }

    Invoke-LocalCompose exec -T --user www-data app php artisan clbk:database-ready
    Invoke-LocalCompose exec -T --user www-data app php artisan up
    Invoke-LocalCompose ps
    $savedSettings = Get-Content -LiteralPath $environmentFile
    $httpPort = ($savedSettings | Where-Object { $_ -match '^CLBK_LOCAL_HTTP_PORT=' }) -replace '^CLBK_LOCAL_HTTP_PORT=', ''
    & (Join-Path $PSScriptRoot 'check-local-docker-assets.ps1') -BaseUrl "http://localhost:$httpPort"
    Write-Host "Aplikasi: http://localhost:$httpPort/login"
    Write-Host 'Akun demo lokal: lihat docs/docker-local.md. APP_KEY dan password MySQL disimpan di .env.docker.local.'
} finally {
    Pop-Location
}
