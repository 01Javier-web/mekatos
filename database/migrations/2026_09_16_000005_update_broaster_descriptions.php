<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')
            ->whereIn('name', [
                '1/4 Pollo Broaster',
                '1/2 Pollo Broaster',
                'Pollo Broaster Completo',
            ])
            ->update([
                'description' => 'Acompañado con papa a la francesa y arepa frita',
            ]);
    }

    public function down(): void
    {
        DB::table('products')
            ->whereIn('name', [
                '1/4 Pollo Broaster',
                '1/2 Pollo Broaster',
            ])
            ->where('description', 'Acompañado con papa a la francesa y arepa frita')
            ->update([
                'description' => 'Papas a la francesa, arepa frita y miel.',
            ]);

        DB::table('products')
            ->where('name', 'Pollo Broaster Completo')
            ->where('description', 'Acompañado con papa a la francesa y arepa frita')
            ->update([
                'description' => 'Pollo broaster completo. Papas a la francesa, arepa frita y miel.',
            ]);
    }
};
