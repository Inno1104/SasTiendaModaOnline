# Checklist de producción del módulo de usuarios

## Antes del despliegue

- [ ] Identificar versión del código, responsable, ventana de mantenimiento y última migración aplicada.
- [ ] Confirmar `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, correo, caché, colas y política de contraseñas.
- [ ] Revisar `php artisan migrate:status`; no deben existir migraciones desconocidas, duplicadas o pendientes no aprobadas.
- [ ] Verificar específicamente `2026_08_26_000048_optimize_user_management_queries` y sus índices.
- [ ] Ejecutar `php artisan test` con la misma revisión que se desplegará.
- [ ] Confirmar que cada tienda conserva al menos un administrador activo.
- [ ] Confirmar que los roles asignables son Administrador, Vendedor y Almacén y no dependen de IDs fijos.
- [ ] Revisar `SESSION_DRIVER`: con una sola instancia puede mantenerse `file`; para `database` debe existir y probarse la tabla `sessions`; para varias instancias usar un almacenamiento compartido.
- [ ] Probar una copia anonimizada de la base existente con `php artisan migrate --force` en un entorno aislado.

## Concurrencia real en MySQL

- [ ] Crear una base exclusiva cuyo nombre termine en `_test`; la prueba ejecuta `migrate:fresh` y nunca debe apuntar a producción.
- [ ] PowerShell: `$env:USER_PLAN_MYSQL_CONCURRENCY='1'; $env:USER_PLAN_MYSQL_DATABASE='saas_users_test'; php artisan test tests/Feature/MySqlUserPlanConcurrencyTest.php`.
- [ ] Linux: `USER_PLAN_MYSQL_CONCURRENCY=1 USER_PLAN_MYSQL_DATABASE=saas_users_test php artisan test tests/Feature/MySqlUserPlanConcurrencyTest.php`.
- [ ] Confirmar que dos altas simultáneas para un único cupo producen exactamente un usuario creado y uno rechazado.

## Respaldo verificable

- [ ] Activar una ventana sin cambios administrativos o poner la aplicación en mantenimiento.
- [ ] Crear un respaldo consistente: `mysqldump --single-transaction --routines --triggers --default-character-set=utf8mb4 BASE_DATOS > usuarios_predeploy.sql`.
- [ ] Calcular y conservar el hash SHA-256 del dump junto con la versión exacta del código.
- [ ] Restaurar antes del despliegue en una base aislada: `mysql BASE_DATOS_RESTAURACION < usuarios_predeploy.sql`.
- [ ] Comparar por tienda: total de usuarios, activos, administradores activos y eventos de `auditorias` asociados a usuarios.
- [ ] Ejecutar sobre la restauración las pruebas de roles, multiempresa, límite del plan y auditoría.

## Despliegue

- [ ] Activar mantenimiento: `php artisan down --retry=60`.
- [ ] Aplicar migraciones: `php artisan migrate --force`.
- [ ] Regenerar cachés: `php artisan optimize:clear` y `php artisan optimize`.
- [ ] Reiniciar workers: `php artisan queue:restart` cuando corresponda.
- [ ] Desactivar mantenimiento: `php artisan up`.

## Pruebas de humo

- [ ] Iniciar sesión con un administrador activo y abrir el listado paginado.
- [ ] Confirmar que Vendedor y Almacén reciben 403 al intentar administrar usuarios.
- [ ] Crear un usuario dentro del límite y verificar `usuario.creado` en auditoría.
- [ ] Intentar superar `max_usuarios` y confirmar que no quedan usuario ni auditoría parciales.
- [ ] Editar rol y contraseña; comprobar que la contraseña nunca aparece en auditoría.
- [ ] Desactivar un usuario con motivo y confirmar que su siguiente petición cierra la sesión.
- [ ] Reactivarlo con motivo y confirmar `usuario.activado`.
- [ ] Intentar degradar al último administrador activo y confirmar el rechazo.
- [ ] Desde dos tiendas, verificar que ninguna pueda consultar o modificar usuarios de la otra.

## Recuperación y rollback

- [ ] Ante errores, activar mantenimiento, detener workers y conservar logs antes de modificar datos.
- [ ] No usar `migrate:rollback` si posteriores cambios de producción dependen del mismo lote sin evaluar todas las migraciones afectadas.
- [ ] Restaurar base y código del mismo punto temporal; no mezclar versiones.
- [ ] Después de restaurar, ejecutar `php artisan migrate:status` y verificar conteos y administradores activos por tienda.
- [ ] Verificar integridad de auditoría: actor, tienda, usuario afectado, acción, motivo y fecha.
- [ ] Revocar sesiones de las cuentas afectadas; con sesiones `file`, limpiar únicamente el almacenamiento de sesiones planificado; con Redis o base de datos, usar el procedimiento específico del entorno.
- [ ] Ejecutar las pruebas de humo antes de reabrir y documentar responsable, duración, hashes y resultado.
