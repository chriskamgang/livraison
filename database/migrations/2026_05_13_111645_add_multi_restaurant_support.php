<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Ajouter restaurant_id aux users (pour lier un admin restaurant à son restaurant)
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('restaurant_id')->nullable()->after('role')
                  ->constrained('restaurants')->nullOnDelete();
        });

        // Modifier l'enum role pour ajouter restaurant_admin
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('client', 'driver', 'admin', 'super_admin', 'restaurant_admin') DEFAULT 'client'");

        // Ajouter user_id (owner) aux restaurants
        Schema::table('restaurants', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')
                  ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('client', 'driver', 'admin', 'super_admin') DEFAULT 'client'");

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['restaurant_id']);
            $table->dropColumn('restaurant_id');
        });
    }
};
