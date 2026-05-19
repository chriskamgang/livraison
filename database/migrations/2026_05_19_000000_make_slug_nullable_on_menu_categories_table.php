<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('menu_categories', 'slug')) {
                $table->string('slug')->nullable()->after('name');
            }
        });

        // If column exists but is NOT NULL, make it nullable
        if (Schema::hasColumn('menu_categories', 'slug')) {
            Schema::table('menu_categories', function (Blueprint $table) {
                $table->string('slug')->nullable()->change();
            });
        }

        // Generate slugs for existing categories that don't have one
        $categories = \App\Models\MenuCategory::whereNull('slug')->orWhere('slug', '')->get();
        foreach ($categories as $category) {
            $category->slug = \Illuminate\Support\Str::slug($category->name);
            $category->save();
        }
    }

    public function down(): void
    {
        // No rollback needed
    }
};
