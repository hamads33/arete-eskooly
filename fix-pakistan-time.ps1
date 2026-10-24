$ErrorActionPreference = 'Stop'
Set-TimeZone -Id 'Pakistan Standard Time'
$response = Invoke-WebRequest -Uri 'https://www.microsoft.com' -Method Head -UseBasicParsing
$serverUtc = [DateTimeOffset]::Parse($response.Headers['Date']).UtcDateTime
$pakistanLocal = [DateTime]::SpecifyKind($serverUtc.AddHours(5), [DateTimeKind]::Unspecified)
Set-Date -Date $pakistanLocal
Write-Host "Current Pakistan time: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss zzz')"
Write-Host 'You may close this window.'
Read-Host 'Press Enter to close'
