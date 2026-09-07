<?php

namespace Database\Seeders;

use App\Models\RestaurantTable;
use App\TableStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RestaurantTableSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(1, 41) as $number) {
            RestaurantTable::updateOrCreate(
                ['number' => $number],
                [
                    'name' => null,
                    'capacity' => 4,
                    'qr_token' => null,
                    'status' => TableStatus::AVAILABLE,
                ]
            );

            $table = RestaurantTable::where('number', $number)->first();
            if (! $table->qr_token) {
                $table->update(['qr_token' => Str::uuid()->toString()]);
            }
        }
    }
}
