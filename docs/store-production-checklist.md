# Checklist de producción — Mi tienda y configuración

## Antes del despliegue

- [ ] Registrar versión del código, responsable, ventana de mantenimiento y última migración aplicada.
- [ ] Ejecutar `php artisan migrate:status` y comprobar que las migraciones `000049`, `000050` y `000051` aparecen una sola vez.
- [ ] Ejecutar `php artisan test --compact` en el mismo commit que será desplegado.
- [ ] Confirmar que `storage/app/public` y el enlace `public/storage` son accesibles por la aplicación.
- [ ] Verificar que `.env`, credenciales y archivos de respaldo no estén dentro del directorio público.

## Respaldo verificable

- [ ] Crear un respaldo consistente de MySQL con transacción, rutinas y triggers.
- [ ] Respaldar `storage/app/public/tiendas` y `storage/app/public/comprobantes` en el mismo punto temporal que la base.
- [ ] Calcular y registrar hashes de ambos respaldos.
- [ ] Restaurar base y archivos en un entorno aislado; un respaldo que no fue restaurado todavía no está verificado.
- [ ] Abrir una tienda existente, un logo histórico y un comprobante emitido después de restaurar.

Ejemplo de respaldo de base:

```text
mysqldump --single-transaction --routines --triggers --default-character-set=utf8mb4 BASE_DATOS > tienda_predeploy.sql
```

## Pruebas de humo

- [ ] Administrador activo puede modificar su tienda; vendedor, almacén, usuario inactivo y otra tienda reciben rechazo.
- [ ] Nombre, identificación, correo, teléfonos, ubicación y URLs se normalizan y validan.
- [ ] Logo válido puede cargarse, reemplazarse y eliminarse; SVG con scripts y archivos disfrazados se rechazan.
- [ ] Un error de auditoría revierte datos y elimina el archivo nuevo sin borrar el logo anterior.
- [ ] Cambio fiscal exige confirmación y motivo; un formulario obsoleto no sobrescribe una versión reciente.
- [ ] Dos comprobantes simultáneos obtienen números diferentes dentro de la tienda y serie correspondiente.
- [ ] Vista previa A4 y ticket 80 mm usan los datos temporales sin persistirlos.
- [ ] Flujo tienda → POS → venta → pago → stock → comprobante conserva moneda, series, logo y snapshot comercial.
- [ ] Un usuario de otra empresa no puede consultar ni descargar el comprobante.

## Recuperación

- [ ] Detener escrituras o colocar la aplicación en mantenimiento antes de restaurar.
- [ ] Restaurar código, base de datos y archivos del mismo punto temporal; no mezclar respaldos.
- [ ] Ejecutar `php artisan optimize:clear`, `php artisan migrate:status` y la suite de humo.
- [ ] Comparar conteos de tiendas, ventas, pagos y archivos antes de reabrir.
- [ ] Documentar hora, responsable, duración, hashes, incidencias y autorización de reapertura.
- [ ] No ejecutar `migrate:rollback` sobre producción con ventas reales sin analizar pérdida de datos y dependencias.
