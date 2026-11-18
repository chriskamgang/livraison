<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Les colonnes ENUM refusaient des valeurs que le code utilise déjà
 * (orders.payment_status = 'cash_on_delivery', payments.status = 'cancelled')
 * et la nouvelle méthode 'gfs'. On passe en VARCHAR : aucune valeur existante n'est perdue.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' && DB::getDriverName() !== 'mariadb') {
            return;
        }

        DB::statement("ALTER TABLE payments MODIFY COLUMN method VARCHAR(30) NOT NULL DEFAULT 'cash'");
        DB::statement("ALTER TABLE payments MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE orders MODIFY COLUMN payment_status VARCHAR(30) NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Pas de retour en ENUM : il supprimerait les valeurs 'gfs', 'cancelled', 'cash_on_delivery'.
    }
};
