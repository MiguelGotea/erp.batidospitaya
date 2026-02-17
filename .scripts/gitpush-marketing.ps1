# Script para hacer commit y push rapido del modulo MARKETING
Write-Host ">> Subiendo cambios de MARKETING..." -ForegroundColor Cyan

git add modulos/marketing/
git status --short

$mensaje = "Update marketing: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
git commit -m $mensaje
git push origin main

Write-Host ">> Deploy de MARKETING iniciado" -ForegroundColor Green
Write-Host ">> https://github.com/MiguelGotea/erp.batidospitaya/actions" -ForegroundColor Yellow
