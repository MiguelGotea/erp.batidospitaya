# Auto-navegar a la raíz (GPS Interno)
Set-Location $PSScriptRoot
Set-Location ..

# Verificar archivos vacíos antes de agregar
$cambios = git status --porcelain
if ($cambios) {
    $emptyFiles = @()
    foreach ($line in ($cambios -split "`r?`n")) {
        if ([string]::IsNullOrWhiteSpace($line)) { continue }
        $file = $line.Substring(3).Trim()
        if (Test-Path $file) {
            $item = Get-Item $file
            if ($item.PSIsContainer -ne $true -and $item.Length -eq 0) {
                $emptyFiles += $file
            }
        }
    }

    if ($emptyFiles.Count -gt 0) {
        Write-Host ""
        Write-Host "⚠️ ADVERTENCIA: Se detectaron archivos VACÍOS (0 bytes) que están modificados o son nuevos:" -ForegroundColor Yellow
        foreach ($ef in $emptyFiles) {
            Write-Host "   -> $ef" -ForegroundColor Red
        }
        Write-Host ""
        $confirmacion = Read-Host "¿Estás seguro de que deseas continuar con el push de estos archivos vacíos? (S/N)"
        if ($confirmacion -notmatch "^[Ss]$") {
            Write-Host "Operación cancelada. Por favor revisa tus archivos." -ForegroundColor Yellow
            exit
        }
    }
}

# Script Tanque v7 (Anti-Choque)
git add .
$msg = "Human Push (Global) $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
git commit -m "$msg" 2>$null

Write-Host "🚀 Intentando sincronizar y subir cambios..." -ForegroundColor Cyan
git pull origin main --rebase

if ($LASTEXITCODE -ne 0) {
    Write-Host "⚠️ Conflicto con el Bot detectado. Aplicando reparación de Hierro..." -ForegroundColor Yellow
    git rebase --abort 2>$null
    git pull origin main --no-rebase -X ours
    git add .
    git commit -m "$msg (Conflict Resolved)" 2>$null
}

git push origin HEAD
Write-Host "✅ ¡Subida completada con éxito!" -ForegroundColor Green
