<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')->whereIn('status', ['PREPARANDO', 'LISTO'])->update(['status' => 'EN PREPARACIÓN']);
        DB::table('order_status_histories')->whereIn('previous_status', ['PREPARANDO', 'LISTO'])->update(['previous_status' => 'EN PREPARACIÓN']);
        DB::table('order_status_histories')->whereIn('new_status', ['PREPARANDO', 'LISTO'])->update(['new_status' => 'EN PREPARACIÓN']);
    }

    public function down(): void
    {
        // Los estados antiguos no se restauran para evitar volver a introducir estados operativos obsoletos.
    }
};
