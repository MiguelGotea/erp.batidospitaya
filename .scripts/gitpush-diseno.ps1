# Script para hacer commit y push rapido del modulo DISENO
Write-Host ">> Subiendo cambios de DISENO..." -ForegroundColor Cyan

git add modulos/diseno/
git status --short

$mensaje = "Update diseno: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
git commit -m $mensaje
git push origin main

Write-Host ">> Deploy de DISENO iniciado" -ForegroundColor Green
Write-Host ">> https://github.com/MiguelGotea/erp.batidospitaya/actions" -ForegroundColor Yellow
