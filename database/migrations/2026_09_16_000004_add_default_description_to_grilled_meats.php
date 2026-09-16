<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $description = 'Acompañada de papa a la francesa, yuca frita, ensalada y aji';
        $costillitasDescription = 'Acompañadas con papa a la francesa y huevos de codorniz';

        DB::table('products')
            ->whereIn('name', [
                'Chuleta de Cerdo',
                'Carne de Res - 300 gr',
                'Carne de Cerdo - 300 gr',
                'Pechuga a la Plancha - 300 gr',
                'Pechuga con Champiñones',
                'Carne Mixta - 300 gr',
                'Punta de Anca - 400 gr',
                'Churrasco - 400 gr',
                'Sobrebarriga Dorada - 300 gr',
                'Costilla a la BBQ - 400 gr',
            ])
            ->where(function ($query) {
                $query->whereNull('description')
                    ->orWhere('description', '');
            })
            ->update(['description' => $description]);

        DB::table('products')
            ->where('name', 'Costillitas')
            ->where(function ($query) {
                $query->whereNull('description')
                    ->orWhere('description', '');
            })
            ->update(['description' => $costillitasDescription]);
    }

    public function down(): void
    {
        $description = 'Acompañada de papa a la francesa, yuca frita, ensalada y aji';
        $costillitasDescription = 'Acompañadas con papa a la francesa y huevos de codorniz';

        DB::table('products')
            ->whereIn('name', [
                'Chuleta de Cerdo',
                'Carne de Res - 300 gr',
                'Carne de Cerdo - 300 gr',
                'Pechuga a la Plancha - 300 gr',
                'Pechuga con Champiñones',
                'Carne Mixta - 300 gr',
                'Punta de Anca - 400 gr',
                'Churrasco - 400 gr',
                'Sobrebarriga Dorada - 300 gr',
                'Costilla a la BBQ - 400 gr',
            ])
            ->where('description', $description)
            ->update(['description' => null]);

        DB::table('products')
            ->where('name', 'Costillitas')
            ->where('description', $costillitasDescription)
            ->update(['description' => null]);
    }
};
