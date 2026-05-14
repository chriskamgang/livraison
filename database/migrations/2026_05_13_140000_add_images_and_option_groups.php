<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Galerie d'images pour les articles (jusqu'à 5)
        Schema::table('menu_items', function (Blueprint $table) {
            $table->json('images')->nullable()->after('image');
        });

        // Groupes d'options (ex: "Choix du complement", "Taille", "Sauce")
        Schema::create('option_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // ex: "Choix du complement"
            $table->enum('type', ['single', 'multiple'])->default('single'); // single = radio, multiple = checkbox
            $table->boolean('is_required')->default(false);
            $table->integer('min_selections')->default(0);
            $table->integer('max_selections')->default(1);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Options individuelles dans un groupe
        Schema::create('option_group_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('option_group_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // ex: "Frites de plantain"
            $table->decimal('price_adjustment', 10, 2)->default(0); // supplement de prix
            $table->boolean('is_default')->default(false);
            $table->boolean('is_available')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Pivot: quels groupes d'options sont lies a quels articles
        Schema::create('menu_item_option_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('option_group_id')->constrained()->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->unique(['menu_item_id', 'option_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_option_group');
        Schema::dropIfExists('option_group_items');
        Schema::dropIfExists('option_groups');
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn('images');
        });
    }
};
