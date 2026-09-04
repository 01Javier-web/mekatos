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

## BUG-002 — Error 419 al iniciar sesión después de cerrar sesión con otro usuario

- **Fecha:** 2026-09-04
- **Síntoma:** Después de que un usuario cerraba sesión, el siguiente usuario que intentaba iniciar sesión podía recibir `419 | Page Expired` sin haber cerrado el navegador ni realizado ninguna acción adicional.
- **Causa raíz:** La sesión del usuario anterior se invalidaba, pero las cookies de sesión y CSRF podían permanecer en el navegador y provocar un estado de sesión/cookie obsoleto al intentar iniciar una nueva sesión.
- **Archivo afectado:** `app/Http/Controllers/Web/AuthController.php`.
- **Solución:** Mantener el cierre de sesión con `Auth::logout()`, invalidar la sesión y regenerar el token CSRF. Además, la respuesta de logout elimina automáticamente la cookie configurada para la sesión (`config('session.cookie')`) y la cookie `XSRF-TOKEN`.
- **Prevención:** En flujos donde varios usuarios puedan utilizar el mismo navegador, el logout debe invalidar la sesión y limpiar las cookies de sesión/CSRF correspondientes. No solucionar el problema eliminando `@csrf` del formulario.
- **Prueba realizada:** Se verificó el flujo `MESERO → cerrar sesión → ADMIN` y el cambio inverso `ADMIN → cerrar sesión → MESERO`, sin presentar nuevamente el error 419.
- **Estado:** Corregido y probado.
