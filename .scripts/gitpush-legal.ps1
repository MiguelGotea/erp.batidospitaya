# Script para hacer commit y push rapido del modulo LEGAL
Write-Host ">> Subiendo cambios de LEGAL..." -ForegroundColor Cyan

git add modulos/legal/
git status --short

$mensaje = "Update legal: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
git commit -m $mensaje
git push origin main

Write-Host ">> Deploy de LEGAL iniciado" -ForegroundColor Green
Write-Host ">> https://github.com/MiguelGotea/erp.batidospitaya/actions" -ForegroundColor Yellow
