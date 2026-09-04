<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('table_session_id')
                ->nullable()
                ->constrained('table_sessions')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string('status')->default('PENDING');

            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('total', 10, 2);

            $table->text('notes')->nullable();

            $table->foreignId('handled_by_user_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('delivered_by_user_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->dateTime('delivered_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
