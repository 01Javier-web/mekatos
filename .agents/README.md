# MEKATOS — Equipo de Agentes

Este directorio define el equipo de agentes para desarrollar el proyecto existente sin romper su arquitectura.

## Regla principal

El backend existente es la fuente de verdad. Antes de modificar cualquier módulo, el agente responsable debe inspeccionar el código real del repositorio.

No se inventan modelos, relaciones, rutas, campos, enums, estados, roles, middleware, reglas de negocio ni nombres de columnas.

## Agentes

1. `BackendGuardian.md` — arquitectura y contrato backend.
2. `BladeFrontend.md` — implementación de interfaces Laravel Blade.
3. `ResponsiveUX.md` — UX, UI y responsive.
4. `LaravelQA.md` — pruebas funcionales y regresión.
5. `SecurityReviewer.md` — seguridad y autorización.
6. `IntegrationLead.md` — integración y conflictos.
7. `ProjectCoordinator.md` — coordinación, orden y aprobación de módulos.

## Flujo

`ProjectCoordinator` → `BackendGuardian` → `BladeFrontend` → `ResponsiveUX` → `LaravelQA` → `SecurityReviewer` → `IntegrationLead` → `ProjectCoordinator`.

Ningún módulo se considera terminado solo porque compile. Debe funcionar, respetar el backend existente, permisos y validaciones, tener tests suficientes, ser responsive y pasar integración.

## Reglas críticas

- Mantener API Controllers separados de Web Controllers.
- Usar Laravel Blade para el frontend.
- Usar `PUT`, no `PATCH`.
- Respetar exactamente `ADMIN` y `MESERO`.
- No crear módulo de cocina.
- No introducir arquitecturas nuevas innecesariamente.
- Antes de modificar archivos, declarar archivos a leer, crear y modificar.
- No editar simultáneamente el mismo archivo entre agentes salvo coordinación explícita.
- Especial cuidado con `routes/web.php`, `routes/api.php`, `bootstrap/app.php`, `public/css/app.css` y `layouts/app.blade.php`.
