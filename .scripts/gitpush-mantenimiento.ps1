# Script para hacer commit y push rapido del modulo MANTENIMIENTO
Write-Host ">> Subiendo cambios de MANTENIMIENTO..." -ForegroundColor Cyan

git add modulos/mantenimiento/
git status --short

$mensaje = "Update mantenimiento: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
git commit -m $mensaje
git push origin main

Write-Host ">> Deploy de MANTENIMIENTO iniciado" -ForegroundColor Green
Write-Host ">> https://github.com/MiguelGotea/erp.batidospitaya/actions" -ForegroundColor Yellow
