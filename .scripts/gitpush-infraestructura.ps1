# Script para hacer commit y push rapido del modulo INFRAESTRUCTURA
Write-Host ">> Subiendo cambios de INFRAESTRUCTURA..." -ForegroundColor Cyan

git add modulos/infraestructura/
git status --short

$mensaje = "Update infraestructura: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
git commit -m $mensaje
git push origin main

Write-Host ">> Deploy de INFRAESTRUCTURA iniciado" -ForegroundColor Green
Write-Host ">> https://github.com/MiguelGotea/erp.batidospitaya/actions" -ForegroundColor Yellow
