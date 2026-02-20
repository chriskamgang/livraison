<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', [
                'searching',    // Recherche livreur
                'assigned',     // Livreur assigné
                'going_to_restaurant', // En route vers resto
                'at_restaurant', // Au restaurant
                'picked_up',    // Commande récupérée
                'on_the_way',   // En route vers client
                'arrived',      // Arrivé chez client
                'delivered',    // Livré
                'failed',       // Echec livraison
            ])->default('searching');
            // Position restaurant
            $table->decimal('restaurant_latitude', 10, 8)->nullable();
            $table->decimal('restaurant_longitude', 11, 8)->nullable();
            // Position client (précise)
            $table->decimal('delivery_latitude', 10, 8)->nullable();
            $table->decimal('delivery_longitude', 11, 8)->nullable();
            $table->string('delivery_address');
            $table->string('delivery_address_details')->nullable(); // Appart, étage, etc.
            // Tracking
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->integer('estimated_minutes')->nullable();
            $table->string('delivery_proof_photo')->nullable(); // Photo preuve livraison
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->decimal('driver_earnings', 8, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
