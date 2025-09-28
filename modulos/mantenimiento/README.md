# 📋 **DOCUMENTACIÓN COMPLETA - Sistema de Sincronización Automática**

## **🔧Caso Mantenimiento:**
### **🔍 0. Herramientas**
```bash
# cron job
- /bin/bash /home/u839374897/sync-to-github.sh\

# Manualmente desde hostinger terminal  ejecutar: /bin/bash ~/ , revisar: nano ~/
- sync-to-github.sh 
- deploy-erp.sh

# github action
- .github/workflows/deploy-mantenimiento.yml 
```

### **🔍 1. Exploración inicial del servidor**
```bash
# Verificar directorio SSH
ls -la ~/.ssh/

# Resultado: Encontramos 2 pares de claves
# - id_rsa/id_rsa.pub (RSA, Sep 1)
# - erp-batidos-deploy/erp-batidos-deploy.pub (Ed25519, Sep 28)
```

### **🔑 2. Identificación de claves disponibles**
```bash
# Ver claves públicas
ls -la ~/.ssh/*.pub

# Analizar tipo y detalles de cada clave
ssh-keygen -l -f ~/.ssh/id_rsa.pub
ssh-keygen -l -f ~/.ssh/erp-batidos-deploy.pub

# Resultado:
# - id_rsa: RSA 3072 bits
# - erp-batidos-deploy: Ed25519 256 bits (más segura)
```

### **📱 3. Ver contenido de claves públicas**
```bash
# Clave RSA
cat ~/.ssh/id_rsa.pub
# ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABgQDX7DgzolhqZ... u839374897@us-phx-web1059.main-hosting.eu

# Clave Ed25519 (recomendada)
cat ~/.ssh/erp-batidos-deploy.pub  
# ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIAHWWJu9du9uzZKDP5ChDrpCef8QB4uvJMXZ58SkH2XZ erp-deploy@batidospitaya.com
```

### **🚨 4. Problema detectado: authorized_keys faltante**
```bash
# Verificar acceso SSH entrante
cat ~/.ssh/authorized_keys
# Error: No such file or directory

# PROBLEMA: Sin este archivo, GitHub no puede conectarse
```

### **🔧 5. Crear authorized_keys**
```bash
# Agregar clave pública para permitir acceso externo
echo "ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIAHWWJu9du9uzZKDP5ChDrpCef8QB4uvJMXZ58SkH2XZ erp-deploy@batidospitaya.com" > ~/.ssh/authorized_keys

# Permisos de seguridad
chmod 600 ~/.ssh/authorized_keys
```

### **✅ 6. Elementos clave del éxito**
```bash
- ✅ Elegir clave Ed25519 (más segura que RSA)
- ✅ Crear `authorized_keys` con la clave pública
- ✅ Usar la ruta completa de dominios
- ✅ Especificar puerto 65002 en todos los comandos
- ✅ Permisos correctos en archivos SSH
```

### **✅ 7. Claves Privadas github**
```bash
- HOSTINGER_SSH_KEY: cat ~/.ssh/erp-batidos-deploy
- HOSTINGER_USER: u838374897
- HOSTINGER_HOST: 145.223.105.42 
- HOSTINGER_PATH: /home/u839374897/domains/erp.batidospitaya.com/public_html
```

## 🏗️ **ARQUITECTURA DEL SISTEMA NUEVO**

### **Diagrama de Flujo**

## Opción 2: Texto con formato ASCII

