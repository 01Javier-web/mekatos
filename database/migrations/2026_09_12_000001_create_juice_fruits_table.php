<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('juice_fruits', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('juice_fruits')->insert([
            ['name' => 'Maracuyá', 'sort_order' => 1, 'is_available' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Lulo', 'sort_order' => 2, 'is_available' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Mora', 'sort_order' => 3, 'is_available' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Fresa', 'sort_order' => 4, 'is_available' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('juice_fruits');
    }
};
