# Initial MEKATOS Project Analysis

Fecha de análisis: 2026-09-04.

## Estado observado

El repositorio `01Javier-web/mekatos` usa Laravel y ya contiene backend API, modelos Eloquent, migraciones, enums, Sanctum, middleware de roles y una primera capa Blade. El árbol actual incluye Controllers API/Admin, Web Admin, Models, migrations, `routes/api.php`, `routes/web.php`, CSS/JS públicos, vistas Blade y tests de ejemplo.

## Backend relevante

- `routes/api.php` contiene endpoints de mesa por token QR, menú, creación de pedidos, login/logout, administración de pedidos/usuarios/categorías/productos/mesas y entrega de pedidos.
- `routes/web.php` actualmente tiene redirección raíz, listado web de pedidos y rutas `/tables`.
- `bootstrap/app.php` registra el alias `role` para `RoleMiddleware`.
- `RoleMiddleware` compara los roles recibidos contra `UserRole::tryFrom()`.
- `UserRole` define exactamente `ADMIN` y `MESERO`.
- `Order` usa `orderItems()`, `statusHistories()`, `tableSession()`, `handledBy()` y `deliveredBy()`.
- `OrderStatus` define los backing values `PENDIENTE`, `PREPARANDO`, `LISTO`, `ENTREGADO` y `CANCELADO`.

## Frontend observado

Existe una estructura Blade inicial: `resources/views/layouts/app.blade.php`, `resources/views/admin/orders/index.blade.php` y `resources/views/welcome.blade.php`. Existe también `app/Http/Controllers/Web/Admin/OrderController.php`. La estructura todavía está lejos del frontend completo definido en la especificación de agentes.

## Tests observados

El repositorio contiene únicamente los tests de ejemplo de Laravel en `tests/Feature/ExampleTest.php` y `tests/Unit/ExampleTest.php`. Por tanto, la cobertura funcional requerida por `LaravelQA` todavía debe construirse.

## CSS/JS observado

Existen `public/css/app.css`, `public/js/app.js`, además de `resources/css/app.css` y `resources/js/app.js`. Esto debe revisarse antes de decidir una estrategia de estilos para evitar duplicación.

## Hallazgo importante para BackendGuardian

El repositorio contiene `app/OrderStatus.php`, pero el archivo declara `namespace App\Enums;` y el código consume `App\Enums\OrderStatus`. La ruta esperada por PSR-4 para ese namespace sería `app/Enums/OrderStatus.php`. Este punto debe verificarse en ejecución antes de modificarlo; no debe corregirse automáticamente sin comprobar el estado real de Composer/autoload y las pruebas.

## Hallazgo de arquitectura web

`routes/web.php` actualmente solo expone una vista administrativa de pedidos y las rutas `/tables`. Por lo tanto, la futura expansión Blade requerirá Web Controllers y rutas web adicionales, pero cada módulo debe diseñarse después de inspeccionar el backend correspondiente.

## Plan inicial

El primer trabajo funcional debe comenzar con `BackendGuardian` leyendo el contrato real del backend y `ProjectCoordinator` organizando el módulo de layout/login antes de implementar CRUDs Blade. No se asignan cambios de negocio backend por defecto.

## Criterio de seguridad

No se debe considerar seguro un módulo porque un botón esté oculto. La autorización debe permanecer en servidor mediante los mecanismos existentes.
