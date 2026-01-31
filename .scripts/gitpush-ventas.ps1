# Script para hacer commit y push rapido del modulo VENTAS
Write-Host ">> Subiendo cambios de VENTAS..." -ForegroundColor Cyan

git add modulos/ventas/
git status --short

$mensaje = "Update ventas: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
git commit -m $mensaje
git push origin main

Write-Host ">> Deploy de VENTAS iniciado" -ForegroundColor Green
Write-Host ">> https://github.com/MiguelGotea/erp.batidospitaya/actions" -ForegroundColor Yellow
