param([string] $BaseUrl)

$ErrorActionPreference = 'Stop'
if (-not $BaseUrl) {
    $environmentFile = Join-Path (Split-Path -Parent $PSScriptRoot) '.env.docker.local'
    $portLine = Get-Content -LiteralPath $environmentFile | Where-Object { $_ -match '^CLBK_LOCAL_HTTP_PORT=' }
    $port = $portLine -replace '^CLBK_LOCAL_HTTP_PORT=', ''
    $BaseUrl = "http://localhost:$port"
}
$expectedOrigin = ([Uri] $BaseUrl).GetLeftPart([UriPartial]::Authority)
$page = Invoke-WebRequest -UseBasicParsing -Uri "$expectedOrigin/login" -TimeoutSec 20
$matches = [regex]::Matches($page.Content, '(?:src|href)="(?<url>https?://[^\"]+/build/[^\"]+)"')
if ($matches.Count -eq 0) {
    throw 'Halaman login tidak mengeluarkan URL aset hasil build.'
}
$assetUrls = $matches | ForEach-Object { [System.Net.WebUtility]::HtmlDecode($_.Groups['url'].Value) } | Sort-Object -Unique
foreach ($url in $assetUrls) {
    if (([Uri] $url).GetLeftPart([UriPartial]::Authority) -ne $expectedOrigin) {
        throw "Origin aset tidak sesuai: $url (seharusnya $expectedOrigin). Rebuild container aplikasi."
    }
    $asset = Invoke-WebRequest -UseBasicParsing -Uri $url -TimeoutSec 20
    if ($asset.StatusCode -ne 200) { throw "Aset gagal dimuat: $url" }
}
$routeMatch = [regex]::Match($page.Content, 'const Ziggy=(?<json>\{.*?\});')
if (-not $routeMatch.Success) { throw 'Konfigurasi route Ziggy tidak ditemukan.' }
$routes = $routeMatch.Groups['json'].Value | ConvertFrom-Json
if ($routes.url -ne $expectedOrigin) {
    throw "Origin route Ziggy tidak sesuai: $($routes.url) (seharusnya $expectedOrigin)."
}
Write-Host "Aset JS/CSS dan route memakai origin $expectedOrigin; seluruh aset HTTP 200."
