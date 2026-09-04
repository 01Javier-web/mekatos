<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\UserRole;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Rol interno del usuario.
            //
            // Por seguridad no recomiendo que ADMIN
            // sea el default.
            $table->string('role')
                ->default(UserRole::Waiter->value)
                ->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Rol interno del usuario.
            //
            // Por seguridad no recomiendo que ADMIN
            // sea el default.
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        });
    }
};
