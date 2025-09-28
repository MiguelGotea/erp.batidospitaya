📋 README - Sistema de Sincronización Automática ERP Batidos Pitaya
🎯 Descripción del Sistema
Sistema de sincronización bidireccional automática entre Hostinger y GitHub para el módulo de mantenimiento del ERP Batidos Pitaya.

Características Principales
🔄 Sincronización bidireccional automática

🚀 Deploy automático via GitHub Actions

⏰ Sync cada 30 minutos desde Hostinger

🔒 Seguridad integrada con .gitignore

📊 Logs detallados para monitoreo

🏗️ Arquitectura del Sistema
Flujo de Trabajo

graph TB
    A[Editor en Hostinger] --> B[Sync automático cada 30min]
    C[Editor en GitHub] --> D[GitHub Actions Deploy]
    B --> E[Repositorio GitHub]
    D --> F[Hostinger Actualizado]
    E --> D




Estructura de Carpetas
text
erp.batidospitaya.com/
├── 📁 modulos/
│   └── 📁 mantenimiento/          # ✅ Sincronizado
│       ├── *.php
│       ├── 📁 ajax/
│       └── 📁 models/
├── 📁 .github/workflows/          # ✅ Workflows GitHub
│   └── deploy-mantenimiento.yml
├── .gitignore                     # ✅ Configuración seguridad
└── README.md
⚙️ Configuración Técnica
Credenciales Configuradas
Dominio: erp.batidospitaya.com

Usuario Hostinger: u839374897

Repositorio GitHub: MiguelGotea/erp.batidospitaya

Ruta sincronizada: /modulos/mantenimiento/

Secrets GitHub Configurados
HOSTINGER_SSH_KEY - Clave privada SSH

HOSTINGER_USER - u839374897

HOSTINGER_HOST - erp.batidospitaya.com

HOSTINGER_PATH - Ruta completa del proyecto

🚀 Cómo Usar el Sistema
Para Desarrolladores Editando en Hostinger
Edición Normal:
Modificar archivos en /modulos/mantenimiento/

Los cambios se sincronizarán automáticamente cada 30 minutos

Verificar en GitHub que los cambios aparecieron

Sincronización Manual (Si es necesario):
bash
# Ejecutar sync inmediato
/bin/bash ~/sync-to-github.sh

# Verificar logs
cat /home/u839374897/sync-logs/$(date +\%Y-\%m-\%d).log
Para Desarrolladores Editando en GitHub
Flujo de Trabajo:
Editar archivos en GitHub: modulos/mantenimiento/

Hacer commit y push a la rama main

GitHub Actions se ejecuta automáticamente

Los cambios se despliegan en Hostinger en 2-3 minutos

Verificar Deploy:
Ver GitHub Actions: https://github.com/MiguelGotea/erp.batidospitaya/actions

Revisar logs en Hostinger: cat ~/deploy-logs/fecha.log

📊 Monitoreo y Logs
Archivos de Log
bash
# Logs de sincronización (Hostinger → GitHub)
/home/u839374897/sync-logs/YYYY-MM-DD.log

# Logs de deploy (GitHub → Hostinger)  
/home/u839374897/deploy-logs/YYYY-MM-DD.log

# Log del daemon/cron
/home/u839374897/daemon.log
Comandos de Monitoreo
bash
# Verificar sync reciente
tail -f /home/u839374897/sync-logs/$(date +\%Y-\%m-\%d).log

# Verificar deploy reciente
tail -f /home/u839374897/deploy-logs/$(date +\%Y-\%m-\%d).log

# Verificar estado Git
cd /home/u839374897/domains/erp.batidospitaya.com/public_html
git status modulos/mantenimiento/
🔒 Seguridad y Exclusiones
Archivos Excluidos (.gitignore)
Credenciales y configuraciones sensibles

Archivos multimedia grandes (>50MB)

Logs y archivos temporales

Uploads y backups

Estructura Protegida
Solo modulos/mantenimiento/ se sincroniza

Credenciales de BD excluidas

Archivos de configuración protegidos

🛠️ Scripts y Automatización
Scripts Principales
Script	Función	Frecuencia
sync-to-github.sh	Hostinger → GitHub	Cada 30 min
deploy-erp.sh	GitHub → Hostinger	Manual
sync-daemon.sh	Vigilancia continua	Continuo
GitHub Actions
Workflow: deploy-mantenimiento.yml

Trigger: Push a main en modulos/mantenimiento/

Acción: Deploy automático a Hostinger

❓ Solución de Problemas
Problemas Comunes
Sync no se ejecuta automáticamente
bash
# Ejecutar manualmente
/bin/bash ~/sync-to-github.sh

# Verificar daemon
ps aux | grep sync-daemon
GitHub Actions falla
Verificar GitHub Secrets están configurados

Revisar logs en Actions tab

Verificar permisos SSH

Conflictos de merge
bash
# En Hostinger, resolver conflictos
cd /home/u839374897/domains/erp.batidospitaya.com/public_html
git fetch origin
git reset --hard origin/main
Comandos de Diagnóstico
bash
# Estado completo del sistema
cd /home/u839374897/domains/erp.batidospitaya.com/public_html
git status
git log --oneline -3
ps aux | grep sync-daemon
📞 Soporte y Mantenimiento
Contacto
Administrador: Miguel Gotea

Repositorio: https://github.com/MiguelGotea/erp.batidospitaya

Mantenimiento Programado
Limpieza de logs antiguos: Domingos 2:00 AM

Verificación de seguridad: Mensual

Actualización de scripts: Según necesidad

✅ Checklist de Verificación
Estado Actual del Sistema
Sincronización Hostinger → GitHub configurada

GitHub Actions para deploy automático

Seguridad con .gitignore implementada

Logs de actividad funcionando

Múltiples editores pueden trabajar

Próximas Mejoras Opcionales
Notificaciones por email

Monitoreo más avanzado

Backup automático adicional

Panel de control web

🎉 ¡Sistema Listo para Producción!
El sistema de sincronización está completamente operativo y permite:

✅ Desarrollo colaborativo multi-editor

✅ Backup automático en GitHub

✅ Deploy continuo y seguro

✅ Monitoreo detallado

¡Happy coding! 🚀