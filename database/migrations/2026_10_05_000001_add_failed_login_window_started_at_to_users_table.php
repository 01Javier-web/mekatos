<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ventana de 15 minutos para los intentos fallidos de inicio de sesión.
 *
 * Guarda cuándo empezó la serie actual de fallos: solo cuentan los fallos
 * dentro de esa ventana. Solo agrega una columna que admite nulos; no modifica
 * datos ni las columnas failed_login_attempts y locked_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('failed_login_window_started_at')->nullable()->after('failed_login_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('failed_login_window_started_at');
        });
    }
};
