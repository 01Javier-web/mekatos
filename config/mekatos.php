<?php

return [

    /*
    | Correo que recibe el enlace de recuperación de emergencia cuando el único
    | administrador funcional queda bloqueado por intentos fallidos.
    | El valor real se define solo en el .env de cada entorno.
    */
    'admin_recovery_email' => env('ADMIN_RECOVERY_EMAIL'),

];
