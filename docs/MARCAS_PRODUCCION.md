# Marcas: verificación para producción

## Antes del despliegue

1. Crear el `.env` de producción tomando `.env.production.example` como referencia.
2. Generar una clave propia con `php artisan key:generate` si la instalación es nueva.
3. Confirmar `APP_ENV=production`, `APP_DEBUG=false` y una URL HTTPS real.
4. Configurar credenciales de base de datos, correo y almacenamiento sin guardarlas en el repositorio.
5. Respaldar la base de datos antes de aplicar migraciones.

## Despliegue

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link
php artisan optimize
```

El proceso que ejecuta PHP debe tener escritura únicamente donde Laravel la requiere: `storage` y `bootstrap/cache`.

## Verificación funcional

- Acceso y aislamiento por tienda.
- Creación y edición con nombre único normalizado.
- Activación y desactivación auditadas.
- Bloqueo de eliminación cuando existen productos asociados.
- Integración de marcas activas con Productos y POS.
- Búsqueda, filtros, ordenamiento y paginación.
- Diseño adaptable y permisos de solo lectura.

Ejecutar antes de publicar:

```bash
php artisan test
php artisan migrate:status
php artisan route:list --name=marcas
```

## Reversión

No ejecutar `migrate:rollback` automáticamente sobre información real. Restaurar el respaldo previo si el despliegue falla después de una migración de datos o estructura.
