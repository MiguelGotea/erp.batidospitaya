# Script para hacer commit y push rapido del modulo GERENCIA
Write-Host ">> Subiendo cambios de GERENCIA..." -ForegroundColor Cyan

git add modulos/gerencia/
git status --short

$mensaje = "Update gerencia: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
git commit -m $mensaje
git push origin main

Write-Host ">> Deploy de GERENCIA iniciado" -ForegroundColor Green
Write-Host ">> https://github.com/MiguelGotea/erp.batidospitaya/actions" -ForegroundColor Yellow
