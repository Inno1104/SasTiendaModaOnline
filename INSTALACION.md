# SaaS Tienda Moda — Guía de Instalación

## Requisitos
- PHP 8.1+
- Composer
- MySQL 5.7+ / MariaDB 10+
- Node.js (opcional, para assets)

## Pasos de instalación

### 1. Instalar dependencias PHP
```bash
composer install
```

### 2. Copiar variables de entorno
```bash
cp .env.example .env
```
Editar `.env` y configurar:
```
DB_HOST=127.0.0.1
DB_DATABASE=saas_tienda_moda
DB_USERNAME=root
DB_PASSWORD=    # tu contraseña o vacío
```

### 3. Generar clave de aplicación
```bash
php artisan key:generate
```

### 4. Crear la base de datos en MySQL
```sql
CREATE DATABASE saas_tienda_moda CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 5. Ejecutar migraciones y datos iniciales
```bash
php artisan migrate --seed
```

### 6. Enlace de almacenamiento (para imágenes)
```bash
php artisan storage:link
```

### 7. Iniciar el servidor
```bash
php artisan serve
```
Abrir: http://localhost:8000

## Credenciales iniciales
- **Email:** admin@tiendamoda.com
- **Contraseña:** admin123

## Módulos incluidos
- Dashboard con KPIs, gráficos de ventas y categorías
- Punto de Venta (POS) con carrito interactivo
- Gestión de Productos con variantes (talla/color)
- Categorías por género (Damas, Caballeros, Niños)
- Tipos: Casual, Deportivo, Formal
- Gestión de Clientes con sistema de puntos
- Historial de Ventas con filtros
- Proveedores
- Reportes de Ventas e Inventario
- Autenticación con roles (Administrador, Vendedor, Almacén)
