# 🚀 ERP Batidos Pitaya

Repositorio central del sistema ERP para Batidos Pitaya.

## 📦 Estructura del Proyecto

- `modulos/`: Contiene los 17 módulos del sistema.
- `.github/workflows/`: Workflows de GitHub Actions para deploy automático.
- `.scripts/`: Scripts auxiliares de PowerShell.
- `docs/`: Documentación técnica y de infraestructura.

## 🚀 Deploy Automático

### Gestión de Archivos (Estandarización)
Para mantener el repositorio limpio y seguro, se aplican las siguientes reglas:

| Carpeta/Archivo | Subir a GitHub | Subir al Host |
| :--- | :---: | :---: |
| `.agent/`, `core/`, `docs/` | ❌ No | ❌ No |
| `.scripts/` | ✅ Sí | ❌ No |
| `.github/`, `.gitignore` | ✅ Sí | ❌ No |
| `modulos/` (lógica) | ✅ Sí | ✅ Sí |
| `modulos/*/uploads/` | ❌ No | ❌ No |

- 🔧 Permisos automáticos aplicados en cada deploy: 755 para carpetas y 644 para archivos.
- 📁 Las carpetas `uploads` dentro de cada módulo se crean automáticamente si no existen.

### Documentación de Deploy

Toda la información sobre el sistema de deploy, configuración de SSH y cómo agregar nuevos dominios se encuentra en la carpeta `docs/`:

1. [**Guía de Configuración General**](docs/DEPLOY_SETUP.md)
2. [**Implementar Nuevo Dominio**](docs/DEPLOY_NEW_DOMAIN.md)
3. [**Solución de Problemas (Troubleshooting)**](docs/TROUBLESHOOTING.md)

---

## 🛠️ Desarrollo Local

Para trabajar en este proyecto localmente, asegúrate de tener configurado tu entorno de PHP y Visual Studio Code.

### Scripts de Ayuda
Usa los scripts en `.scripts/` para agilizar tus commits y pushes:
- `.\.scripts\gitpush.ps1`: Sube todos los cambios y activa el deploy.

---

**Última actualización:** 2026-02-17
