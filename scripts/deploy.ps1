<#
.SYNOPSIS
  Nasadí aktuálny stav pluginu na GitHub (zápis cez PowerShell).
.DESCRIPTION
  Pridá zmeny, vytvorí commit a pushne ho na origin/main.
.EXAMPLE
  .\scripts\deploy.ps1 -Message "Pridana GEO targeting sekcia"
#>
[CmdletBinding()]
param(
  [Parameter(Mandatory = $true)]
  [string]$Message
)

$ErrorActionPreference = 'Stop'

Push-Location (Join-Path $PSScriptRoot '..')

try {
  git add -A

  git diff --cached --quiet
  if ($LASTEXITCODE -eq 0) {
    Write-Host 'Nie su ziadne zmeny na commitnutie.' -ForegroundColor Yellow
    return
  }

  git commit -m $Message
  if ($LASTEXITCODE -ne 0) { throw 'Commit zlyhal.' }

  git push origin main
  if ($LASTEXITCODE -ne 0) { throw 'Push zlyhal.' }

  Write-Host "Hotovo: $Message" -ForegroundColor Green
}
finally {
  Pop-Location
}