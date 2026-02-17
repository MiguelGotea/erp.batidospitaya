# Script para hacer commit y push rapido del modulo SUCURSALES
Write-Host ">> Subiendo cambios de SUCURSALES..." -ForegroundColor Cyan

git add modulos/sucursales/
git status --short

$mensaje = "Update sucursales: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
git commit -m $mensaje
git push origin main

Write-Host ">> Deploy de SUCURSALES iniciado" -ForegroundColor Green
Write-Host ">> https://github.com/MiguelGotea/erp.batidospitaya/actions" -ForegroundColor Yellow
