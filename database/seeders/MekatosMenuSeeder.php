<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class MekatosMenuSeeder extends Seeder
{
    /**
     * Carga el menú de Mekatos Comidas Rápidas a partir de la carta física.
     *
     * No elimina productos existentes: los que no pertenecen al menú actual
     * quedan inactivos/no disponibles para conservar cualquier historial.
     */
    public function run(): void
    {
        Product::query()->update(['is_available' => false]);
        Category::query()->update(['is_active' => false]);

        $categories = [
            [
                'name' => 'Especialidades',
                'description' => 'Pollo broaster, patacones, fajitas, arepas y otras especialidades de la casa.',
                'sort_order' => 1,
                'products' => [
                    ['name' => 'Pollo Broaster', 'price' => 59000, 'description' => 'Papas a la francesa, arepa frita y miel.'],
                    ['name' => '1/2 Pollo Broaster', 'price' => 31000],
                    ['name' => '1/4 Pollo Broaster', 'price' => 18500],
                    ['name' => 'Alitas BBQ', 'price' => 29000, 'description' => '7 coditos bañados en salsa BBQ acompañados de papa a la francesa o papa criolla y huevos de codorniz.'],
                    ['name' => 'Pataconazo', 'price' => 29500, 'description' => 'Carne de res, pollo, quesillo gratinado y huevo de codorniz.'],
                    ['name' => 'Patacón Criollo', 'price' => 29500, 'description' => 'Carne de res, pollo, quesillo, maíz, chorizo y huevo de codorniz.'],
                    ['name' => 'Fajitas', 'price' => 29000, 'description' => 'Tortilla rellena de quesillo, pollo, carne, quesillo gratinado, acompañado de papa a la francesa.'],
                    ['name' => 'Desgranada', 'price' => 31000, 'description' => 'Maíz tierno, carne, pollo, tocineta y quesillo gratinado.'],
                    ['name' => 'Arepa Mixta', 'price' => 22000, 'description' => 'Res, pollo, chorizo, huevos, quesillo gratinado.'],
                    ['name' => 'Arepa con Queso', 'price' => 12500, 'description' => 'Rellena con mucho queso y jamón.'],
                    ['name' => 'Costillitas Gratinada y Encebollada', 'price' => 31000, 'description' => 'Papas a la francesa, costilla en trocitos y huevos de codorniz.'],
                    ['name' => 'Chicharroncitos en Salsa BBQ', 'price' => 29500, 'description' => 'Papa criolla.'],
                    ['name' => 'Lasañas', 'price' => 30500, 'description' => 'Carnes, pollo o mixtas.'],
                ],
            ],
            [
                'name' => 'Chuzos, Salchipapas y Perros',
                'description' => 'Chuzos, salchipapas y perros calientes de Mekatos.',
                'sort_order' => 2,
                'products' => [
                    ['name' => 'Chuzos de Res, Pollo o Cerdo', 'price' => 23500, 'description' => 'Acompañado de papa a la francesa.'],
                    ['name' => 'Chuzo Encebollado o Gratinado', 'price' => 26000],
                    ['name' => 'Chuzo Hawaiano o con Champiñones', 'price' => 30000, 'description' => 'Acompañado de papa a la francesa y ensalada.'],
                    ['name' => 'Chuzo Mixto', 'price' => 26000, 'description' => 'Carne de res, cerdo y pollo. Acompañado con papas a la francesa.'],
                    ['name' => 'Salchipapa Loca', 'price' => 29000, 'description' => 'Carne, pollo y gratinado.'],
                    ['name' => 'Salchipapa', 'price' => 18500, 'description' => 'Papa a la francesa, salchicha ranchera, huevos de codorniz, salsas al gusto.'],
                    ['name' => 'Salchipapa Gratinada', 'price' => 25000],
                    ['name' => 'Choripapa', 'price' => 19500, 'description' => 'Papa a la francesa, chorizo, huevos de codorniz, salsas al gusto.'],
                    ['name' => 'Perro Sencillo', 'price' => 13000, 'description' => 'Pan, salchicha, quesillo, salsas al gusto.'],
                    ['name' => 'Perro Americano', 'price' => 14000, 'description' => 'Pan, salchicha americana, quesillo, salsas al gusto.'],
                    ['name' => 'Choriperro', 'price' => 16500, 'description' => 'Pan, chorizo, quesillo, salsas al gusto.'],
                    ['name' => 'Perra', 'price' => 23500, 'description' => 'Pan, salchicha americana, doble porción de quesillo, tocineta.'],
                    ['name' => 'Perro Suiza', 'price' => 18500],
                    ['name' => 'Perro Loco', 'price' => 21500],
                    ['name' => 'Perro Ranchero', 'price' => 14500],
                ],
            ],
            [
                'name' => 'Hamburguesas',
                'description' => 'Cualquiera de nuestras hamburguesas más papa a la francesa y gaseosa por solo $10.000 adicionales.',
                'sort_order' => 3,
                'products' => [
                    ['name' => 'Mini Hamburguesa', 'price' => 15000, 'description' => 'Salsas, carne, queso y tomate.'],
                    ['name' => 'Hamburguesa Sencilla', 'price' => 23500, 'description' => 'Carne casera, quesillo, lechuga, tomate, pan, salsas al gusto.'],
                    ['name' => 'Hamburguesa Criolla', 'price' => 32000, 'description' => 'Carne casera, tocineta, quesillo, jamón, lechuga, tomate, pan, salsas al gusto. Huevo frito y maíz tierno.'],
                    ['name' => 'Hamburguesa de Casa Res 160 gr', 'price' => 27500, 'description' => 'Carne casera, tocineta, quesillo, jamón, lechuga, tomate, cebolla, pan, salsas al gusto.'],
                    ['name' => 'Hamburguesa Doble Carne o Mixta', 'price' => 35000, 'description' => 'Carne casera, tocineta, quesillo, jamón, lechuga, tomate, pan, salsas al gusto.'],
                    ['name' => 'Hamburguesa de Pollo', 'price' => 27500, 'description' => 'Pollo apanado, tocineta, quesillo, jamón, tomate, lechuga, pan, salsa al gusto.'],
                    ['name' => 'Hamburguesa de Búfalo', 'price' => 27500, 'description' => 'Carne de búfalo, tocineta, quesillo, jamón, lechuga, tomate, pan, salsas al gusto.'],
                    ['name' => 'Hamburguesa Súper Especial', 'price' => 45000, 'description' => 'Doble de todo.'],
                    ['name' => 'Hamburguesa Trifásica', 'price' => 43000, 'description' => 'Carne de pollo, res y búfalo, tocineta, quesillo, jamón, lechuga, tomate, salsa al gusto.'],
                ],
            ],
            [
                'name' => 'Sándwiches',
                'description' => 'Sándwiches de la casa acompañados según lo indicado en la carta.',
                'sort_order' => 4,
                'products' => [
                    ['name' => 'Club Sandwich', 'price' => 29500, 'description' => 'Pan tostado, repollo, tocineta, huevo frito, quesillo, jamón, lechuga, tomate. Acompañado de papas a la francesa.'],
                    ['name' => 'Sándwich Atún', 'price' => 27500, 'description' => 'Acompañado con papas a la francesa.'],
                    ['name' => 'Sándwich Mekatos', 'price' => 28500, 'description' => 'Pan tierno, carne y pollo bañado en salsa de queso, quesillo, jamón, lechuga, tomate rojo, acompañado con papa a la francesa.'],
                    ['name' => 'Suiza', 'price' => 18500, 'description' => 'Acompañado de papa a la francesa y salsas al gusto.'],
                ],
            ],
            [
                'name' => 'Menú Infantil',
                'description' => 'Opciones del menú infantil de Mekatos.',
                'sort_order' => 5,
                'products' => [
                    ['name' => 'Mini Salchipapa', 'price' => 15000, 'description' => 'Papa a la francesa + salchicha ranchera + huevos de codorniz y salsas al gusto.'],
                    ['name' => 'Sándwich Infantil', 'price' => 10000, 'description' => 'Quesillo, jamón y salsas.'],
                ],
            ],
            [
                'name' => 'Parrilla',
                'description' => 'Las carnes van acompañadas de 150 gr de papa a la francesa, yuca, ensalada, ají y salsas al gusto.',
                'sort_order' => 6,
                'products' => [
                    ['name' => 'Chuleta de Cerdo', 'price' => 33500],
                    ['name' => 'Carne de Res - 300 gr', 'price' => 32000],
                    ['name' => 'Carne de Cerdo - 300 gr', 'price' => 32000],
                    ['name' => 'Pechuga a la Plancha - 300 gr', 'price' => 32000],
                    ['name' => 'Pechuga con Champiñones', 'price' => 37000],
                    ['name' => 'Carne Mixta - 300 gr', 'price' => 34000],
                    ['name' => 'Punta de Anca - 400 gr', 'price' => 42000],
                    ['name' => 'Churrasco - 400 gr', 'price' => 42000],
                    ['name' => 'Sobrebarriga Dorada - 300 gr', 'price' => 37000],
                    ['name' => 'Costillitas', 'price' => 27000],
                    ['name' => 'Costilla a la BBQ - 400 gr', 'price' => 37000],
                    ['name' => 'Parrilla para Dos', 'price' => 54000, 'description' => 'Carne de res, cerdo, pollo, chorizo y costilla B.B.Q.'],
                    ['name' => 'Picada para Dos Personas', 'price' => 60000, 'description' => 'Carne de res, cerdo, pollo, chorizo y huevos de codorniz.'],
                    ['name' => 'Picada para Tres o Cuatro Personas', 'price' => 88000, 'description' => 'Carne de res, cerdo, pollo, chorizo y huevos de codorniz.'],
                    ['name' => 'Picada para Cinco a Seis Personas', 'price' => 120000, 'description' => 'Carne de res, cerdo, pollo, chorizo, huevos de codorniz y costilla ahumada.'],
                    ['name' => 'Picada para Seis a Ocho Personas', 'price' => 145000, 'description' => 'Carne de res, cerdo, pollo, chorizo, huevos de codorniz y costilla ahumada.'],
                ],
            ],
            [
                'name' => 'Ensaladas',
                'description' => 'Ensaladas de la carta de Mekatos.',
                'sort_order' => 7,
                'products' => [
                    ['name' => 'Ensalada César', 'price' => 34000, 'description' => 'Lechuga, pollo, crotones de pan, salsa César, queso parmesano.'],
                ],
            ],
            [
                'name' => 'Bebidas',
                'description' => 'Bebidas de la carta de Mekatos. El jugo natural en jarra tiene precio diferente según sea en agua o en leche.',
                'sort_order' => 8,
                'products' => [
                    ['name' => 'Jugo Natural Jarra - En Agua', 'price' => 8500],
                    ['name' => 'Jugo Natural Jarra - En Leche', 'price' => 9500],
                    ['name' => 'Limonada Jarra', 'price' => 6500],
                    ['name' => 'Milo Jarra', 'price' => 10000],
                    ['name' => 'Gaseosa 350 ml', 'price' => 4500],
                    ['name' => 'Gaseosa 1.5', 'price' => 9500],
                    ['name' => 'Agua Botella', 'price' => 3500],
                    ['name' => 'Cerveza', 'price' => 5000],
                    ['name' => 'Tamarindo Preparada', 'price' => 5500],
                ],
            ],
            [
                'name' => 'Granizadas',
                'description' => 'Granizadas de naranja, limón, lulo, mora y maracuyá en agua. Cerezada a $10.000.',
                'sort_order' => 9,
                'products' => [
                    ['name' => 'Granizada de Naranja', 'price' => 8500, 'description' => 'En agua.'],
                    ['name' => 'Granizada de Limón', 'price' => 8500, 'description' => 'En agua.'],
                    ['name' => 'Granizada de Lulo', 'price' => 8500, 'description' => 'En agua.'],
                    ['name' => 'Granizada de Mora', 'price' => 8500, 'description' => 'En agua.'],
                    ['name' => 'Granizada de Maracuyá', 'price' => 8500, 'description' => 'En agua.'],
                    ['name' => 'Cerezada', 'price' => 10000],
                ],
            ],
        ];

        foreach ($categories as $categoryData) {
            $category = Category::updateOrCreate(
                ['name' => $categoryData['name']],
                [
                    'description' => $categoryData['description'],
                    'sort_order' => $categoryData['sort_order'],
                    'is_active' => true,
                ]
            );

            foreach ($categoryData['products'] as $productData) {
                Product::updateOrCreate(
                    [
                        'category_id' => $category->id,
                        'name' => $productData['name'],
                    ],
                    [
                        'description' => $productData['description'] ?? null,
                        'price' => $productData['price'],
                        'image_path' => null,
                        'is_available' => true,
                    ]
                );
            }
        }
    }
}
