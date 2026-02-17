# Script para hacer commit y push rapido del modulo SISTEMAS
Write-Host ">> Subiendo cambios de SISTEMAS..." -ForegroundColor Cyan

git add modulos/sistemas/
git status --short

$mensaje = "Update sistemas: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
git commit -m $mensaje
git push origin main

Write-Host ">> Deploy de SISTEMAS iniciado" -ForegroundColor Green
Write-Host ">> https://github.com/MiguelGotea/erp.batidospitaya/actions" -ForegroundColor Yellow