```markdown
┌───────────────────┐    ┌────────────────────┐
│ Editor en         │    │ Sync automático    │
│ Hostinger         │───▶│ cada 30min        │──────┐
└───────────────────┘    └────────────────────┘     │
                                                    │
┌───────────────────┐                               │ 
│ Editor en         │    ┌───────────────────┐      │
│ VS Code           │───▶│ Push a GitHub     │──────┼──┐
└───────────────────┘    └───────────────────┘      │  │
                                                    │  │
┌───────────────────┐                               │  │
│ Editor en         │    ┌───────────────────┐      │  │
│ GitHub Web        │───▶│ Commit directo    │──────┘  │
└───────────────────┘    └───────────────────┘         │
                                                       │
                                ┌──────────────────────┘
                                │
                                ▼
                        ┌───────────────────┐
                        │ Repositorio       │
                        │ GitHub            │
                        └───────────────────┘
                                │
                                ▼
                        ┌───────────────────┐
                        │ GitHub Actions    │
                        └───────────────────┘
                                │
                                ▼
                        ┌─────────────────────────────┐
                        │ Deploy automático           │
                        │ a Hostinger                 │
                        └─────────────────────────────┘

### Componentes Técnicos
- **Hostinger**: Servidor de producción con acceso SSH
- **GitHub**: Repositorio central y CI/CD
- **GitHub Actions**: Pipeline de deploy automático
- **Scripts Bash**: Automatización de sincronización
- **SSH Keys**: Autenticación segura
---

## 🔄 **PROCEDIMIENTO PARA REPLICAR EN OTRA CUENTA/CARPETA**

### **📁 ESTRUCTURA DE CONFIGURACIÓN**

#### **1. Datos del Nuevo Proyecto**
```bash
# DATOS BASE (MODIFICAR SEGÚN NUEVO PROYECTO)
NUEVO_DOMINIO="nuevo-dominio.com"
NUEVA_RUTA_BASE="/home/usuario/domains/nuevo-dominio.com/public_html"
NUEVA_CARPETA_EDITABLE="/home/usuario/domains/nuevo-dominio.com/public_html/carpeta-permitida"
NUEVO_REPO_GITHUB="https://github.com/nuevo-usuario/nuevo-repo.git"
USUARIO_HOSTINGER="usuario"
```

#### **2. Script de Configuración Automática**
```bash
#!/bin/bash
# setup-new-sync.sh - Configura nuevo sistema de sincronización

# =============================================================================
# CONFIGURACIÓN - MODIFICAR ESTOS VALORES
# =============================================================================
DOMINIO="nuevo-dominio.com"
RUTA_BASE="/home/usuario/domains/nuevo-dominio.com/public_html"
CARPETA_EDITABLE="/home/usuario/domains/nuevo-dominio.com/public_html/carpeta-permitida"
REPO_GITHUB="https://github.com/nuevo-usuario/nuevo-repo.git"
USUARIO_HOSTINGER="usuario"
USUARIO_GITHUB="nuevo-usuario"

# =============================================================================
# CONFIGURACIÓN SSH
# =============================================================================
echo "🔐 Configurando SSH..."
ssh-keygen -t ed25519 -f ~/.ssh/${DOMINIO}-deploy -C "deploy@${DOMINIO}" -N ""

echo "=== CLAVE PÚBLICA PARA GITHUB ==="
cat ~/.ssh/${DOMINIO}-deploy.pub
echo "=== FIN CLAVE ==="

# Agregar a configuración SSH
cat >> ~/.ssh/config << EOF

Host ${DOMINIO}-github
    HostName github.com
    User git
    IdentityFile ~/.ssh/${DOMINIO}-deploy
    IdentitiesOnly yes
EOF

# =============================================================================
# SCRIPTS DE SINCRONIZACIÓN
# =============================================================================

# Script Sync Hostinger → GitHub
cat > ~/sync-${DOMINIO}.sh << 'EOF'
#!/bin/bash
# sync-${DOMINIO}.sh - Sincronización Hostinger → GitHub

mkdir -p /home/${USUARIO_HOSTINGER}/sync-logs
LOG_FILE="/home/${USUARIO_HOSTINGER}/sync-logs/$(date +\%Y-\%m-\%d).log"
REPO_PATH="${RUTA_BASE}"

echo "=== SYNC ${DOMINIO}: $(date) ===" >> $LOG_FILE

cd $REPO_PATH

if [ ! -d ".git" ]; then
    echo "❌ ERROR: No es un repositorio Git" >> $LOG_FILE
    exit 1
fi

