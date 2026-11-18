<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Les commandes annulées avant ce correctif gardaient une livraison « searching ».
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('deliveries')
            ->whereIn('status', ['searching', 'assigned', 'going_to_restaurant', 'at_restaurant'])
            ->whereIn('order_id', DB::table('orders')->whereIn('status', ['cancelled', 'refunded'])->select('id'))
            ->update(['status' => 'failed', 'notes' => 'Commande annulée', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Rien à défaire : données corrigées.
    }
};
