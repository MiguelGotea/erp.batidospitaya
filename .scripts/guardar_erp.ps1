# ==============================================================================
# Script de Guardado - ERP Batidos Pitaya
# ==============================================================================

# 1. Inteligencia: Moverse automaticamente a la raiz del proyecto
$expectedPath = $PSScriptRoot
Set-Location -Path $expectedPath

Write-Host '--- INICIANDO PROCESO DE GUARDADO ---' -ForegroundColor Gray

if (-not (Test-Path '.git')) {
    Write-Host 'ERROR: No se detecto una carpeta .git' -ForegroundColor Red
    exit
}

# 2. Seguridad: Verificar rama dev
$currentBranch = git branch --show-current
if ($currentBranch -ne 'dev') {
    Write-Host "ERROR: Estas en la rama '$currentBranch'. Cambia a dev primero." -ForegroundColor Red
    exit
}

# 3. Sincronizar (Igual que actualizar_erp.ps1)
Write-Host ''
Write-Host '1. Trayendo cambios de la nube (fetch + rebase)...' -ForegroundColor Cyan
git fetch origin
git rebase origin/main --autostash

if ($LASTEXITCODE -ne 0) {
    Write-Host 'ERROR: Problema al sincronizar. Revisa conflictos.' -ForegroundColor Red
    exit
}

# 4. Verificar si hay cambios locales para guardar
$cambios = git status --porcelain
if (-not $cambios) {
    Write-Host ''
    Write-Host 'INFO: No hay cambios locales para guardar. Todo esta al dia.' -ForegroundColor Green
} else {
    Write-Host ''
    Write-Host '2. Preparando tus cambios locales...' -ForegroundColor Cyan
    
    # Mensaje de commit
    Write-Host 'Escribe que cambiaste (breve):' -ForegroundColor Yellow
    $mensaje = Read-Host '>'

    if ([string]::IsNullOrWhiteSpace($mensaje)) {
        $mensaje = "Actualizacion automatica: $(Get-Date -Format 'yyyy-MM-dd HH:mm')"
    }

    # Agregar archivos (excluyendo .sql por seguridad)
    git add .
    git reset HEAD -- '*.sql' 2>$null

    Write-Host 'Guardando commit local...' -ForegroundColor Gray
    git commit -m "$mensaje"

    Write-Host 'Subiendo cambios a GitHub (dev)...' -ForegroundColor Gray
    git push origin dev

    # 5. Intentar crear/actualizar Pull Request si gh cli esta instalado
    Write-Host ''
    Write-Host '3. Verificando Pull Request en GitHub...' -ForegroundColor Cyan
    $ghPath = Get-Command gh -ErrorAction SilentlyContinue
    if ($ghPath) {
        $prExists = gh pr list --head dev --base main --json number --jq '.[0].number'
        if ($prExists) {
            Write-Host "Pull Request #$prExists actualizado." -ForegroundColor Green
        } else {
            gh pr create --base main --head dev --title "$mensaje" --body "Enviado automaticamente via script."
            Write-Host 'Pull Request creado con exito.' -ForegroundColor Green
        }
    } else {
        Write-Host 'Nota: Instala GitHub CLI (gh) para crear PRs automaticamente.' -ForegroundColor Gray
    }
}

Write-Host ''
Write-Host 'Presiona Enter para cerrar...'
Read-Host
