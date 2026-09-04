# Mekatos — Sistema de pedidos

Sistema web para gestionar la operación de **Mekatos Comidas Rápidas**: menú digital por QR, pedidos desde mesa, pedidos para llevar y pedidos manuales desde caja/panel de meseros.

## Funcionalidades

### Cliente por QR
- Acceso público mediante QR de cada mesa.
- Consulta de mesa y menú desde la API.
- Menú organizado por categorías.
- Búsqueda de productos.
- Navegación rápida por categorías.
- Carrito con cantidades y subtotales.
- Notas para cocina.
- Persistencia local del carrito mientras el cliente permanece en la mesa.
- Confirmación antes de enviar.
- Confirmación del número de pedido después del envío.
- Mensajes de error y recuperación ante fallos de conexión.

### Operación
- Pedidos en mesa (`MESA`).
- Pedidos para llevar (`PARA_LLEVAR`).
- Flujo de estados: `PENDIENTE` → `PREPARANDO` → `LISTO` → `ENTREGADO`.
- Cancelación desde los estados permitidos.
- Historial de cambios de estado.
- Registro del usuario que crea y entrega pedidos.
- Sesiones de mesa activas y ocupación automática de la mesa para pedidos en mesa.

### Administración
- Dashboard con indicadores operativos.
- Acciones rápidas.
- Gestión de pedidos y filtros por estado.
- Gestión de categorías.
- Gestión de productos y disponibilidad.
- Gestión de mesas y enlaces QR.
- Gestión de usuarios.
- Interfaz responsive para computador, tablet y móvil.

## Tecnologías

- Laravel 12
- PHP
- MySQL / MariaDB
- Laravel Sanctum
- Blade
- JavaScript
- CSS
- Vite

## Estructura principal

```text
app/
  Enums/
  Http/Controllers/
  Models/
  TableStatus.php
  TableSessionStatus.php

database/
  migrations/
  seeders/

resources/
  views/
  css/

public/
  css/
  js/

routes/
  api.php
  web.php

tests/
```

## Instalación local

1. Clonar el repositorio.
2. Instalar dependencias PHP:

```bash
composer install
```

3. Instalar dependencias frontend:

```bash
npm install
```

4. Crear `.env` a partir de `.env.example` y configurar la base de datos.
5. Generar la clave de aplicación:

```bash
php artisan key:generate
```

6. Ejecutar las migraciones:

```bash
php artisan migrate
```

7. Cargar los datos iniciales y el menú de Mekatos:

```bash
php artisan db:seed
```

8. Compilar los recursos frontend:

```bash
npm run build
```

9. Iniciar el proyecto según el entorno local. Con XAMPP puede utilizarse el proyecto dentro de `htdocs`.

## Usuario inicial

El `DatabaseSeeder` contiene el usuario administrador inicial configurado para el entorno de desarrollo.

**No utilizar credenciales de desarrollo en producción.**

## Menú de Mekatos

El seeder `MekatosMenuSeeder` contiene el catálogo organizado por categorías y mantiene los productos no disponibles ocultos del menú público sin borrar el historial de datos.

## Desarrollo

Antes de probar cambios importantes se recomienda limpiar la caché de configuración/vistas cuando corresponda:

```bash
php artisan optimize:clear
```

Para revisar el estado de las migraciones:

```bash
php artisan migrate:status
```
