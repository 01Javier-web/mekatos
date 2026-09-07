<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $canonicalNames = [
                'Pollo Broaster', '1/2 Pollo Broaster', '1/4 Pollo Broaster', 'Alitas BBQ',
                'Pataconazo', 'Patacón Criollo', 'Arepa Mixta', 'Arepa con Queso',
                'Fajitas', 'Desgranada', 'Costillitas Gratinada y Encebollada',
                'Chicharroncitos en Salsa BBQ', 'Lasañas', 'Chuzos de Res, Pollo o Cerdo',
                'Chuzo Encebollado o Gratinado', 'Chuzo Hawaiano o con Champiñones', 'Chuzo Mixto',
                'Salchipapa Loca', 'Salchipapa', 'Salchipapa Gratinada', 'Choripapa',
                'Perro Sencillo', 'Perro Americano', 'Choriperro', 'Perra', 'Perro Suiza',
                'Perro Loco', 'Perro Ranchero', 'Mini Hamburguesa', 'Hamburguesa de Casa Res 160 gr',
                'Hamburguesa Sencilla', 'Hamburguesa Doble Carne o Mixta', 'Hamburguesa de Pollo',
                'Hamburguesa de Búfalo', 'Hamburguesa Súper Especial', 'Hamburguesa Trifásica',
                'Hamburguesa Criolla', 'Club Sandwich', 'Sándwich Atun', 'Sándwich Mekatos', 'Suiza',
                'Mini Salchipapa', 'Sándwich', 'Chuleta de Cerdo', 'Carne de Res - 300 gr',
                'Carne de Cerdo - 300 gr', 'Pechuga a la Plancha - 300 gr', 'Pechuga con Champiñones',
                'Carne Mixta - 300 gr', 'Punta de Anca - 400 gr', 'Churrasco - 400 gr',
                'Sobrebarriga Dorada - 300 gr', 'Costillitas', 'Costilla a la BBQ - 400 gr',
                'Parrilla para Dos', 'Picada para Dos Personas', 'Picada para Tres o Cuatro Personas',
                'Picada para Cinco a Seis Personas', 'Picada para Seis a Ocho Personas', 'Ensalada César',
                'Jugo Natural Jarra', 'Limonada Jarra', 'Milo Jarra', 'Tamarindo Preparada',
                'Gaseosa 350 ml', 'Gaseosa 1.5', 'Agua Botella', 'Cerveza', 'Granizada de Naranja',
                'Granizada de Limón', 'Granizada de Lulo', 'Granizada de Mora', 'Granizada de Maracuyá',
                'Cerezada',
            ];

            $juiceCategory = DB::table('categories')
                ->where('name', 'Jugos y Bebidas Preparadas')
                ->first();

            $juice = DB::table('products')->where('name', 'Jugo Natural Jarra')->first();
            $legacyJuices = DB::table('products')
                ->whereIn('name', ['Jugo Natural Jarra - En Agua', 'Jugo Natural Jarra - En Leche'])
                ->get();

            if (! $juice && $legacyJuices->isNotEmpty()) {
                $waterJuice = $legacyJuices->firstWhere('name', 'Jugo Natural Jarra - En Agua') ?? $legacyJuices->first();

                DB::table('products')->where('id', $waterJuice->id)->update([
                    'name' => 'Jugo Natural Jarra',
                    'category_id' => $juiceCategory?->id ?? $waterJuice->category_id,
                    'description' => 'En agua $8.500 o en leche $9.500. Selecciona la fruta al pedir.',
                    'price' => 8500,
                    'is_available' => true,
                    'updated_at' => now(),
                ]);

                $juice = DB::table('products')->where('id', $waterJuice->id)->first();
            }

            if ($juice && $legacyJuices->isNotEmpty()) {
                $legacyIds = $legacyJuices->pluck('id')->filter(fn ($id) => $id !== $juice->id)->values()->all();
                if ($legacyIds) {
                    // Preserve historical line prices while consolidating the product identity.
                    DB::table('order_items')->whereIn('product_id', $legacyIds)->update(['product_id' => $juice->id]);
                    DB::table('products')->whereIn('id', $legacyIds)->delete();
                }
            }

            if ($juice) {
                DB::table('products')->where('id', $juice->id)->update([
                    'name' => 'Jugo Natural Jarra',
                    'category_id' => $juiceCategory?->id ?? $juice->category_id,
                    'description' => 'En agua $8.500 o en leche $9.500. Selecciona la fruta al pedir.',
                    'price' => 8500,
                    'is_available' => true,
                    'updated_at' => now(),
                ]);
            }

            // Remove products that are not part of the physical menu when they
            // have no order history. Referenced legacy rows stay untouched so
            // existing order history keeps its foreign-key integrity.
            DB::table('products')
                ->whereNotIn('name', $canonicalNames)
                ->whereNotExists(function ($query): void {
                    $query->select(DB::raw(1))
                        ->from('order_items')
                        ->whereColumn('order_items.product_id', 'products.id');
                })
                ->delete();

            if (Schema::hasTable('categories')) {
                DB::table('categories')
                    ->where('is_active', false)
                    ->whereNotExists(function ($query): void {
                        $query->select(DB::raw(1))
                            ->from('products')
                            ->whereColumn('products.category_id', 'categories.id');
                    })
                    ->delete();
            }
        });
    }

    public function down(): void
    {
        // Catalog cleanup is intentionally irreversible; the deleted legacy
        // catalog records were not part of the current physical menu.
    }
};
