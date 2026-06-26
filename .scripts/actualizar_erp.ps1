# ==============================================================================
# Script de Actualizacion - ERP Batidos Pitaya
# ==============================================================================

# 1. Inteligencia: Moverse automaticamente a la raiz del proyecto
$expectedPath = $PSScriptRoot
Set-Location -Path $expectedPath

Write-Host '--- INICIANDO ACTUALIZACION ---' -ForegroundColor Gray

if (-not (Test-Path '.git')) {
    Write-Host 'ERROR: No se detecto una carpeta .git' -ForegroundColor Red
    exit
}

# 2. Ejecutar comandos en orden
Write-Host ''
Write-Host '1. Cambiando a rama dev...' -ForegroundColor Cyan
git checkout dev

if ($LASTEXITCODE -ne 0) {
    Write-Host 'ERROR: No se pudo cambiar a la rama dev.' -ForegroundColor Red
    exit
}

Write-Host ''
Write-Host '2. Sincronizando con el servidor...' -ForegroundColor Cyan
git fetch origin

if ($LASTEXITCODE -ne 0) {
    Write-Host 'ERROR: No se pudo conectar con GitHub.' -ForegroundColor Red
    exit
}

Write-Host ''
Write-Host '3. Aplicando cambios de main sobre dev...' -ForegroundColor Cyan
git rebase origin/main --autostash

if ($LASTEXITCODE -eq 0) {
    Write-Host ''
    Write-Host 'Sincronizacion completada con exito.' -ForegroundColor Green
} else {
    Write-Host ''
    Write-Host 'Hubo un problema o conflictos en el rebase.' -ForegroundColor Yellow
}

Write-Host ''
Write-Host 'Presiona Enter para cerrar...'
Read-Host
