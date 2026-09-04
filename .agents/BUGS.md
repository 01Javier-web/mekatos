# Registro de Bugs MEKATOS

Este archivo conserva errores confirmados para que los agentes los consulten antes de realizar cambios.

## BUG-001 — Error 419 al iniciar sesión

- **Fecha:** 2026-09-04
- **Síntoma:** Al enviar el formulario de login, `POST /login` responde HTTP 419 `unknown status`.
- **Causa raíz:** El proyecto utiliza el driver de sesión `database` por defecto en `config/session.php`, pero el repositorio no tenía una migración para crear la tabla `sessions`. El formulario de login sí incluye `@csrf`, por lo que el problema no era la ausencia del token CSRF.
- **Archivos afectados:** `config/session.php`, `resources/views/auth/login.blade.php`, `database/migrations/`.
- **Solución:** Añadir la migración oficial de la tabla `sessions` y ejecutar `php artisan migrate` en el entorno local.
- **Prevención:** Antes de implementar autenticación web, verificar que el driver de sesión configurado tenga su almacenamiento/migración disponible. No eliminar `@csrf` para solucionar un 419.
- **Estado:** Corregido en código; pendiente de ejecutar migraciones y probar en el entorno local.
