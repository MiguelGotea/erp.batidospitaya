# Script para hacer commit y push rapido del modulo PRODUCCION
Write-Host ">> Subiendo cambios de PRODUCCION..." -ForegroundColor Cyan

git add modulos/produccion/
git status --short

$mensaje = "Update produccion: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
git commit -m $mensaje
git push origin main

Write-Host ">> Deploy de PRODUCCION iniciado" -ForegroundColor Green
Write-Host ">> https://github.com/MiguelGotea/erp.batidospitaya/actions" -ForegroundColor Yellow