# Actualizar desde GitHub primero
git fetch origin
if [ $(git rev-parse HEAD) != $(git rev-parse origin/main) ]; then
    echo "📥 Hay cambios en GitHub, haciendo merge..." >> $LOG_FILE
    git merge origin/main --no-edit
    
    if [ $? -ne 0 ]; then
        echo "⚠️ Conflictos detectados, resolviendo..." >> $LOG_FILE
        git checkout --ours ${CARPETA_EDITABLE#$RUTA_BASE/}/
        git add ${CARPETA_EDITABLE#$RUTA_BASE/}/
        git commit -m "Resolución automática de conflictos"
    fi
fi

# Verificar cambios locales
CHANGES=$(git status ${CARPETA_EDITABLE#$RUTA_BASE/}/ --porcelain)
if [ -n "$CHANGES" ]; then
    echo "📤 Cambios locales detectados" >> $LOG_FILE
    git add ${CARPETA_EDITABLE#$RUTA_BASE/}/
    git commit -m "Auto-sync: $(date +"%Y-%m-%d %H:%M:%S")"
    
    if git push origin main; then
        echo "✅ Sync exitoso" >> $LOG_FILE
    else
        git push origin main --force
        echo "⚠️ Push forzado completado" >> $LOG_FILE
    fi
else
    echo "📭 No hay cambios locales" >> $LOG_FILE
fi
EOF

# Script Deploy GitHub → Hostinger
cat > ~/deploy-${DOMINIO}.sh << 'EOF'
#!/bin/bash
# deploy-${DOMINIO}.sh - Deploy GitHub → Hostinger

mkdir -p /home/${USUARIO_HOSTINGER}/deploy-logs
LOG_FILE="/home/${USUARIO_HOSTINGER}/deploy-logs/$(date +\%Y-\%m-\%d).log"
DEPLOY_PATH="${RUTA_BASE}"

echo "=== DEPLOY ${DOMINIO}: $(date) ===" >> $LOG_FILE

cd $DEPLOY_PATH

if [ ! -d ".git" ]; then
    echo "❌ ERROR: No es un repositorio Git" >> $LOG_FILE
    exit 1
fi

git fetch origin
if [ $(git rev-parse HEAD) != $(git rev-parse origin/main) ]; then
    echo "📥 Cambios detectados, actualizando..." >> $LOG_FILE
    git reset --hard origin/main
    
    chmod -R 755 ${CARPETA_EDITABLE#$RUTA_BASE/}/
    find ${CARPETA_EDITABLE#$RUTA_BASE/}/ -type f -exec chmod 644 {} \;
    
    echo "✅ Deploy completado" >> $LOG_FILE
else
    echo "📭 No hay cambios para deploy" >> $LOG_FILE
fi
EOF

chmod +x ~/sync-${DOMINIO}.sh ~/deploy-${DOMINIO}.sh

echo "✅ Configuración completada para ${DOMINIO}"
echo "📋 Pasos manuales restantes:"
echo "1. Agregar clave pública a GitHub"
echo "2. Configurar secrets en GitHub"
echo "3. Crear workflow GitHub Actions"
echo "4. Configurar cron job"
```

---

## 🧪 **MANUAL DE PRUEBAS Y VERIFICACIONES**

### **🔧 PRUEBAS MANUALES DESDE SSH HOSTINGER**

#### **1. Prueba de Sincronización (Hostinger → GitHub)**
```bash
# Conectar a Hostinger
ssh ${USUARIO_HOSTINGER}@${DOMINIO}

# Ejecutar sync manual
/bin/bash ~/sync-${DOMINIO}.sh

# Verificar logs
tail -f /home/${USUARIO_HOSTINGER}/sync-logs/$(date +\%Y-\%m-\%d).log

# Verificar estado Git
cd ${RUTA_BASE}
git status
git log --oneline -3
```

#### **2. Prueba de Deploy (GitHub → Hostinger)**
```bash
# Ejecutar deploy manual
/bin/bash ~/deploy-${DOMINIO}.sh

# Verificar logs
tail -f /home/${USUARIO_HOSTINGER}/deploy-logs/$(date +\%Y-\%m-\%d).log

# Verificar archivos
ls -la ${CARPETA_EDITABLE}/
```

#### **3. Prueba de Cambio en Hostinger**
```bash
# Crear archivo de prueba
echo "Prueba sync $(date)" > ${CARPETA_EDITABLE}/test-hostinger.txt

# Sincronizar
/bin/bash ~/sync-${DOMINIO}.sh

# Verificar en GitHub que apareció el archivo
```

### **💻 CONFIGURACIÓN VS CODE**

#### **1. Clonar Repositorio**
```bash
# En VS Code Terminal
git clone https://github.com/${USUARIO_GITHUB}/${REPO_GITHUB##*/} ./
```

#### **2. Configurar Credenciales**
```bash
# Configurar usuario (IMPORTANTE: Usar cuenta correcta)
git config user.name "${USUARIO_GITHUB}"
git config user.email "tu-email@dominio.com"

# Verificar configuración
git config --list | grep user
```

#### **3. Flujo de Trabajo en VS Code**
```bash
# 1. Hacer cambios en la carpeta permitida
# 2. Stage cambios
git add carpeta-permitida/

# 3. Commit
git commit -m "Descripción del cambio"

# 4. Push (activará GitHub Actions)
git push origin main
```

#### **4. Verificar Push Exitoso**
```bash
# Verificar que el push funcionó
git log --oneline -2

# Verificar GitHub Actions
# Ir a: https://github.com/${USUARIO_GITHUB}/${REPO_GITHUB##*/}/actions
```

### **🌐 PRUEBAS DIRECTAS DESDE GITHUB**

#### **1. Edición via GitHub Web**
1. Navegar a: `https://github.com/${USUARIO_GITHUB}/${REPO_GITHUB##*/}`
2. Ir a la carpeta sincronizada
3. Click en **"Edit this file"** (ícono de lápiz)
4. Hacer cambios
5. **Commit directly to the main branch**
6. Verificar que GitHub Actions se ejecuta

#### **2. Verificar GitHub Actions**
1. Ir a **Actions** tab del repositorio
2. Verificar que el workflow **"Deploy Módulo"** se ejecutó
3. Revisar logs del deployment
4. Verificar que no hay errores

#### **3. Verificar Deploy en Hostinger**
```bash
# Conectar a Hostinger y verificar
ssh ${USUARIO_HOSTINGER}@${DOMINIO}
ls -la ${CARPETA_EDITABLE}/
cat ${CARPETA_EDITABLE}/test-github.txt
```

### **⚙️ CONFIGURACIÓN GITHUB ACTIONS**

#### **Workflow para Nuevo Proyecto**
```yaml
# .github/workflows/deploy-${DOMINIO}.yml
name: 🚀 Deploy ${DOMINIO}

on:
  push:
    branches: [ main ]
    paths:
      - 'carpeta-permitida/**'

jobs:
  deploy:
    runs-on: ubuntu-latest
    
    steps:
    - name: 📥 Checkout código
      uses: actions/checkout@v4
      
    - name: 🔑 Configurar SSH
      uses: webfactory/ssh-agent@v0.8.0
      with:
        ssh-private-key: ${{ secrets.${DOMINIO^^}_SSH_KEY }}
        
    - name: 📤 Sincronizar carpeta
      run: |
        echo "🔄 Sincronizando carpeta-permitida/"

        # Crear la carpeta si no existe
        ssh -o StrictHostKeyChecking=no -p 65002 ${{ secrets.${DOMINIO^^}_USER }}@${{ secrets.${DOMINIO^^}_HOST }} \
          "mkdir -p ${{ secrets.${DOMINIO^^}_PATH }}/carpeta-permitida"

        # Sincronizar la carpeta completa
        rsync -avz \
          --delete \
          -e "ssh -o StrictHostKeyChecking=no" -p 65002" \
          ./carpeta-permitida/ \
          ${{ secrets.${DOMINIO^^}_USER }}@${{ secrets.${DOMINIO^^}_HOST }}:${{ secrets.${DOMINIO^^}_PATH }}/carpeta-permitida/
          
    - name: 🔧 Configurar permisos
      run: |
        ssh -o StrictHostKeyChecking=no -p 65002 ${{ secrets.${DOMINIO^^}_USER }}@${{ secrets.${DOMINIO^^}_HOST }} \
          "chmod -R 755 ${{ secrets.${DOMINIO^^}_PATH }}/carpeta-permitida/ && \
          find ${{ secrets.${DOMINIO^^}_PATH }}/carpeta-permitida/ -type f -exec chmod 644 {} \;"
```

### **🔒 CONFIGURACIÓN SECRETS GITHUB**

#### **Secrets Requeridos:**
1. **`DOMINIO_SSH_KEY`**: Clave privada SSH
2. **`DOMINIO_USER`**: Usuario Hostinger
3. **`DOMINIO_HOST`**: Dominio
4. **`DOMINIO_PATH`**: Ruta base

#### **Obtener Clave Privada:**
```bash
cat ~/.ssh/${DOMINIO}-deploy
```

### **⏰ CONFIGURACIÓN CRON AUTOMÁTICO**

#### **Agregar a Crontab:**
```bash
# Editar crontab
crontab -e

# Agregar línea para sync automático
*/30 * * * * /bin/bash /home/${USUARIO_HOSTINGER}/sync-${DOMINIO}.sh
```

---

## 🚨 **SOLUCIÓN DE PROBLEMAS**

### **Problemas Comunes y Soluciones**

#### **1. Error: Permission Denied**
```bash
# Verificar permisos SSH
chmod 600 ~/.ssh/${DOMINIO}-deploy
chmod 644 ~/.ssh/${DOMINIO}-deploy.pub

# Verificar configuración SSH
cat ~/.ssh/config
```

#### **2. Error: Sync/Deploy Script Falla**
```bash
# Verificar que la carpeta existe
ls -la ${CARPETA_EDITABLE}

# Verificar que es repositorio Git
cd ${RUTA_BASE}
git status

# Verificar logs detallados
/bin/bash ~/sync-${DOMINIO}.sh
```

#### **3. Error: GitHub Actions Falla**
- Verificar **Secrets** en GitHub
- Verificar que la **clave SSH** está agregada
- Revisar **logs** del Action

#### **4. Conflictos de Merge**
```bash
# En Hostinger, resolver conflictos
cd ${RUTA_BASE}
git fetch origin
git reset --hard origin/main
```

### **Script de Diagnóstico**
```bash
#!/bin/bash
# diagnose-${DOMINIO}.sh - Diagnóstico completo

echo "=== DIAGNÓSTICO ${DOMINIO} ==="
echo "1. Conexión SSH:"
ssh -T ${DOMINIO}-github

echo "2. Estado Git:"
cd ${RUTA_BASE}
git status
git log --oneline -3

echo "3. Archivos en carpeta:"
ls -la ${CARPETA_EDITABLE} | head -10

echo "4. Logs recientes:"
tail -5 /home/${USUARIO_HOSTINGER}/sync-logs/$(date +\%Y-\%m-\%d).log 2>/dev/null || echo "No hay logs hoy"
tail -5 /home/${USUARIO_HOSTINGER}/deploy-logs/$(date +\%Y-\%m-\%d).log 2>/dev/null || echo "No hay logs hoy"

echo "5. Cron activo:"
crontab -l | grep ${DOMINIO} || echo "No hay cron configurado"
```

---

## ✅ **CHECKLIST DE IMPLEMENTACIÓN**

### **Configuración Inicial**
- [ ] Script de setup ejecutado
- [ ] Clave SSH generada y agregada a GitHub
- [ ] Secrets configurados en GitHub
- [ ] Workflow GitHub Actions creado
- [ ] Cron job configurado

### **Pruebas de Funcionamiento**
- [ ] Sync manual Hostinger → GitHub
- [ ] Deploy manual GitHub → Hostinger
- [ ] Edición desde VS Code
- [ ] Edición desde GitHub Web
- [ ] Sincronización automática

### **Monitoreo**
- [ ] Logs funcionando
- [ ] GitHub Actions ejecutándose
- [ ] Sin errores en sincronización
- [ ] Permisos correctos

---

## 📞 **MANTENIMIENTO**

### **Tareas Periódicas**
- **Diario**: Revisar logs de sync/deploy
- **Semanal**: Limpiar logs antiguos
- **Mensual**: Verificar permisos y seguridad
- **Según necesidad**: Actualizar scripts

### **Backup y Recuperación**
```bash
# Backup de configuración
tar -czf /home/${USUARIO_HOSTINGER}/backup-sync-${DOMINIO}.tar.gz \
  ~/.ssh/${DOMINIO}-deploy* \
  ~/sync-${DOMINIO}.sh \
  ~/deploy-${DOMINIO}.sh

# Restaurar desde GitHub (si hay problemas)
cd ${RUTA_BASE}
git fetch origin
git reset --hard origin/main
```

**¡Sistema listo para producción! 🚀**