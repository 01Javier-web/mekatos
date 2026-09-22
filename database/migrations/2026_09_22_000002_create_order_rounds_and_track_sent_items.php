<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['order_id', 'number']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('order_round_id')->nullable()->after('order_id')->constrained('order_rounds')->nullOnDelete();
            $table->timestamp('sent_at')->nullable()->after('notes');
            $table->index(['order_id', 'sent_at']);
        });

        $orders=DB::table('orders')->select('id','status','created_at')->orderBy('id')->get();
        foreach($orders as $order){
            $roundId=DB::table('order_rounds')->insertGetId([
                'order_id'=>$order->id,
                'number'=>1,
                'created_by_user_id'=>null,
                'created_at'=>$order->created_at,
                'updated_at'=>$order->created_at,
            ]);

            $sentAt=$order->status === 'PENDIENTE' ? null : $order->created_at;
            DB::table('order_items')
                ->where('order_id',$order->id)
                ->update(['order_round_id'=>$roundId,'sent_at'=>$sentAt]);
        }
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['order_id', 'sent_at']);
            $table->dropForeign(['order_round_id']);
            $table->dropColumn(['order_round_id','sent_at']);
        });

        Schema::dropIfExists('order_rounds');
    }
};
