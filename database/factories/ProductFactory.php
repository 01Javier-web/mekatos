<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Genera un nombre falso.
            'name' => fake()->words(3, true),

            // Genera una descripción falsa.
            'description' => fake()->sentence(),

            // Precio aleatorio entre 100 y 2000.
            'price' => fake()->randomFloat(2, 100, 2000),

            // Por defecto el producto estará disponible.
            'is_available' => true,
        ];
    }
}
