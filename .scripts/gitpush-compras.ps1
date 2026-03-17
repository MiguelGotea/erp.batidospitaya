# Script para hacer commit y push rapido del modulo COMPRAS
Write-Host ">> Subiendo cambios de COMPRAS..." -ForegroundColor Cyan

git add modulos/compras/
git status --short

$mensaje = "Update compras: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
git commit -m $mensaje
git push origin main

Write-Host ">> Deploy de COMPRAS iniciado" -ForegroundColor Green
Write-Host ">> https://github.com/MiguelGotea/erp.batidospitaya/actions" -ForegroundColor Yellow
