# 📦 Scripts de Deploy - ERP Batidos Pitaya

Esta carpeta contiene los scripts para realizar despliegues rápidos de los módulos del ERP.

## 🚀 Uso desde la Terminal

### Desde la raíz del proyecto:
```powershell
# Subir cambios de un módulo específico
.\.scripts\gitpush-mantenimiento.ps1
.\.scripts\gitpush-sistemas.ps1
.\.scripts\gitpush-gerencia.ps1
# ... etc
```


### Desde cualquier ubicación:
```powershell
cd "c:\Users\migue\Desktop\Sistema\Pitaya Web\VisualCode\erp.batidospitaya.com"
.\.scripts\gitpush-[nombre-modulo].ps1
```

## 📋 Scripts Disponibles

- `gitpush-atencioncliente.ps1`
- `gitpush-auxiliaradministrativo.ps1`
- `gitpush-cds.ps1`
- `gitpush-desarrollo.ps1`
- `gitpush-diseno.ps1`
- `gitpush-experienciadigital.ps1`
- `gitpush-gerencia.ps1`
- `gitpush-infraestructura.ps1`
- `gitpush-legal.ps1`
- `gitpush-mantenimiento.ps1`
- `gitpush-marketing.ps1`
- `gitpush-produccion.ps1`
- `gitpush-rh.ps1`
- `gitpush-sistemas.ps1`
- `gitpush-sucursales.ps1`
- `gitpush-tecnicodesarrollohumano.ps1`
- `gitpush-ventas.ps1`
- `gitpush.ps1` (sube todos los cambios)

---

## 🏗️ Lógica del Deploy

El sistema de deploy está configurado para:
- ✅ Sincronizar los 17 módulos individuales.
- ❌ Excluir la carpeta `uploads/` de cada módulo para preservar archivos subidos.
- 🔧 Configurar permisos automáticos en el servidor (755 carpetas, 644 archivos).

---

## 🔄 Sincronización Manual (Reset)

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

**Última actualización:** 2026-02-17

