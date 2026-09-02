# Checklist de despliegue del módulo de inventario

## Antes del despliegue

- [ ] Confirmar una copia de seguridad restaurable de base de datos y archivos de productos.
- [ ] Verificar PHP, extensiones, servidor SQL y permisos de `storage`/`bootstrap/cache`.
- [ ] Configurar `APP_ENV=production`, `APP_DEBUG=false`, URL, correo, colas, sesión y caché.
- [ ] No copiar `.env` de desarrollo ni credenciales de demostración.
- [ ] Ejecutar `composer install --no-dev --optimize-autoloader`.
- [ ] Ejecutar `php artisan test` en CI con la misma revisión que se desplegará.
- [ ] Revisar `php artisan migrate:status`; no debe haber migraciones desconocidas o duplicadas.
- [ ] Crear una base MySQL exclusiva terminada en `_test` y certificar bloqueos reales con dos procesos:
  - PowerShell: `$env:INVENTORY_MYSQL_CONCURRENCY='1'; $env:INVENTORY_MYSQL_DATABASE='saas_tienda_moda_inventory_test'; php artisan test tests/Feature/MySqlInventoryConcurrencyTest.php`.
  - Linux: `INVENTORY_MYSQL_CONCURRENCY=1 INVENTORY_MYSQL_DATABASE=saas_tienda_moda_inventory_test php artisan test tests/Feature/MySqlInventoryConcurrencyTest.php`.
- [ ] Restaurar una copia anonimizada de la base existente en un entorno aislado y ejecutar `php artisan migrate --force`.
- [ ] Ejecutar `php artisan test tests/Feature/InventoryMigrationUpgradeTest.php` y comprobar que los conteos históricos conservan su identidad y diferencia.

## Despliegue

- [ ] Activar modo mantenimiento: `php artisan down --retry=60`.
- [ ] Aplicar migraciones: `php artisan migrate --force`.
- [ ] Verificar especialmente las migraciones `000024` a `000028`, `000046` y `000047` de inventario.
- [ ] Regenerar cachés: `php artisan optimize:clear` y `php artisan optimize`.
- [ ] Reiniciar workers de cola: `php artisan queue:restart` cuando corresponda.
- [ ] Desactivar mantenimiento: `php artisan up`.

## Pruebas de humo

- [ ] Iniciar sesión con administrador, almacén y vendedor de prueba.
- [ ] Confirmar aislamiento entre dos tiendas.
- [ ] Registrar una compra y verificar entrada en kardex.
- [ ] Registrar una venta y verificar salida y saldo.
- [ ] Anular la venta y verificar reposición exacta, una sola vez.
- [ ] Registrar conteo físico y confirmar que no cambia stock antes de conciliar.
- [ ] Conciliar como administrador y verificar movimiento, responsable y auditoría.
- [ ] Confirmar que almacén no puede autorizar conciliaciones.
- [ ] Descargar y revisar el reporte CSV de reposición.
- [ ] Comprobar estados disponible, bajo y agotado en Stock, Dashboard, POS y Reportes.

## Observabilidad y rollback

- [ ] Supervisar errores HTTP 422/403/500, consultas lentas y fallos de bloqueo durante la primera hora.
- [ ] Verificar crecimiento y retención de `stock_movimientos`, `stock_conteos` y `auditorias`.
- [ ] Ante un fallo, poner el sistema en mantenimiento antes de restaurar.
- [ ] No ejecutar `migrate:rollback` después de registrar movimientos reales sin evaluar pérdida de trazabilidad.
- [ ] Restaurar código y base de datos desde el mismo punto de respaldo.
- [ ] Documentar responsable, hora, versión desplegada y resultado de las pruebas de humo.

## Respaldo y recuperación verificable

- [ ] Registrar versión de aplicación, última migración aplicada y hora de inicio del respaldo.
- [ ] Crear un respaldo consistente de MySQL, incluyendo rutinas y triggers:
  `mysqldump --single-transaction --routines --triggers --default-character-set=utf8mb4 BASE_DATOS > inventario_predeploy.sql`.
- [ ] Respaldar `storage/app/public` y conservarlo junto al dump y a la versión exacta del código.
- [ ] Calcular y guardar el hash SHA-256 del dump y del archivo de productos.
- [ ] Restaurar primero en una base aislada:
  `mysql BASE_DATOS_RESTAURACION < inventario_predeploy.sql`.
- [ ] Verificar en la restauración: cantidades de variantes, suma de stock, movimientos, conteos, ventas y compras.
- [ ] Ejecutar `php artisan migrate:status` y las pruebas de humo sobre la restauración antes de aprobar el respaldo.
- [ ] Si el despliegue falla, activar mantenimiento, preservar logs y detener workers antes de restaurar.
- [ ] Restaurar base, archivos y código del mismo punto temporal; no mezclar respaldos de versiones diferentes.
- [ ] Después de recuperar, verificar el último saldo del kardex contra cada variante y conciliar las cajas afectadas.
- [ ] Registrar evidencia de recuperación: responsable, duración, hashes, filas verificadas y autorización de reapertura.
