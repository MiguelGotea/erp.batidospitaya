# Script para forzar la actualización desde GitHub descartando cambios locales
Write-Host "Iniciando limpieza profunda y actualización forzada..." -ForegroundColor Cyan

# 1. Intentar abortar cualquier proceso de git pendiente
git merge --abort 2>$null
git rebase --abort 2>$null

# 2. Descargar lo último de la nube
Write-Host "Descargando datos de GitHub..." -ForegroundColor Yellow
git fetch origin

# 3. Forzar el estado local al estado de la nube (rama dev)
Write-Host "Sobrescribiendo archivos locales con la versión de la nube..." -ForegroundColor Yellow
git reset --hard origin/dev

# 4. Limpiar archivos y carpetas que no estén en GitHub (excepto este script)
Write-Host "Eliminando archivos no rastreados..." -ForegroundColor Yellow
git clean -fd -e .scripts/forzar_actualizacion_erp.ps1

Write-Host "`n¡Éxito! Tu código local ahora es una copia exacta de la nube (dev)." -ForegroundColor Green
Write-Host "Se han descartado todos los cambios locales en menu_lateral.php, permissions.php y demás." -ForegroundColor Green
