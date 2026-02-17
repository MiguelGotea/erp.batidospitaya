# Script para hacer commit y push rapido del modulo ATENCIONCLIENTE
Write-Host ">> Subiendo cambios de ATENCIONCLIENTE..." -ForegroundColor Cyan

git add modulos/atencioncliente/
git status --short

$mensaje = "Update atencioncliente: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
git commit -m $mensaje
git push origin main

Write-Host ">> Deploy de ATENCIONCLIENTE iniciado" -ForegroundColor Green
Write-Host ">> https://github.com/MiguelGotea/erp.batidospitaya/actions" -ForegroundColor Yellow
