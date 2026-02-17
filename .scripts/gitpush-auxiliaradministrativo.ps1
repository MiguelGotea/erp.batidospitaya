# Script para hacer commit y push rapido del modulo AUXILIARADMINISTRATIVO
Write-Host ">> Subiendo cambios de AUXILIARADMINISTRATIVO..." -ForegroundColor Cyan

git add modulos/auxiliaradministrativo/
git status --short

$mensaje = "Update auxiliaradministrativo: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
git commit -m $mensaje
git push origin main

Write-Host ">> Deploy de AUXILIARADMINISTRATIVO iniciado" -ForegroundColor Green
Write-Host ">> https://github.com/MiguelGotea/erp.batidospitaya/actions" -ForegroundColor Yellow
