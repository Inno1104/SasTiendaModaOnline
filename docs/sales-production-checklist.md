# Checklist de producción del módulo de ventas

## Antes del despliegue

- [ ] Congelar la versión que superó la suite y registrar su identificador.
- [ ] Confirmar `APP_ENV=production`, `APP_DEBUG=false`, URL, zona horaria y moneda.
- [ ] Crear un respaldo consistente de base de datos y de `storage/app/public`.
- [ ] Restaurar ese respaldo en un entorno aislado y comprobar que puede abrirse.
- [ ] Ejecutar `composer install --no-dev --optimize-autoloader`.
- [ ] Ejecutar `php artisan test --compact` en CI con MySQL de la misma versión de producción.
- [ ] Ejecutar la prueba de concurrencia con dos workers contra una base MySQL exclusiva de CI; no usar SQLite para certificar bloqueos.
- [ ] Revisar `php artisan migrate:status` y los planes `EXPLAIN` de ventas, caja y reportes.

## Despliegue

- [ ] Activar mantenimiento con `php artisan down --retry=60`.
- [ ] Ejecutar `php artisan migrate --force`.
- [ ] Verificar las migraciones de ventas `000040` a `000045`.
- [ ] Ejecutar `php artisan optimize:clear` y `php artisan optimize`.
- [ ] Reiniciar workers mediante `php artisan queue:restart` cuando corresponda.
- [ ] Desactivar mantenimiento con `php artisan up`.

## Pruebas de humo

- [ ] Abrir una caja y registrar una venta en efectivo.
- [ ] Verificar pago, efectivo esperado, salida de kardex y stock de la variante.
- [ ] Reenviar el mismo UUID y comprobar que no existe doble venta ni doble salida.
- [ ] Intentar vender simultáneamente la última unidad desde dos sesiones; solo una debe confirmarse.
- [ ] Anular con caja abierta y comprobar reversión financiera y reposición exacta.
- [ ] Cerrar caja, procesar una devolución desde otra caja y verificar el egreso.
- [ ] Reimprimir A4 y ticket 80 mm y comparar su snapshot comercial.
- [ ] Confirmar aislamiento usando usuarios de dos tiendas.
- [ ] Consultar Dashboard, Caja, Historial y Reportes para un rango amplio.

## Recuperación

- [ ] Ante un error financiero, activar mantenimiento antes de cualquier restauración.
- [ ] Preservar logs, auditorías, UUID de operación y evidencia del incidente.
- [ ] No ejecutar `migrate:rollback` después de ventas reales sin evaluar las columnas y trazabilidad que se perderían.
- [ ] Restaurar código, base y archivos desde el mismo punto de respaldo.
- [ ] Ejecutar `php artisan migrate:status`, conciliación de caja y verificación de kardex.
- [ ] Documentar hora, responsable, ventas afectadas y resultado de la recuperación.
