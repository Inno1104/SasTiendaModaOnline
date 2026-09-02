# Checklist de producción: proveedores

## Antes del despliegue

- [ ] Crear un respaldo verificado de la base de datos y del archivo `.env`.
- [ ] Confirmar `APP_ENV=production`, `APP_DEBUG=false` y una `APP_KEY` válida.
- [ ] Verificar credenciales de base de datos, correo, caché, sesiones y colas.
- [ ] Ejecutar `composer install --no-dev --optimize-autoloader`.
- [ ] Ejecutar `php artisan test` y detener el despliegue ante cualquier fallo.
- [ ] Ejecutar `php artisan migrate:status` y revisar migraciones pendientes.
- [ ] Comprobar que no existan proveedores sin `tienda_id` ni RUC duplicados por tienda.
- [ ] Verificar espacio disponible y duración estimada del respaldo y migraciones.

## Despliegue

- [ ] Activar mantenimiento con `php artisan down` cuando el despliegue no sea de cero interrupciones.
- [ ] Desplegar exactamente la versión validada del código.
- [ ] Ejecutar `php artisan migrate --force`.
- [ ] Ejecutar `php artisan optimize:clear` y luego `php artisan optimize`.
- [ ] Reiniciar workers con `php artisan queue:restart` si existen colas activas.
- [ ] Desactivar mantenimiento con `php artisan up`.

## Verificación de esquema

- [ ] Confirmar `2026_08_25_000029_secure_supplier_identity` como `Ran`.
- [ ] Confirmar `2026_08_25_000030_optimize_supplier_queries` como `Ran`.
- [ ] Verificar el índice único `proveedores_tienda_ruc_unique`.
- [ ] Verificar `proveedores_tienda_estado_nombre` y `proveedores_tienda_fecha`.
- [ ] Verificar `productos_tienda_proveedor` y `compras_tienda_proveedor`.

## Smoke tests

- [ ] Iniciar sesión como Administrador y registrar un proveedor con RUC.
- [ ] Intentar repetir el mismo RUC con separadores y comprobar que sea rechazado.
- [ ] Registrar dos proveedores sin RUC y comprobar que ambos sean aceptados.
- [ ] Crear un producto con proveedor y otro sin proveedor.
- [ ] Registrar una compra y comprobar detalle, stock y kardex.
- [ ] Desactivar el proveedor y confirmar que desaparezca de operaciones nuevas.
- [ ] Confirmar que productos y compras históricas sigan mostrando al proveedor.
- [ ] Reactivar el proveedor y comprobar que vuelva a estar disponible.
- [ ] Validar búsqueda por nombre, RUC, contacto, teléfono y correo.
- [ ] Probar Administrador, Almacén y Vendedor según sus permisos.
- [ ] Acceder con dos tiendas y confirmar que no exista exposición cruzada.

## Concurrencia y carga en staging

- [ ] Enviar solicitudes paralelas con el mismo RUC y confirmar un único proveedor.
- [ ] Desactivar un proveedor mientras se intenta registrar una compra y comprobar consistencia.
- [ ] Ejecutar una prueba de carga del listado con datos equivalentes al volumen proyectado.
- [ ] Revisar bloqueos, deadlocks, consultas lentas y tiempo de respuesta p95.
- [ ] Confirmar que el listado se mantiene dentro del presupuesto de consultas automatizado.

## Observabilidad

- [ ] Revisar errores HTTP 403, 404, 422 y 500 después del despliegue.
- [ ] Supervisar fallos de auditoría y transacciones revertidas.
- [ ] Revisar crecimiento de `auditorias` y definir su política de retención.
- [ ] Configurar alertas para errores de integridad y deadlocks de base de datos.

## Rollback

- [ ] Detener escrituras antes de restaurar una copia de seguridad.
- [ ] No revertir la migración de identidad si ya existen RUC iguales en tiendas distintas.
- [ ] Preferir restaurar código y base de datos del mismo punto de despliegue.
- [ ] Ejecutar nuevamente los smoke tests después de cualquier rollback.
