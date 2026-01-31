# Script para hacer commit y push rapido del modulo TECNICODESARROLLOHUMANO
Write-Host ">> Subiendo cambios de TECNICODESARROLLOHUMANO..." -ForegroundColor Cyan

git add modulos/tecnicodesarrollohumano/
git status --short

$mensaje = "Update tecnicodesarrollohumano: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
git commit -m $mensaje
git push origin main

Write-Host ">> Deploy de TECNICODESARROLLOHUMANO iniciado" -ForegroundColor Green
Write-Host ">> https://github.com/MiguelGotea/erp.batidospitaya/actions" -ForegroundColor Yellow
