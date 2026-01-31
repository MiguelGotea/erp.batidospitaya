# Script para hacer commit y push rapido del modulo DESARROLLO
Write-Host ">> Subiendo cambios de DESARROLLO..." -ForegroundColor Cyan

git add modulos/desarrollo/
git status --short

$mensaje = "Update desarrollo: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
git commit -m $mensaje
git push origin main

Write-Host ">> Deploy de DESARROLLO iniciado" -ForegroundColor Green
Write-Host ">> https://github.com/MiguelGotea/erp.batidospitaya/actions" -ForegroundColor Yellow
