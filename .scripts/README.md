# 📦 Scripts de Deploy — ERP Batidos Pitaya

Esta carpeta contiene los scripts para realizar despliegues del ERP.

---

## 🚀 Uso

### Subir todos los cambios (push global):
```powershell
# Desde la raíz del proyecto
.\.scripts\gitpush_erp.ps1
```

### Desde cualquier ubicación:
```powershell
cd "c:\Users\migue\Desktop\Sistema\Pitaya Web\VisualCode\erp.batidospitaya.com"
.\.scripts\gitpush_erp.ps1
```

---

## 🏗️ Lógica del Sistema

El sistema sube **todo el contenido de `modulos/`** automáticamente.  
No es necesario listar módulos individuales; cualquier carpeta nueva que se agregue dentro de `modulos/` será incluida en el siguiente push.

### ✅ Qué se sube
- Todo lo que está dentro de `modulos/`
- `core/`, `docs/`, `README.md` de la raíz

### ❌ Qué se excluye siempre (Git + Deploy)
- Cualquier carpeta llamada `uploads/` en **cualquier nivel** dentro de `modulos/`

### ⚠️ Excepciones temporales (carpetas de fotos sin nombre `uploads/`)
Estas carpetas contienen archivos subidos por usuarios pero no se llaman `uploads/`.  
Se excluyen explícitamente hasta que sean migradas a su carpeta `uploads/` correcta:

| Carpeta | Migrar a |
|---|---|
| `supervision/auditorias_original/auditinternas/fotos_auditorias_caja_chica/` | `uploads/` dentro del módulo |
| `supervision/auditorias_original/auditinternas/fotos_auditorias_caja_facturacion/` | `uploads/` dentro del módulo |
| `supervision/auditorias_original/auditinternas/fotos_auditorias_inventario/` | `uploads/` dentro del módulo |
| `supervision/auditorias_original/fotos/` | `uploads/` dentro del módulo |
| `supervision/auditorias_original/fotoslimpieza/` | `uploads/` dentro del módulo |
| `supervision/auditorias_original/fotospersonal/` | `uploads/` dentro del módulo |
| `supervision/auditorias_original/fotosservicio/` | `uploads/` dentro del módulo |

> Al migrar cada carpeta, elimina su línea correspondiente en `.gitignore` y su comentario en `deploy-erp.yml`.

---

## 🔄 Sincronización Manual (Reset forzado)

Si necesitas forzar que el servidor se iguale a GitHub:

```bash
ssh -p 65002 u839374897@145.223.105.42
cd ~/domains/erp.batidospitaya.com/public_html
git fetch origin main
git reset --hard origin/main
```

> [!CAUTION]
> El comando `git reset --hard` borrará cualquier cambio local no committeado en el servidor. Úsalo con precaución.

---

## 🔐 Configuración SSH

Este repositorio utiliza la clave estandarizada `batidospitaya-deploy`.

Ver documentación completa:  
[docs/DEPLOY_SETUP.md](../docs/DEPLOY_SETUP.md)

---

**Última actualización:** 2026-04-29
