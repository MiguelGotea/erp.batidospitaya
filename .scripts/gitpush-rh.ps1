# Script para hacer commit y push rapido del modulo RH
Write-Host ">> Subiendo cambios de RH..." -ForegroundColor Cyan

git add modulos/rh/
git status --short

$mensaje = "Update rh: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
git commit -m $mensaje
git push origin main

Write-Host ">> Deploy de RH iniciado" -ForegroundColor Green
Write-Host ">> https://github.com/MiguelGotea/erp.batidospitaya/actions" -ForegroundColor Yellow
