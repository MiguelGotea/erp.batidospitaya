# Script para hacer commit y push rapido del modulo CDS
Write-Host ">> Subiendo cambios de CDS..." -ForegroundColor Cyan

git add modulos/cds/
git status --short

$mensaje = "Update cds: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
git commit -m $mensaje
git push origin main

Write-Host ">> Deploy de CDS iniciado" -ForegroundColor Green
Write-Host ">> https://github.com/MiguelGotea/erp.batidospitaya/actions" -ForegroundColor Yellow
