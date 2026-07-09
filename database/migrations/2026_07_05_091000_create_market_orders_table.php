<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('market_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('status', [
                'pending', 'accepted', 'shopping', 'photo_sent',
                'validated', 'delivering', 'delivered', 'cancelled'
            ])->default('pending');
            $table->json('items'); // [{name, quantity, unit, estimated_price}]
            $table->text('notes')->nullable();
            $table->string('delivery_address');
            $table->decimal('delivery_latitude', 10, 8)->nullable();
            $table->decimal('delivery_longitude', 11, 8)->nullable();
            $table->decimal('estimated_total', 10, 2)->default(0);
            $table->decimal('actual_total', 10, 2)->nullable();
            $table->decimal('service_fee', 10, 2)->default(500);
            $table->decimal('delivery_fee', 10, 2)->default(500);
            $table->enum('payment_method', ['cash', 'mobile_money'])->default('cash');
            $table->enum('payment_status', ['pending', 'paid'])->default('pending');
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('photo_proof')->nullable(); // URLs of photos sent by driver
            $table->boolean('validated_by_client')->default(false);
            $table->timestamp('estimated_delivery_time')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('market_orders');
    }
};
