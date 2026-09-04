<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The role column is part of the original users table schema.
     * This migration is intentionally kept as a no-op for compatibility
     * with databases where it may already have been recorded.
     */
    public function up(): void
    {
        // Intentionally empty.
    }

    /**
     * The role column belongs to the users table and must not be removed
     * independently by this compatibility migration.
     */
    public function down(): void
    {
        // Intentionally empty.
    }
};
