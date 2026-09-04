<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The sessions table is already created by the original users migration.
     * This compatibility migration is intentionally a no-op.
     */
    public function up(): void
    {
        // Intentionally empty.
    }

    public function down(): void
    {
        // Intentionally empty.
    }
};
