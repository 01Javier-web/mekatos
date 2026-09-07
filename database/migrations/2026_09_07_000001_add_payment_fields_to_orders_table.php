<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dateTime('paid_at')->nullable()->after('delivered_at');
            $table->foreignId('paid_by_user_id')
                ->nullable()
                ->after('paid_at')
                ->constrained('users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['paid_by_user_id']);
            $table->dropColumn(['paid_by_user_id', 'paid_at']);
        });
    }
};
