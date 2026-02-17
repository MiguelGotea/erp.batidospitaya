# Script para hacer commit y push rapido del modulo EXPERIENCIADIGITAL
Write-Host ">> Subiendo cambios de EXPERIENCIADIGITAL..." -ForegroundColor Cyan

git add modulos/experienciadigital/
git status --short

$mensaje = "Update experienciadigital: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
git commit -m $mensaje
git push origin main

Write-Host ">> Deploy de EXPERIENCIADIGITAL iniciado" -ForegroundColor Green
Write-Host ">> https://github.com/MiguelGotea/erp.batidospitaya/actions" -ForegroundColor Yellow
