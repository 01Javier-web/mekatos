<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bloqueo de usuarios por intentos fallidos de inicio de sesión.
 *
 * Solo agrega columnas con valores por defecto: no modifica datos existentes
 * y todos los usuarios actuales quedan desbloqueados (contador 0, sin bloqueo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedTinyInteger('failed_login_attempts')->default(0)->after('is_active');
            $table->timestamp('locked_at')->nullable()->after('failed_login_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['failed_login_attempts', 'locked_at']);
        });
    }
};
