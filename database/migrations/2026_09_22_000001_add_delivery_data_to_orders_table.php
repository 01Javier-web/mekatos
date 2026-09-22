<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->decimal('delivery_fee', 10, 2)->default(0)->after('packaging_fee');
            $table->string('customer_name')->nullable()->after('total');
            $table->string('customer_phone', 30)->nullable()->after('customer_name');
            $table->string('delivery_address')->nullable()->after('customer_phone');
            $table->string('delivery_reference')->nullable()->after('delivery_address');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'delivery_fee',
                'customer_name',
                'customer_phone',
                'delivery_address',
                'delivery_reference',
            ]);
        });
    }
};
