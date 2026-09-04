<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow takeaway orders to exist without a table session.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('table_session_id')
                ->nullable()
                ->change();
        });
    }

    /**
     * Restore the original required table session constraint.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('table_session_id')
                ->nullable(false)
                ->change();
        });
    }
};
